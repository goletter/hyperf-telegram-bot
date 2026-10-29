<?php

declare(strict_types=1);

namespace Goletter\Telegram;

use Goletter\Telegram\Contract\BotInterface;
use Goletter\Telegram\Exceptions\TelegramApiException;
use Goletter\Telegram\Http\TelegramHttpClient;
use Goletter\Telegram\Update\Update;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

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
     * @param bool $logRequest 是否记录 API 请求耗时（telegram.http.log_request）
     * @param LoggerInterface|null $logger 耗时日志输出；未注入时即使开启也不写
     */
    public function __construct(
        protected string $token,
        ClientInterface $client,
        protected string $name = 'default',
        string $baseUri = 'https://api.telegram.org',
        protected string $webhookSecret = '',
        bool $logRequest = false,
        ?LoggerInterface $logger = null
    ) {
        $this->token = self::normalizeToken($token);
        $this->http = new TelegramHttpClient(
            $client,
            $this->token,
            $baseUri,
            $this->name,
            $logRequest,
            $logger
        );
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
     *
     * 静音发送示例：`'disable_notification' => true`（接收方无声音/震动提醒）。
     */
    public function sendMessage(array $params): array
    {
        return (array) $this->call('sendMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editMessageText(array $params): array|bool
    {
        return $this->callMessageOrTrue('editMessageText', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editMessageCaption(array $params): array|bool
    {
        return $this->callMessageOrTrue('editMessageCaption', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editMessageMedia(array $params): array|bool
    {
        return $this->callMessageOrTrue('editMessageMedia', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editMessageReplyMarkup(array $params): array|bool
    {
        return $this->callMessageOrTrue('editMessageReplyMarkup', $params);
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
    public function deleteMessages(array $params): bool
    {
        return (bool) $this->call('deleteMessages', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function forwardMessage(array $params): array
    {
        return (array) $this->call('forwardMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function forwardMessages(array $params): array
    {
        $result = $this->call('forwardMessages', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     */
    public function copyMessage(array $params): array
    {
        return (array) $this->call('copyMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function copyMessages(array $params): array
    {
        $result = $this->call('copyMessages', $params);

        return is_array($result) ? $result : [];
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
    public function sendVideo(array $params): array
    {
        return (array) $this->call('sendVideo', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendMediaGroup(array $params): array
    {
        $result = $this->call('sendMediaGroup', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     *
     * @throws TelegramApiException 图片列表为空或超过 10 张时
     */
    public function sendPhotos(int|string $chatId, array $photos, array $options = []): array
    {
        return $this->sendTypedMediaList($chatId, $photos, $options, 'photo');
    }

    /**
     * {@inheritdoc}
     *
     * @throws TelegramApiException 视频列表为空或超过 10 个时
     */
    public function sendVideos(int|string $chatId, array $videos, array $options = []): array
    {
        return $this->sendTypedMediaList($chatId, $videos, $options, 'video');
    }

    /**
     * {@inheritdoc}
     *
     * 远程地址示例：`'document' => 'https://example.com/a.pdf'`。
     */
    public function sendDocument(array $params): array
    {
        return (array) $this->call('sendDocument', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAnimation(array $params): array
    {
        return (array) $this->call('sendAnimation', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAudio(array $params): array
    {
        return (array) $this->call('sendAudio', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendVoice(array $params): array
    {
        return (array) $this->call('sendVoice', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendVideoNote(array $params): array
    {
        return (array) $this->call('sendVideoNote', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendSticker(array $params): array
    {
        return (array) $this->call('sendSticker', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendLocation(array $params): array
    {
        return (array) $this->call('sendLocation', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendVenue(array $params): array
    {
        return (array) $this->call('sendVenue', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendContact(array $params): array
    {
        return (array) $this->call('sendContact', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendPoll(array $params): array
    {
        return (array) $this->call('sendPoll', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function stopPoll(array $params): array
    {
        return (array) $this->call('stopPoll', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendDice(array $params): array
    {
        return (array) $this->call('sendDice', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function sendChatAction(array $params): bool
    {
        return (bool) $this->call('sendChatAction', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setMessageReaction(array $params): bool
    {
        return (bool) $this->call('setMessageReaction', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfilePhotos(array $params): array
    {
        return (array) $this->call('getUserProfilePhotos', $params);
    }

    /**
     * 按类型发送 1～10 条同类媒体（photo / video）。
     *
     * @param list<mixed> $items
     * @param array<string, mixed> $options
     * @param 'photo'|'video' $type
     * @return list<array<string, mixed>>
     * @throws TelegramApiException
     */
    protected function sendTypedMediaList(int|string $chatId, array $items, array $options, string $type): array
    {
        $label = $type === 'video' ? 'sendVideos' : 'sendPhotos';
        $count = count($items);
        if ($count === 0) {
            throw new TelegramApiException("{$label}: list must not be empty.");
        }
        if ($count > 10) {
            throw new TelegramApiException("{$label}: at most 10 items are allowed.");
        }

        if ($count === 1) {
            $item = $items[0];
            $params = $options;
            $params['chat_id'] = $chatId;
            $field = $type; // photo | video

            if (is_array($item) && ! $this->isUploadValue($item) && array_key_exists('media', $item)) {
                $params[$field] = $item['media'];
                foreach ($this->mediaItemOptionKeys($type) as $key) {
                    if (array_key_exists($key, $item) && ! array_key_exists($key, $params)) {
                        $params[$key] = $item[$key];
                    }
                }
            } else {
                $params[$field] = $item;
            }

            return [$type === 'video' ? $this->sendVideo($params) : $this->sendPhoto($params)];
        }

        return $this->sendMediaGroup($this->buildMediaGroupParams($chatId, $items, $options, $type));
    }

    /**
     * 组装 sendMediaGroup 参数（远程 URL 直传，本地文件用 attach://）。
     *
     * @param list<mixed> $items
     * @param array<string, mixed> $options
     * @param 'photo'|'video' $type
     * @return array<string, mixed>
     */
    protected function buildMediaGroupParams(int|string $chatId, array $items, array $options, string $type): array
    {
        $params = $options;
        unset($params['media'], $params['photo'], $params['video']);
        $params['chat_id'] = $chatId;

        $media = [];
        $attachIndex = 0;

        foreach ($items as $item) {
            $file = $item;
            $entry = ['type' => $type];
            $meta = [];

            if (is_array($item) && ! $this->isUploadValue($item) && array_key_exists('media', $item)) {
                $file = $item['media'];
                foreach ($this->mediaItemOptionKeys($type) as $key) {
                    if (array_key_exists($key, $item)) {
                        $meta[$key] = $item[$key];
                    }
                }
            }

            if ($this->isUploadValue($file)) {
                $attachName = $type . '_' . $attachIndex++;
                $entry['media'] = 'attach://' . $attachName;
                $params[$attachName] = $file;
            } else {
                $entry['media'] = is_string($file) ? $file : (string) $file;
            }

            if (isset($meta['thumbnail']) && $this->isUploadValue($meta['thumbnail'])) {
                $thumbName = 'thumb_' . $attachIndex++;
                $entry['thumbnail'] = 'attach://' . $thumbName;
                $params[$thumbName] = $meta['thumbnail'];
                unset($meta['thumbnail']);
            }

            $media[] = $entry + $meta;
        }

        $params['media'] = $media;

        return $params;
    }

    /**
     * InputMedia / 单发时可从条目透传的字段。
     *
     * @param 'photo'|'video' $type
     * @return list<string>
     */
    protected function mediaItemOptionKeys(string $type): array
    {
        $common = ['caption', 'parse_mode', 'caption_entities', 'show_caption_above_media', 'has_spoiler'];
        if ($type === 'video') {
            return [...$common, 'thumbnail', 'width', 'height', 'duration', 'supports_streaming'];
        }

        return $common;
    }

    /**
     * 是否为需 multipart 上传的本地内容。
     */
    protected function isUploadValue(mixed $value): bool
    {
        if (is_resource($value) || $value instanceof \SplFileInfo) {
            return true;
        }

        return is_array($value)
            && isset($value['contents'])
            && (is_resource($value['contents']) || is_string($value['contents']));
    }

    /**
     * 调用可能返回 Message 或 true 的编辑类 API。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|bool
     */
    protected function callMessageOrTrue(string $method, array $params): array|bool
    {
        $result = $this->call($method, $params);

        return is_array($result) ? $result : (bool) $result;
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
    public function answerInlineQuery(array $params): bool
    {
        return (bool) $this->call('answerInlineQuery', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function answerWebAppQuery(array $params): array
    {
        return (array) $this->call('answerWebAppQuery', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setChatMenuButton(array $params): bool
    {
        return (bool) $this->call('setChatMenuButton', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getChatMenuButton(array $params = []): array
    {
        return (array) $this->call('getChatMenuButton', $params);
    }

    /**
     * {@inheritdoc}
     *
     * 添加与修改共用此方法：再次调用会覆盖。
     */
    public function setWebAppMenuButton(string $text, string $url, int|string|null $chatId = null): bool
    {
        $params = [
            'menu_button' => [
                'type' => 'web_app',
                'text' => $text,
                'web_app' => ['url' => $url],
            ],
        ];
        if ($chatId !== null) {
            $params['chat_id'] = $chatId;
        }

        return $this->setChatMenuButton($params);
    }

    /**
     * {@inheritdoc}
     */
    public function removeWebAppMenuButton(int|string|null $chatId = null): bool
    {
        return $this->resetChatMenuButton($chatId);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebAppMenuButton(int|string|null $chatId = null): ?array
    {
        $params = [];
        if ($chatId !== null) {
            $params['chat_id'] = $chatId;
        }

        $button = $this->getChatMenuButton($params);
        if (($button['type'] ?? null) !== 'web_app') {
            return null;
        }

        $text = $button['text'] ?? null;
        $url = $button['web_app']['url'] ?? null;
        if (! is_string($text) || ! is_string($url) || $text === '' || $url === '') {
            return null;
        }

        return [
            'text' => $text,
            'url' => $url,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setCommandsMenuButton(int|string|null $chatId = null): bool
    {
        $params = [
            'menu_button' => ['type' => 'commands'],
        ];
        if ($chatId !== null) {
            $params['chat_id'] = $chatId;
        }

        return $this->setChatMenuButton($params);
    }

    /**
     * {@inheritdoc}
     */
    public function resetChatMenuButton(int|string|null $chatId = null): bool
    {
        $params = [
            'menu_button' => ['type' => 'default'],
        ];
        if ($chatId !== null) {
            $params['chat_id'] = $chatId;
        }

        return $this->setChatMenuButton($params);
    }

    /**
     * {@inheritdoc}
     */
    public function setMyCommands(array $params): bool
    {
        return (bool) $this->call('setMyCommands', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMyCommands(array $params = []): array
    {
        $result = $this->call('getMyCommands', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMyCommands(array $params = []): bool
    {
        return (bool) $this->call('deleteMyCommands', $params);
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
    public function restrictChatMember(array $params): bool
    {
        return (bool) $this->call('restrictChatMember', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function promoteChatMember(array $params): bool
    {
        return (bool) $this->call('promoteChatMember', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setChatAdministratorCustomTitle(array $params): bool
    {
        return (bool) $this->call('setChatAdministratorCustomTitle', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setChatPermissions(array $params): bool
    {
        return (bool) $this->call('setChatPermissions', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function banChatSenderChat(array $params): bool
    {
        return (bool) $this->call('banChatSenderChat', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function unbanChatSenderChat(array $params): bool
    {
        return (bool) $this->call('unbanChatSenderChat', $params);
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
    public function setChatPhoto(array $params): bool
    {
        return (bool) $this->call('setChatPhoto', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteChatPhoto(array $params): bool
    {
        return (bool) $this->call('deleteChatPhoto', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setChatTitle(array $params): bool
    {
        return (bool) $this->call('setChatTitle', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setChatDescription(array $params): bool
    {
        return (bool) $this->call('setChatDescription', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function pinChatMessage(array $params): bool
    {
        return (bool) $this->call('pinChatMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function unpinChatMessage(array $params): bool
    {
        return (bool) $this->call('unpinChatMessage', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function unpinAllChatMessages(array $params): bool
    {
        return (bool) $this->call('unpinAllChatMessages', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function leaveChat(array $params): bool
    {
        return (bool) $this->call('leaveChat', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function createForumTopic(array $params): array
    {
        return (array) $this->call('createForumTopic', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function editForumTopic(array $params): bool
    {
        return (bool) $this->call('editForumTopic', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function closeForumTopic(array $params): bool
    {
        return (bool) $this->call('closeForumTopic', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function reopenForumTopic(array $params): bool
    {
        return (bool) $this->call('reopenForumTopic', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteForumTopic(array $params): bool
    {
        return (bool) $this->call('deleteForumTopic', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function unpinAllForumTopicMessages(array $params): bool
    {
        return (bool) $this->call('unpinAllForumTopicMessages', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getForumTopicIconStickers(): array
    {
        $result = $this->call('getForumTopicIconStickers');

        return is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     */
    public function setMyName(array $params): bool
    {
        return (bool) $this->call('setMyName', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMyName(array $params = []): array
    {
        return (array) $this->call('getMyName', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setMyDescription(array $params): bool
    {
        return (bool) $this->call('setMyDescription', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMyDescription(array $params = []): array
    {
        return (array) $this->call('getMyDescription', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setMyShortDescription(array $params): bool
    {
        return (bool) $this->call('setMyShortDescription', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMyShortDescription(array $params = []): array
    {
        return (array) $this->call('getMyShortDescription', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function setMyDefaultAdministratorRights(array $params = []): bool
    {
        return (bool) $this->call('setMyDefaultAdministratorRights', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function getMyDefaultAdministratorRights(array $params = []): array
    {
        return (array) $this->call('getMyDefaultAdministratorRights', $params);
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
     */
    public function getGroupInfo(int|string $chatId): array
    {
        return [
            'chat_id' => $chatId,
            'chat' => $this->getChat(['chat_id' => $chatId]),
            'count' => $this->getGroupMemberCount($chatId),
            'administrators' => $this->getGroupAdmins($chatId),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 单个 chat_id 查询失败时对应值为 null，不中断整批。
     */
    public function getGroupsInfo(array $chatIds): array
    {
        $groups = [];

        foreach ($chatIds as $chatId) {
            $key = (string) $chatId;
            try {
                $groups[$key] = $this->getGroupInfo($chatId);
            } catch (TelegramApiException) {
                $groups[$key] = null;
            }
        }

        return $groups;
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
