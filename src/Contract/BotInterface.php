<?php

declare(strict_types=1);

namespace Goletter\Telegram\Contract;

use Goletter\Telegram\Update\Update;

/**
 * Telegram Bot 客户端契约。
 *
 * 业务侧通常通过 BotFactory 获取实现，再调用本接口方法。
 */
interface BotInterface
{
    /**
     * 获取当前 Bot Token（已规范化）。
     */
    public function getToken(): string;

    /**
     * 获取 Bot 逻辑名称（配置名或业务缓存名）。
     */
    public function getName(): string;

    /**
     * 调用任意 Bot API 方法，返回响应中的 result 字段。
     *
     * @param string $method API 方法名，如 sendMessage
     * @param array<string, mixed> $params 请求参数
     * @return mixed
     */
    public function call(string $method, array $params = []): mixed;

    /**
     * 获取当前 Bot 基本信息（getMe）。
     *
     * @return array<string, mixed>
     */
    public function getMe(): array;

    /**
     * 发送文本消息（sendMessage）。
     *
     * @param array<string, mixed> $params 至少含 chat_id、text
     * @return array<string, mixed> Message
     */
    public function sendMessage(array $params): array;

    /**
     * 编辑消息文本（editMessageText）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> Message
     */
    public function editMessageText(array $params): array;

    /**
     * 删除消息（deleteMessage）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_id
     */
    public function deleteMessage(array $params): bool;

    /**
     * 发送图片（sendPhoto）。
     *
     * photo 可为 file_id、URL、资源流、SplFileInfo 或 ['contents'=>..., 'filename'=>...]
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> Message
     */
    public function sendPhoto(array $params): array;

    /**
     * 发送文档（sendDocument）。
     *
     * document 可为 file_id、URL、资源流、SplFileInfo 或 ['contents'=>..., 'filename'=>...]
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> Message
     */
    public function sendDocument(array $params): array;

    /**
     * 应答回调查询（answerCallbackQuery）。
     *
     * @param array<string, mixed> $params 至少含 callback_query_id
     */
    public function answerCallbackQuery(array $params): bool;

    /**
     * 长轮询拉取更新（getUpdates）。
     *
     * 与 Webhook 模式互斥，同一 Bot 不要同时使用。
     *
     * @param array<string, mixed> $params 可选 offset、timeout、allowed_updates 等
     * @return list<array<string, mixed>>
     */
    public function getUpdates(array $params = []): array;

    /**
     * 设置 Webhook（setWebhook）。
     *
     * 若 Bot 配置了 webhook_secret 且未传 secret_token，会自动附带。
     *
     * @param array<string, mixed> $params 至少含 url
     */
    public function setWebhook(array $params): bool;

    /**
     * 删除 Webhook（deleteWebhook）。
     *
     * @param array<string, mixed> $params 可选 drop_pending_updates
     */
    public function deleteWebhook(array $params = []): bool;

    /**
     * 查询当前 Webhook 状态（getWebhookInfo）。
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array;

    /**
     * 获取文件信息（getFile），可用于拼下载路径。
     *
     * @param array<string, mixed> $params 含 file_id
     * @return array<string, mixed>
     */
    public function getFile(array $params): array;

    /**
     * 导出/重置群主邀请链接（exportChatInviteLink）。
     *
     * 会吊销旧的主链接。Bot 需具备 can_invite_users。
     *
     * @param array{chat_id: int|string} $params
     * @return string 邀请链接 URL
     */
    public function exportChatInviteLink(array $params): string;

    /**
     * 创建附加邀请链接（createChatInviteLink）。
     *
     * 可限时、限人数、需管理员审批（creates_join_request）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> ChatInviteLink
     */
    public function createChatInviteLink(array $params): array;

    /**
     * 编辑附加邀请链接（editChatInviteLink）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> ChatInviteLink
     */
    public function editChatInviteLink(array $params): array;

