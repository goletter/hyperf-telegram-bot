<?php

declare(strict_types=1);

namespace Goletter\Telegram\Update;

use ArrayAccess;
use JsonSerializable;

/**
 * Telegram Update 轻量封装，支持数组访问与 JSON 序列化。
 *
 * @implements ArrayAccess<string, mixed>
 */
class Update implements ArrayAccess, JsonSerializable
{
    /**
     * @param array<string, mixed> $data 原始 Update 数组
     */
    public function __construct(protected array $data)
    {
    }

    /**
     * 获取 update_id。
     */
    public function getUpdateId(): int
    {
        return (int) ($this->data['update_id'] ?? 0);
    }

    /**
     * 获取消息类更新（message / edited_message / channel_post / edited_channel_post）。
     *
     * @return array<string, mixed>|null Message
     */
    public function getMessage(): ?array
    {
        return $this->firstOf(['message', 'edited_message', 'channel_post', 'edited_channel_post']);
    }

    /**
     * 获取回调查询（callback_query）。
     *
     * @return array<string, mixed>|null
     */
    public function getCallbackQuery(): ?array
    {
        $query = $this->data['callback_query'] ?? null;

        return is_array($query) ? $query : null;
    }

    /**
     * 获取键盘 Web App 回传数据（message.web_app_data）。
     *
     * 来自 ReplyKeyboard 上 type=web_app 的按钮；Menu Button 打开的 Mini App
     * 一般自行调业务 API，不会走此字段。
     *
     * @return array{button_text?: string, data?: string}|null
     */
    public function getWebAppData(): ?array
    {
        $message = $this->getMessage();
        $data = $message['web_app_data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /**
     * 获取 Inline 查询（inline_query）。
     *
     * @return array<string, mixed>|null
     */
    public function getInlineQuery(): ?array
    {
        $query = $this->data['inline_query'] ?? null;

        return is_array($query) ? $query : null;
    }

    /**
     * 获取用户选择的 Inline 结果（chosen_inline_result）。
     *
     * @return array<string, mixed>|null
     */
    public function getChosenInlineResult(): ?array
    {
        $result = $this->data['chosen_inline_result'] ?? null;

        return is_array($result) ? $result : null;
    }

    /**
     * 获取配送地址查询（shipping_query）。
     *
     * @return array<string, mixed>|null
     */
    public function getShippingQuery(): ?array
    {
        $query = $this->data['shipping_query'] ?? null;

        return is_array($query) ? $query : null;
    }

    /**
     * 获取支付预结账查询（pre_checkout_query）。
     *
     * @return array<string, mixed>|null
     */
    public function getPreCheckoutQuery(): ?array
    {
        $query = $this->data['pre_checkout_query'] ?? null;

        return is_array($query) ? $query : null;
    }

    /**
     * 获取投票状态更新（poll）。
     *
     * @return array<string, mixed>|null
     */
    public function getPoll(): ?array
    {
        $poll = $this->data['poll'] ?? null;

        return is_array($poll) ? $poll : null;
    }

    /**
     * 获取用户投票（poll_answer）。
     *
     * @return array<string, mixed>|null
     */
    public function getPollAnswer(): ?array
    {
        $answer = $this->data['poll_answer'] ?? null;

        return is_array($answer) ? $answer : null;
    }

    /**
     * 获取消息表情回应更新（message_reaction）。
     *
     * @return array<string, mixed>|null
     */
    public function getMessageReaction(): ?array
    {
        $reaction = $this->data['message_reaction'] ?? null;

        return is_array($reaction) ? $reaction : null;
    }

    /**
     * 获取消息表情回应计数更新（message_reaction_count）。
     *
     * @return array<string, mixed>|null
     */
    public function getMessageReactionCount(): ?array
    {
        $count = $this->data['message_reaction_count'] ?? null;

        return is_array($count) ? $count : null;
    }

    /**
     * 获取加群申请（chat_join_request）。
     *
     * @return array<string, mixed>|null
     */
    public function getChatJoinRequest(): ?array
    {
        $request = $this->data['chat_join_request'] ?? null;

        return is_array($request) ? $request : null;
    }

    /**
     * 获取成员状态变更（chat_member / my_chat_member）。
     *
     * 进群、退群、权限变化等，可用于自行维护群成员表。
     *
     * @return array<string, mixed>|null ChatMemberUpdated
     */
    public function getChatMemberUpdate(): ?array
    {
        return $this->firstOf(['chat_member', 'my_chat_member']);
    }

    /**
     * 获取 Bot 自身成员状态变更（my_chat_member）。
     *
     * 用于维护「该 Bot 加入了哪些群」：进群 / 退群 / 权限变化。
     *
     * @return array<string, mixed>|null ChatMemberUpdated
     */
    public function getMyChatMember(): ?array
    {
        $value = $this->data['my_chat_member'] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * 是否为 my_chat_member 更新。
     */
    public function isMyChatMemberUpdate(): bool
    {
        return $this->getMyChatMember() !== null;
    }

    /**
     * 从常见 Update 类型中提取 chat 对象。
     *
     * @return array<string, mixed>|null Chat
     */
    public function getChat(): ?array
    {
        $message = $this->getMessage();
        if (isset($message['chat']) && is_array($message['chat'])) {
            return $message['chat'];
        }

        $callback = $this->getCallbackQuery();
        if (isset($callback['message']['chat']) && is_array($callback['message']['chat'])) {
            return $callback['message']['chat'];
        }

        $joinRequest = $this->getChatJoinRequest();
        if (isset($joinRequest['chat']) && is_array($joinRequest['chat'])) {
            return $joinRequest['chat'];
        }

        $memberUpdate = $this->getChatMemberUpdate();
        if (isset($memberUpdate['chat']) && is_array($memberUpdate['chat'])) {
            return $memberUpdate['chat'];
        }

        $reaction = $this->getMessageReaction();
        if (isset($reaction['chat']) && is_array($reaction['chat'])) {
            return $reaction['chat'];
        }

        $reactionCount = $this->getMessageReactionCount();
        if (isset($reactionCount['chat']) && is_array($reactionCount['chat'])) {
            return $reactionCount['chat'];
        }

        $pollAnswer = $this->getPollAnswer();
        if (isset($pollAnswer['voter_chat']) && is_array($pollAnswer['voter_chat'])) {
            return $pollAnswer['voter_chat'];
        }

        return null;
    }

    /**
     * 获取会话 ID（私聊为正、群/超级群通常为负）。
     */
    public function getChatId(): int|string|null
    {
        $chat = $this->getChat();

        return $chat['id'] ?? null;
    }

    /**
     * 获取触发更新的用户（from）。
     *
     * @return array<string, mixed>|null User
     */
    public function getFrom(): ?array
    {
        $message = $this->getMessage();
        if (isset($message['from']) && is_array($message['from'])) {
            return $message['from'];
        }

        $callback = $this->getCallbackQuery();
        if (isset($callback['from']) && is_array($callback['from'])) {
            return $callback['from'];
        }

        $joinRequest = $this->getChatJoinRequest();
        if (isset($joinRequest['from']) && is_array($joinRequest['from'])) {
            return $joinRequest['from'];
        }

        $memberUpdate = $this->getChatMemberUpdate();
        if (isset($memberUpdate['from']) && is_array($memberUpdate['from'])) {
            return $memberUpdate['from'];
        }
        if (isset($memberUpdate['new_chat_member']['user']) && is_array($memberUpdate['new_chat_member']['user'])) {
            return $memberUpdate['new_chat_member']['user'];
        }

        foreach ([
            $this->getInlineQuery(),
            $this->getChosenInlineResult(),
            $this->getShippingQuery(),
            $this->getPreCheckoutQuery(),
            $this->getPollAnswer(),
            $this->getMessageReaction(),
        ] as $payload) {
            if (isset($payload['from']) && is_array($payload['from'])) {
                return $payload['from'];
            }
            if (isset($payload['user']) && is_array($payload['user'])) {
                return $payload['user'];
            }
        }

        return null;
    }

    /**
     * 获取触发用户的 user_id。
     */
    public function getUserId(): ?int
    {
        $from = $this->getFrom();
        if ($from === null || ! isset($from['id'])) {
            return null;
        }

        return (int) $from['id'];
    }

    /**
     * 获取文本：消息 text、callback_query.data，或 web_app_data.data。
     */
    public function getText(): ?string
    {
        $message = $this->getMessage();
        if (isset($message['text']) && is_string($message['text'])) {
            return $message['text'];
        }

        $callback = $this->getCallbackQuery();
        if (isset($callback['data']) && is_string($callback['data'])) {
            return $callback['data'];
        }

        $webApp = $this->getWebAppData();
        if (isset($webApp['data']) && is_string($webApp['data'])) {
            return $webApp['data'];
        }

        return null;
    }

    /**
     * 判断是否为 Bot 命令（以 / 开头）。
     *
     * 传入 $command 时匹配命令名，兼容 `/start@BotName` 形式。
     *
     * @param string|null $command 命令名，如 start 或 /start；null 表示任意命令
     */
    public function isCommand(?string $command = null): bool
    {
        $text = $this->getText();
        if ($text === null || ! str_starts_with($text, '/')) {
            return false;
        }

        if ($command === null) {
            return true;
        }

        $name = ltrim($command, '/');
        $parts = preg_split('/\s+/', $text, 2) ?: [];
        $first = (string) ($parts[0] ?? '');
        $first = explode('@', $first, 2)[0];

        return $first === '/' . $name;
    }

    /**
     * 返回原始 Update 数组。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
            return;
        }

        $this->data[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * 按顺序返回第一个存在且为数组的字段。
     *
     * @param list<string> $keys
     * @return array<string, mixed>|null
     */
    protected function firstOf(array $keys): ?array
    {
        foreach ($keys as $key) {
            $value = $this->data[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }
}
