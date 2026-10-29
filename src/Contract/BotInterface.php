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
     * 常用可选参数：disable_notification（静音，无声音提醒）、parse_mode、
     * reply_markup、reply_parameters、protect_content 等，原样传给 Telegram。
     *
     * @param array<string, mixed> $params 至少含 chat_id、text；可选 disable_notification 等
     * @return array<string, mixed> Message
     */
    public function sendMessage(array $params): array;

    /**
     * 编辑消息文本（editMessageText）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|bool Message；inline 消息可能为 true
     */
    public function editMessageText(array $params): array|bool;

    /**
     * 编辑消息说明（editMessageCaption）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|bool
     */
    public function editMessageCaption(array $params): array|bool;

    /**
     * 编辑消息媒体（editMessageMedia）。
     *
     * @param array<string, mixed> $params 含 media（InputMedia）
     * @return array<string, mixed>|bool
     */
    public function editMessageMedia(array $params): array|bool;

    /**
     * 仅编辑消息键盘（editMessageReplyMarkup）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|bool
     */
    public function editMessageReplyMarkup(array $params): array|bool;

    /**
     * 删除消息（deleteMessage）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_id
     */
    public function deleteMessage(array $params): bool;

    /**
     * 批量删除消息（deleteMessages）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_ids（1～100）
     */
    public function deleteMessages(array $params): bool;

    /**
     * 转发单条消息（forwardMessage）。
     *
     * @param array<string, mixed> $params 含 chat_id、from_chat_id、message_id
     * @return array<string, mixed> Message
     */
    public function forwardMessage(array $params): array;

    /**
     * 批量转发消息（forwardMessages）。
     *
     * @param array<string, mixed> $params 含 chat_id、from_chat_id、message_ids
     * @return list<array<string, mixed>> MessageId[]
     */
    public function forwardMessages(array $params): array;

    /**
     * 复制单条消息（copyMessage），不显示“转发自”。
     *
     * @param array<string, mixed> $params 含 chat_id、from_chat_id、message_id
     * @return array<string, mixed> MessageId
     */
    public function copyMessage(array $params): array;

    /**
     * 批量复制消息（copyMessages）。
     *
     * @param array<string, mixed> $params 含 chat_id、from_chat_id、message_ids
     * @return list<array<string, mixed>> MessageId[]
     */
    public function copyMessages(array $params): array;

    /**
     * 发送图片（sendPhoto）。
     *
     * photo 可为 file_id、远程 URL（http/https）、资源流、SplFileInfo
     * 或 ['contents'=>..., 'filename'=>...]；可选 caption、disable_notification 等。
     *
     * @param array<string, mixed> $params 至少含 chat_id、photo
     * @return array<string, mixed> Message
     */
    public function sendPhoto(array $params): array;

    /**
     * 发送视频（sendVideo）。
     *
     * video 可为 file_id、远程 URL、资源流、SplFileInfo 或 ['contents'=>..., 'filename'=>...]
     * 可选 caption、duration、width、height、thumbnail、supports_streaming、
     * disable_notification、parse_mode、reply_markup 等。
     *
     * @param array<string, mixed> $params 至少含 chat_id、video
     * @return array<string, mixed> Message
     */
    public function sendVideo(array $params): array;

    /**
     * 发送媒体组（sendMediaGroup），2～10 条，用于一次发送多图/多视频等。
     *
     * media 为 InputMedia* 数组；远程地址可直接写 URL。本地上传用 attach://，
     * 或用 sendPhotos / sendVideos 便捷方法。
     *
     * @param array<string, mixed> $params 至少含 chat_id、media
     * @return list<array<string, mixed>> Message[]
     */
    public function sendMediaGroup(array $params): array;

    /**
     * 便捷发送多张图片（1 张走 sendPhoto，2～10 张走 sendMediaGroup）。
     *
     * $photos 每项可为：
     * - 远程 URL / file_id 字符串
     * - 资源流、SplFileInfo、['contents'=>..., 'filename'=>...]
     * - ['media' => 上述任意, 'caption' => '...', 'parse_mode' => ...]
     *
     * @param int|string $chatId
     * @param list<mixed> $photos
     * @param array<string, mixed> $options 透传给 API（如 disable_notification）；单图时 caption 也可放这里
     * @return list<array<string, mixed>> Message[]
     */
    public function sendPhotos(int|string $chatId, array $photos, array $options = []): array;

    /**
     * 便捷发送多个视频（1 个走 sendVideo，2～10 个走 sendMediaGroup）。
     *
     * $videos 每项可为：
     * - 远程 URL / file_id 字符串
     * - 资源流、SplFileInfo、['contents'=>..., 'filename'=>...]
     * - ['media' => 上述任意, 'caption' => '...', 'supports_streaming' => true, ...]
     *
     * @param int|string $chatId
     * @param list<mixed> $videos
     * @param array<string, mixed> $options 透传给 API（如 disable_notification）；单视频时 caption 等也可放这里
     * @return list<array<string, mixed>> Message[]
     */
    public function sendVideos(int|string $chatId, array $videos, array $options = []): array;

    /**
     * 发送文档（sendDocument）。
     *
     * document 可为 file_id、远程 URL（http/https）、资源流、SplFileInfo
     * 或 ['contents'=>..., 'filename'=>...]；可选 caption、disable_notification、
     * thumbnail、parse_mode 等。
     *
     * @param array<string, mixed> $params 至少含 chat_id、document
     * @return array<string, mixed> Message
     */
    public function sendDocument(array $params): array;

    /**
     * 发送动画 GIF（sendAnimation）。
     *
     * @param array<string, mixed> $params 至少含 chat_id、animation
     * @return array<string, mixed> Message
     */
    public function sendAnimation(array $params): array;

    /**
     * 发送音频（sendAudio），客户端以音乐播放器展示；建议 MP3/M4A。
     *
     * @param array<string, mixed> $params 至少含 chat_id、audio
     * @return array<string, mixed> Message
     */
    public function sendAudio(array $params): array;

    /**
     * 发送语音消息（sendVoice），建议 OGG/OPUS。
     *
     * @param array<string, mixed> $params 至少含 chat_id、voice
     * @return array<string, mixed> Message
     */
    public function sendVoice(array $params): array;

    /**
     * 发送圆形视频留言（sendVideoNote）。
     *
     * @param array<string, mixed> $params 至少含 chat_id、video_note
     * @return array<string, mixed> Message
     */
    public function sendVideoNote(array $params): array;

    /**
     * 发送贴纸（sendSticker）。
     *
     * @param array<string, mixed> $params 至少含 chat_id、sticker
     * @return array<string, mixed> Message
     */
    public function sendSticker(array $params): array;

    /**
     * 发送地理位置（sendLocation）。
     *
     * @param array<string, mixed> $params 含 chat_id、latitude、longitude
     * @return array<string, mixed> Message
     */
    public function sendLocation(array $params): array;

    /**
     * 发送地点/场馆（sendVenue）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> Message
     */
    public function sendVenue(array $params): array;

    /**
     * 发送联系人（sendContact）。
     *
     * @param array<string, mixed> $params 含 chat_id、phone_number、first_name
     * @return array<string, mixed> Message
     */
    public function sendContact(array $params): array;

    /**
     * 发送投票（sendPoll）。
     *
     * @param array<string, mixed> $params 含 chat_id、question、options
     * @return array<string, mixed> Message
     */
    public function sendPoll(array $params): array;

    /**
     * 停止投票（stopPoll）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_id
     * @return array<string, mixed> Poll
     */
    public function stopPoll(array $params): array;

    /**
     * 发送骰子/动画表情（sendDice）。
     *
     * @param array<string, mixed> $params 至少含 chat_id；可选 emoji
     * @return array<string, mixed> Message
     */
    public function sendDice(array $params): array;

    /**
     * 发送聊天动作（sendChatAction），如 typing、upload_photo。
     *
     * @param array<string, mixed> $params 含 chat_id、action
     */
    public function sendChatAction(array $params): bool;

    /**
     * 设置消息表情回应（setMessageReaction）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_id；可选 reaction
     */
    public function setMessageReaction(array $params): bool;

    /**
     * 获取用户头像列表（getUserProfilePhotos）。
     *
     * @param array<string, mixed> $params 含 user_id
     * @return array<string, mixed> UserProfilePhotos
     */
    public function getUserProfilePhotos(array $params): array;

    /**
     * 应答回调查询（answerCallbackQuery）。
     *
     * @param array<string, mixed> $params 至少含 callback_query_id
     */
    public function answerCallbackQuery(array $params): bool;

    /**
     * 应答 Inline 查询（answerInlineQuery）。
     *
     * @param array<string, mixed> $params 含 inline_query_id、results
     */
    public function answerInlineQuery(array $params): bool;

    /**
     * 应答 Web App 查询并代发消息（answerWebAppQuery）。
     *
     * @param array<string, mixed> $params 含 web_app_query_id、result
     * @return array<string, mixed> SentWebAppMessage
     */
    public function answerWebAppQuery(array $params): array;

    /**
     * 设置输入框旁菜单按钮（setChatMenuButton）。
     *
     * menu_button.type 可为 commands / web_app / default。
     *
     * @param array<string, mixed> $params 可选 chat_id、menu_button
     */
    public function setChatMenuButton(array $params): bool;

    /**
     * 查询菜单按钮（getChatMenuButton）。
     *
     * @param array<string, mixed> $params 可选 chat_id
     * @return array<string, mixed> MenuButton
     */
    public function getChatMenuButton(array $params = []): array;

    /**
     * 添加或修改 Mini App 菜单按钮（web_app）。
     *
     * Telegram 无独立 create/update：再次调用即覆盖文案与 URL。
     *
     * @param string $text 按钮文案
     * @param string $url Mini App HTTPS URL
     * @param int|string|null $chatId 指定会话；null 表示 Bot 默认菜单
     */
    public function setWebAppMenuButton(string $text, string $url, int|string|null $chatId = null): bool;

    /**
     * 删除 Mini App 菜单按钮（恢复为 default）。
     *
     * 等价于 resetChatMenuButton；Telegram 没有单独的 deleteChatMenuButton。
     *
     * @param int|string|null $chatId 指定会话；null 表示 Bot 默认菜单
     */
    public function removeWebAppMenuButton(int|string|null $chatId = null): bool;

    /**
     * 查询当前是否为 web_app 菜单；是则返回 text/url，否则 null。
     *
     * @param int|string|null $chatId 指定会话；null 表示 Bot 默认菜单
     * @return array{text: string, url: string}|null
     */
    public function getWebAppMenuButton(int|string|null $chatId = null): ?array;

    /**
     * 便捷：将菜单按钮设为命令列表（commands）。
     *
     * @param int|string|null $chatId 指定会话；null 表示 Bot 默认菜单
     */
    public function setCommandsMenuButton(int|string|null $chatId = null): bool;

    /**
     * 便捷：恢复默认菜单按钮（default）。
     *
     * @param int|string|null $chatId 指定会话；null 表示 Bot 默认菜单
     */
    public function resetChatMenuButton(int|string|null $chatId = null): bool;

    /**
     * 设置 Bot 命令列表（setMyCommands）。
     *
     * 常与 setCommandsMenuButton 配合；点菜单展开这些命令。
     *
     * @param array<string, mixed> $params 含 commands；可选 scope、language_code
     */
    public function setMyCommands(array $params): bool;

    /**
     * 查询 Bot 命令列表（getMyCommands）。
     *
     * @param array<string, mixed> $params 可选 scope、language_code
     * @return list<array<string, mixed>> BotCommand[]
     */
    public function getMyCommands(array $params = []): array;

    /**
     * 删除 Bot 命令列表（deleteMyCommands）。
     *
     * @param array<string, mixed> $params 可选 scope、language_code
     */
    public function deleteMyCommands(array $params = []): bool;

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
     * 限制群成员权限（restrictChatMember）。
     *
     * @param array<string, mixed> $params 含 chat_id、user_id、permissions
     */
    public function restrictChatMember(array $params): bool;

    /**
     * 提升/降级管理员（promoteChatMember）。
     *
     * @param array<string, mixed> $params 含 chat_id、user_id
     */
    public function promoteChatMember(array $params): bool;

    /**
     * 设置管理员自定义头衔（setChatAdministratorCustomTitle）。
     *
     * @param array<string, mixed> $params 含 chat_id、user_id、custom_title
     */
    public function setChatAdministratorCustomTitle(array $params): bool;

    /**
     * 设置群默认权限（setChatPermissions）。
     *
     * @param array<string, mixed> $params 含 chat_id、permissions
     */
    public function setChatPermissions(array $params): bool;

    /**
     * 封禁频道身份发言（banChatSenderChat）。
     *
     * @param array<string, mixed> $params 含 chat_id、sender_chat_id
     */
    public function banChatSenderChat(array $params): bool;

    /**
     * 解封频道身份（unbanChatSenderChat）。
     *
     * @param array<string, mixed> $params 含 chat_id、sender_chat_id
     */
    public function unbanChatSenderChat(array $params): bool;

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
     * 设置群头像（setChatPhoto）。
     *
     * @param array<string, mixed> $params 含 chat_id、photo
     */
    public function setChatPhoto(array $params): bool;

    /**
     * 删除群头像（deleteChatPhoto）。
     *
     * @param array{chat_id: int|string} $params
     */
    public function deleteChatPhoto(array $params): bool;

    /**
     * 设置群标题（setChatTitle）。
     *
     * @param array{chat_id: int|string, title: string} $params
     */
    public function setChatTitle(array $params): bool;

    /**
     * 设置群简介（setChatDescription）。
     *
     * @param array<string, mixed> $params 含 chat_id；可选 description
     */
    public function setChatDescription(array $params): bool;

    /**
     * 置顶消息（pinChatMessage）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_id
     */
    public function pinChatMessage(array $params): bool;

    /**
     * 取消置顶（unpinChatMessage）。
     *
     * @param array<string, mixed> $params 含 chat_id；可选 message_id
     */
    public function unpinChatMessage(array $params): bool;

    /**
     * 取消全部置顶（unpinAllChatMessages）。
     *
     * @param array{chat_id: int|string} $params
     */
    public function unpinAllChatMessages(array $params): bool;

    /**
     * Bot 退出群/频道（leaveChat）。
     *
     * @param array{chat_id: int|string} $params
     */
    public function leaveChat(array $params): bool;

    /**
     * 创建论坛话题（createForumTopic）。
     *
     * @param array<string, mixed> $params 含 chat_id、name
     * @return array<string, mixed> ForumTopic
     */
    public function createForumTopic(array $params): array;

    /**
     * 编辑论坛话题（editForumTopic）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_thread_id
     */
    public function editForumTopic(array $params): bool;

    /**
     * 关闭论坛话题（closeForumTopic）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_thread_id
     */
    public function closeForumTopic(array $params): bool;

    /**
     * 重新打开论坛话题（reopenForumTopic）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_thread_id
     */
    public function reopenForumTopic(array $params): bool;

    /**
     * 删除论坛话题（deleteForumTopic）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_thread_id
     */
    public function deleteForumTopic(array $params): bool;

    /**
     * 清除话题内置顶（unpinAllForumTopicMessages）。
     *
     * @param array<string, mixed> $params 含 chat_id、message_thread_id
     */
    public function unpinAllForumTopicMessages(array $params): bool;

    /**
     * 获取可用作话题图标的自定义 emoji 贴纸（getForumTopicIconStickers）。
     *
     * @return list<array<string, mixed>> Sticker[]
     */
    public function getForumTopicIconStickers(): array;

    /**
     * 设置 Bot 名称（setMyName）。
     *
     * @param array<string, mixed> $params 可选 name、language_code
     */
    public function setMyName(array $params): bool;

    /**
     * 获取 Bot 名称（getMyName）。
     *
     * @param array<string, mixed> $params 可选 language_code
     * @return array<string, mixed> BotName
     */
    public function getMyName(array $params = []): array;

    /**
     * 设置 Bot 简介（setMyDescription）。
     *
     * @param array<string, mixed> $params 可选 description、language_code
     */
    public function setMyDescription(array $params): bool;

    /**
     * 获取 Bot 简介（getMyDescription）。
     *
     * @param array<string, mixed> $params 可选 language_code
     * @return array<string, mixed> BotDescription
     */
    public function getMyDescription(array $params = []): array;

    /**
     * 设置 Bot 短简介（setMyShortDescription）。
     *
     * @param array<string, mixed> $params 可选 short_description、language_code
     */
    public function setMyShortDescription(array $params): bool;

    /**
     * 获取 Bot 短简介（getMyShortDescription）。
     *
     * @param array<string, mixed> $params 可选 language_code
     * @return array<string, mixed> BotShortDescription
     */
    public function getMyShortDescription(array $params = []): array;

    /**
     * 设置默认管理员权限（setMyDefaultAdministratorRights）。
     *
     * @param array<string, mixed> $params 可选 rights、for_channels
     */
    public function setMyDefaultAdministratorRights(array $params = []): bool;

    /**
     * 获取默认管理员权限（getMyDefaultAdministratorRights）。
     *
     * @param array<string, mixed> $params 可选 for_channels
     * @return array<string, mixed> ChatAdministratorRights
     */
    public function getMyDefaultAdministratorRights(array $params = []): array;

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
     * 获取单个群资料概览（getChat + 人数 + 管理员）。
     *
     * @return array{
     *     chat_id: int|string,
     *     chat: array<string, mixed>,
     *     count: int,
     *     administrators: list<array<string, mixed>>
     * }
     */
    public function getGroupInfo(int|string $chatId): array;

    /**
     * 按已知 chat_id 列表批量获取群资料。
     *
     * Telegram **不能**列出 Bot 所在全部群；需自行落库后再传入 id。
     * 单个查询失败时对应值为 null，不中断整批。
     *
     * @param list<int|string> $chatIds
     * @return array<string, array{
     *     chat_id: int|string,
     *     chat: array<string, mixed>,
     *     count: int,
     *     administrators: list<array<string, mixed>>
     * }|null> chat_id 字符串键 => 资料|null
     */
    public function getGroupsInfo(array $chatIds): array;

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