    /**
     * 吊销邀请链接（revokeChatInviteLink）。
     *
     * @param array<string, mixed> $params 含 chat_id、invite_link
     * @return array<string, mixed> ChatInviteLink
     */
    public function revokeChatInviteLink(array $params): array;

    /**
     * 批准加群申请（approveChatJoinRequest）。
     *
     * @param array{chat_id: int|string, user_id: int} $params
     */
    public function approveChatJoinRequest(array $params): bool;

    /**
     * 拒绝加群申请（declineChatJoinRequest）。
     *
     * @param array{chat_id: int|string, user_id: int} $params
     */
    public function declineChatJoinRequest(array $params): bool;

    /**
     * 封禁群成员（banChatMember）。
     *
     * @param array<string, mixed> $params 含 chat_id、user_id
     */
    public function banChatMember(array $params): bool;

    /**
     * 解封群成员（unbanChatMember）。
     *
     * @param array<string, mixed> $params 含 chat_id、user_id
     */
    public function unbanChatMember(array $params): bool;

    /**
     * 查询单个群成员（getChatMember）。
     *
     * @param array{chat_id: int|string, user_id: int} $params
     * @return array<string, mixed> ChatMember
     */
    public function getChatMember(array $params): array;

    /**
     * 获取群管理员列表（getChatAdministrators）。
     *
     * Bot API 唯一可批量拿到的成员列表。
     *
     * @param array{chat_id: int|string} $params
     * @return list<array<string, mixed>> ChatMember[]
     */
    public function getChatAdministrators(array $params): array;

    /**
     * 获取群人数（getChatMemberCount）。
     *
     * @param array{chat_id: int|string} $params
     */
    public function getChatMemberCount(array $params): int;

    /**
     * 获取群/频道资料（getChat）。
     *
     * @param array{chat_id: int|string} $params
     * @return array<string, mixed> Chat
     */
    public function getChat(array $params): array;

    /**
     * 便捷查询指定群成员（封装 getChatMember）。
     *
     * @return array<string, mixed> ChatMember
     */
    public function getGroupMember(int|string $chatId, int $userId): array;

    /**
     * 便捷获取群管理员列表（封装 getChatAdministrators）。
     *
     * @return list<array<string, mixed>> ChatMember[]
     */
    public function getGroupAdmins(int|string $chatId): array;

    /**
     * 便捷获取群人数（封装 getChatMemberCount）。
     */
    public function getGroupMemberCount(int|string $chatId): int;

    /**
     * 按 user_id 列表批量查询群成员（顺序请求，协程安全）。
     *
     * Telegram Bot API **不支持**一次拉取全部群成员，只能查已知 user_id，
     * 或通过 chat_member 更新自行落库后再查。
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>|null> user_id => ChatMember|null（不在群内/失败为 null）
     */
    public function getGroupMembers(int|string $chatId, array $userIds): array;

    /**
     * 群用户概览：人数 + 管理员列表（非全量成员）。
     *
     * @return array{chat_id: int|string, count: int, administrators: list<array<string, mixed>>}
     */
    public function getGroupUsers(int|string $chatId): array;

    /**
     * 便捷创建邀请链接（封装 createChatInviteLink）。
     *
     * Bot 须为群管理员且具备 can_invite_users。
     * Telegram Bot 无法直接把用户拉进群，需通过邀请链接或审批加群申请。
     *
     * @param int|string $chatId
     * @param array{
     *     name?: string,
     *     expire_date?: int,
     *     member_limit?: int,
     *     creates_join_request?: bool
     * } $options
     * @return array<string, mixed> ChatInviteLink
     */
    public function inviteToChat(int|string $chatId, array $options = []): array;

    /**
     * 将 Webhook / getUpdates 的原始 payload 解析为 Update 对象。
     *
     * @param array<string, mixed>|string $payload JSON 字符串或已解码数组
     */
    public function parseUpdate(array|string $payload): Update;
}
