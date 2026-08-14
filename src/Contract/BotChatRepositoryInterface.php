<?php

declare(strict_types=1);

namespace Goletter\Telegram\Contract;

/**
 * Bot ↔ 群/频道 关系仓储。
 *
 * Telegram Bot API **无法**列出 Bot 已加入的群，必须从 Update（尤其是
 * my_chat_member）自行落库后，才能按 Bot 查询群信息。
 *
 * 默认提供内存实现；生产环境请绑定你们自己的数据库实现。
 */
interface BotChatRepositoryInterface
{
    /**
     * 写入或更新 Bot 与某个会话的关系。
     *
     * @param string $botKey Bot 逻辑键（通常为 Bot::getName()）
     * @param array{
     *     chat_id: int|string,
     *     type?: string,
     *     title?: string|null,
     *     username?: string|null,
     *     status?: string,
     *     active?: bool,
     *     chat?: array<string, mixed>
     * } $record
     */
    public function upsert(string $botKey, array $record): void;

    /**
     * 标记 Bot 已离开该会话（或直接删除，由实现决定）。
     *
     * @param int|string $chatId
     */
    public function markLeft(string $botKey, int|string $chatId, string $status = 'left'): void;

    /**
     * 按 Bot 列出已知会话。
     *
     * @param bool $activeOnly 仅返回仍在群内的记录
     * @return list<array{
     *     bot: string,
     *     chat_id: int|string,
     *     type: string,
     *     title: string|null,
     *     username: string|null,
     *     status: string,
     *     active: bool,
     *     updated_at: int,
     *     chat: array<string, mixed>
     * }>
     */
    public function listByBot(string $botKey, bool $activeOnly = true): array;

    /**
     * 查找单条关系。
     *
     * @param int|string $chatId
     * @return array<string, mixed>|null
     */
    public function find(string $botKey, int|string $chatId): ?array;
}
