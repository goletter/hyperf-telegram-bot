<?php

declare(strict_types=1);

namespace Goletter\Telegram;

use Goletter\Telegram\Contract\BotInterface;
use Goletter\Telegram\Exceptions\TelegramApiException;
use Goletter\Telegram\Http\TelegramHttpClient;
use Goletter\Telegram\Update\Update;
use GuzzleHttp\ClientInterface;

/**
 * Telegram Bot API 客户端实现。
 *
 * 请通过 BotFactory::token() / resolve() / get() 创建，避免直接 new。
 */
class Bot implements BotInterface
{
    protected TelegramHttpClient $http;

    /**
     * @param string $token Bot Token，格式 {bot_id}:{secret}
     * @param ClientInterface $client Guzzle HTTP 客户端（通常由工厂注入共享实例）
     * @param string $name 逻辑名称：配置名或业务缓存键
     * @param string $baseUri Telegram API 根地址
     * @param string $webhookSecret setWebhook / 校验推送用的 secret_token
     */
    public function __construct(
        protected string $token,
        ClientInterface $client,
        protected string $name = 'default',
        string $baseUri = 'https://api.telegram.org',
        protected string $webhookSecret = ''
    ) {
        $this->token = self::normalizeToken($token);
        $this->http = new TelegramHttpClient($client, $this->token, $baseUri);
    }

    /**
     * 规范化并校验 Bot Token（格式：123456789:AAH...）。
     *
     * 会去掉误传入的 `https://api.telegram.org/bot` 或 `bot` 前缀。
     *
     * @throws TelegramApiException Token 为空或格式非法时
     */
    public static function normalizeToken(string $token): string
    {
        $token = trim($token);
        // 误把 URL 或带 bot 前缀的值整段塞进来时去掉前缀
        $token = preg_replace('#^https?://api\.telegram\.org/bot#i', '', $token) ?? $token;
        $token = preg_replace('#^bot#i', '', $token) ?? $token;
        $token = trim($token);

        if ($token === '') {
            throw new TelegramApiException('Telegram bot token is empty.');
        }

        // 合法格式：<bot_id>:<secret>，例如 7123456789:AAHxxxx
        if (! preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
            throw new TelegramApiException(
                'Invalid Telegram bot token format. Expected "{bot_id}:{secret}", e.g. "7123456789:AAHxxxx". '
                . 'A bare secret without the numeric bot_id prefix will cause Telegram to respond with "Not Found".'
            );
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取创建时绑定的 Webhook Secret Token（可能为空字符串）。
     */
    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    /**
     * {@inheritdoc}
     *
     * @throws TelegramApiException
     */
    public function call(string $method, array $params = []): mixed
    {
        return $this->http->request($method, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMe(): array
    {
        return (array) $this->call('getMe');
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessage(array $params): array
    {
        return (array) $this->call('sendMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editMessageText(array $params): array
    {
        return (array) $this->call('editMessageText', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMessage(array $params): bool
    {
        return (bool) $this->call('deleteMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendPhoto(array $params): array
    {
        return (array) $this->call('sendPhoto', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendDocument(array $params): array
    {
        return (array) $this->call('sendDocument', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function answerCallbackQuery(array $params): bool
    {
        return (bool) $this->call('answerCallbackQuery', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdates(array $params = []): array
    {
        $result = $this->call('getUpdates', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     *
     * 已配置 webhookSecret 且未传 secret_token 时自动附带。
     */
    public function setWebhook(array $params): bool
    {
        if ($this->webhookSecret !== '' && ! isset($params['secret_token'])) {
            $params['secret_token'] = $this->webhookSecret;
        }

        return (bool) $this->call('setWebhook', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteWebhook(array $params = []): bool
    {
        return (bool) $this->call('deleteWebhook', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebhookInfo(): array
    {
        return (array) $this->call('getWebhookInfo');
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(array $params): array
    {
        return (array) $this->call('getFile', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function exportChatInviteLink(array $params): string
    {
        return (string) $this->call('exportChatInviteLink', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function createChatInviteLink(array $params): array
    {
        return (array) $this->call('createChatInviteLink', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editChatInviteLink(array $params): array
    {
        return (array) $this->call('editChatInviteLink', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeChatInviteLink(array $params): array
    {
        return (array) $this->call('revokeChatInviteLink', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function approveChatJoinRequest(array $params): bool
    {
        return (bool) $this->call('approveChatJoinRequest', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function declineChatJoinRequest(array $params): bool
    {
        return (bool) $this->call('declineChatJoinRequest', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function banChatMember(array $params): bool
    {
        return (bool) $this->call('banChatMember', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function unbanChatMember(array $params): bool
    {
        return (bool) $this->call('unbanChatMember', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getChatMember(array $params): array
    {
        return (array) $this->call('getChatMember', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getChatAdministrators(array $params): array
    {
        $result = $this->call('getChatAdministrators', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getChatMemberCount(array $params): int
    {
        return (int) $this->call('getChatMemberCount', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getChat(array $params): array
    {
        return (array) $this->call('getChat', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupMember(int|string $chatId, int $userId): array
    {
        return $this->getChatMember([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupAdmins(int|string $chatId): array
    {
        return $this->getChatAdministrators([
            'chat_id' => $chatId,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupMemberCount(int|string $chatId): int
    {
        return $this->getChatMemberCount([
            'chat_id' => $chatId,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * 单个 user_id 查询失败时对应值为 null，不中断整批。
     */
    public function getGroupMembers(int|string $chatId, array $userIds): array
    {
        $members = [];

        foreach ($userIds as $userId) {
            $id = (int) $userId;
            try {
                $members[$id] = $this->getGroupMember($chatId, $id);
            } catch (TelegramApiException) {
                $members[$id] = null;
            }
        }

        return $members;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupUsers(int|string $chatId): array
    {
        return [
            'chat_id' => $chatId,
            'count' => $this->getGroupMemberCount($chatId),
            'administrators' => $this->getGroupAdmins($chatId),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Bot 须为群管理员且具备 can_invite_users 权限。
     */
    public function inviteToChat(int|string $chatId, array $options = []): array
    {
        return $this->createChatInviteLink(array_merge($options, [
            'chat_id' => $chatId,
        ]));
    }

    /**
     * {@inheritdoc}
     *
     * @throws TelegramApiException payload 无法解析为数组时
     */
    public function parseUpdate(array|string $payload): Update
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (! is_array($decoded)) {
                throw new TelegramApiException('Invalid Telegram update payload.');
            }
            $payload = $decoded;
        }

        return new Update($payload);
    }

    /**
     * 校验 Webhook 请求头中的 Secret Token。
     *
     * 未配置 webhookSecret 时始终返回 true（不做校验）。
     *
     * @param string|null $secretToken 通常来自 X-Telegram-Bot-Api-Secret-Token
     */
    public function verifyWebhookSecret(?string $secretToken): bool
    {
        if ($this->webhookSecret === '') {
            return true;
        }

        return hash_equals($this->webhookSecret, (string) $secretToken);
    }
}
