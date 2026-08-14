<?php

declare(strict_types=1);

namespace Goletter\Telegram\Store;

use Goletter\Telegram\Contract\BotChatRepositoryInterface;

/**
 * 进程内内存实现：适合开发 / 单进程演示。
 *
 * 多 Worker / 重启后数据丢失，生产请换数据库实现。
 */
class InMemoryBotChatRepository implements BotChatRepositoryInterface
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $items = [];

    /**
     * {@inheritdoc}
     */
    public function upsert(string $botKey, array $record): void
    {
        $chatId = (string) ($record['chat_id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $existing = $this->items[$botKey][$chatId] ?? [];
        $status = (string) ($record['status'] ?? $existing['status'] ?? 'member');
        $active = array_key_exists('active', $record)
            ? (bool) $record['active']
            : $this->isActiveStatus($status);

        $this->items[$botKey][$chatId] = [
            'bot' => $botKey,
            'chat_id' => $record['chat_id'],
            'type' => (string) ($record['type'] ?? $existing['type'] ?? ''),
            'title' => $record['title'] ?? $existing['title'] ?? null,
            'username' => $record['username'] ?? $existing['username'] ?? null,
            'status' => $status,
            'active' => $active,
            'updated_at' => time(),
            'chat' => is_array($record['chat'] ?? null)
                ? $record['chat']
                : (is_array($existing['chat'] ?? null) ? $existing['chat'] : []),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function markLeft(string $botKey, int|string $chatId, string $status = 'left'): void
    {
        $key = (string) $chatId;
        $existing = $this->items[$botKey][$key] ?? null;
        if ($existing === null) {
            $this->items[$botKey][$key] = [
                'bot' => $botKey,
                'chat_id' => $chatId,
                'type' => '',
                'title' => null,
                'username' => null,
                'status' => $status,
                'active' => false,
                'updated_at' => time(),
                'chat' => [],
            ];

            return;
        }

        $existing['status'] = $status;
        $existing['active'] = false;
        $existing['updated_at'] = time();
        $this->items[$botKey][$key] = $existing;
    }

    /**
     * {@inheritdoc}
     */
    public function listByBot(string $botKey, bool $activeOnly = true): array
    {
        $rows = array_values($this->items[$botKey] ?? []);
        if (! $activeOnly) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (bool) ($row['active'] ?? false)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $botKey, int|string $chatId): ?array
    {
        return $this->items[$botKey][(string) $chatId] ?? null;
    }

    /**
     * 清空全部或指定 Bot 的缓存（测试用）。
     */
    public function flush(?string $botKey = null): void
    {
        if ($botKey === null) {
            $this->items = [];

            return;
        }

        unset($this->items[$botKey]);
    }

    protected function isActiveStatus(string $status): bool
    {
        return in_array($status, ['creator', 'administrator', 'member', 'restricted'], true);
    }
}
