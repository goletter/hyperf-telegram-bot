<?php

declare(strict_types=1);

namespace Goletter\Telegram\Service;

use Goletter\Telegram\Bot;
use Goletter\Telegram\Contract\BotChatRepositoryInterface;
use Goletter\Telegram\Exceptions\TelegramApiException;
use Goletter\Telegram\Update\Update;

/**
 * 维护「Bot 有哪些群」：从 Update 同步，再按 Bot 查询。
 *
 * Webhook / Long Polling 收到更新后调用 sync()；业务侧用 getGroups() 按 Bot 取群列表。
 */
class BotChatTracker
{
    private const ACTIVE_STATUSES = ['creator', 'administrator', 'member', 'restricted'];

    private const GROUP_TYPES = ['group', 'supergroup', 'channel'];

    public function __construct(protected BotChatRepositoryInterface $repository)
    {
    }

    /**
     * 从 Update 同步 Bot ↔ 群关系。
     *
     * 优先处理 my_chat_member（Bot 进退群）；也会从带群聊 chat 的消息中发现新群。
     *
     * @return bool 是否写入/更新了仓储
     */
    public function sync(Bot $bot, Update $update): bool
    {
        $botKey = $bot->getName();

        $myMember = $update->getMyChatMember();
        if ($myMember !== null) {
            return $this->syncMyChatMember($botKey, $myMember);
        }

        $chat = $update->getChat();
        if ($chat === null) {
            return false;
        }

        $type = (string) ($chat['type'] ?? '');
        if (! in_array($type, self::GROUP_TYPES, true)) {
            return false;
        }

        $chatId = $chat['id'] ?? null;
        if ($chatId === null) {
            return false;
        }

        $this->repository->upsert($botKey, [
            'chat_id' => $chatId,
            'type' => $type,
            'title' => $chat['title'] ?? null,
            'username' => $chat['username'] ?? null,
            'status' => 'member',
            'active' => true,
            'chat' => $chat,
        ]);

        return true;
    }

    /**
     * 按 Bot 获取已知群信息。
     *
     * @param bool $refresh true 时对每条记录调用 getChat / getGroupMemberCount 刷新
     * @param bool $activeOnly 仅返回仍在群内的记录
     * @return list<array<string, mixed>>
     */
    public function getGroups(Bot $bot, bool $refresh = false, bool $activeOnly = true): array
    {
        $rows = $this->repository->listByBot($bot->getName(), $activeOnly);
        if (! $refresh) {
            return $rows;
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->refreshRow($bot, $row);
        }

        return $result;
    }

    /**
     * 获取单个群的本地记录；可选实时刷新。
     *
     * @param int|string $chatId
     * @return array<string, mixed>|null
     */
    public function getGroup(Bot $bot, int|string $chatId, bool $refresh = false): ?array
    {
        $row = $this->repository->find($bot->getName(), $chatId);
        if ($row === null) {
            if (! $refresh) {
                return null;
            }

            try {
                $info = $bot->getGroupInfo($chatId);
            } catch (TelegramApiException) {
                return null;
            }

            $chat = $info['chat'];
            $row = [
                'bot' => $bot->getName(),
                'chat_id' => $chatId,
                'type' => (string) ($chat['type'] ?? ''),
                'title' => $chat['title'] ?? null,
                'username' => $chat['username'] ?? null,
                'status' => 'member',
                'active' => true,
                'updated_at' => time(),
                'chat' => $chat,
                'live' => $info,
            ];

            $this->repository->upsert($bot->getName(), $row);

            return $row;
        }

        return $refresh ? $this->refreshRow($bot, $row) : $row;
    }

    public function repository(): BotChatRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @param array<string, mixed> $myMember ChatMemberUpdated
     */
    protected function syncMyChatMember(string $botKey, array $myMember): bool
    {
        $chat = $myMember['chat'] ?? null;
        if (! is_array($chat) || ! isset($chat['id'])) {
            return false;
        }

        $type = (string) ($chat['type'] ?? '');
        if (! in_array($type, self::GROUP_TYPES, true)) {
            return false;
        }

        $newMember = $myMember['new_chat_member'] ?? null;
        $status = is_array($newMember) ? (string) ($newMember['status'] ?? '') : '';
        $chatId = $chat['id'];

        if ($status === '' || ! in_array($status, self::ACTIVE_STATUSES, true)) {
            $this->repository->markLeft($botKey, $chatId, $status !== '' ? $status : 'left');

            return true;
        }

        $this->repository->upsert($botKey, [
            'chat_id' => $chatId,
            'type' => $type,
            'title' => $chat['title'] ?? null,
            'username' => $chat['username'] ?? null,
            'status' => $status,
            'active' => true,
            'chat' => $chat,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function refreshRow(Bot $bot, array $row): array
    {
        $chatId = $row['chat_id'];

        try {
            $info = $bot->getGroupInfo($chatId);
            $chat = $info['chat'];
            $row['type'] = (string) ($chat['type'] ?? $row['type'] ?? '');
            $row['title'] = $chat['title'] ?? $row['title'] ?? null;
            $row['username'] = $chat['username'] ?? $row['username'] ?? null;
            $row['chat'] = $chat;
            $row['live'] = $info;
            $row['active'] = true;
            $row['updated_at'] = time();

            $this->repository->upsert($bot->getName(), $row);
        } catch (TelegramApiException) {
            $row['live'] = null;
            $row['active'] = false;
            $this->repository->markLeft($bot->getName(), $chatId, (string) ($row['status'] ?? 'left'));
        }

        return $row;
    }
}
