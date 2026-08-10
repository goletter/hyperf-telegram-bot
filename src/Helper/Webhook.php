<?php

declare(strict_types=1);

namespace Goletter\Telegram\Helper;

use Goletter\Telegram\Bot;
use Goletter\Telegram\Exceptions\TelegramApiException;
use Goletter\Telegram\Factory\BotFactory;
use Goletter\Telegram\Update\Update;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Webhook 请求解析助手。
 *
 * 负责：解析 Update、校验 Secret Token、把动态 Bot 挂到 Request 上。
 */
class Webhook
{
    /**
     * Request attribute 键：存放已解析的 Bot 实例。
     */
    public const REQUEST_BOT_ATTRIBUTE = 'telegram.bot';

    public function __construct(protected BotFactory $botFactory)
    {
    }

    /**
     * 从 HTTP 请求体解析 Update。
     *
     * 默认会校验请求头 `X-Telegram-Bot-Api-Secret-Token`（Bot 未配置 secret 时跳过）。
     *
     * @param ServerRequestInterface $request 原始 Webhook 请求
     * @param Bot|string|null $bot Bot 实例、缓存名，或 null（从 request attribute / 默认配置解析）
     * @param bool $verifySecret 是否校验 secret
     * @throws TelegramApiException secret 不匹配或 payload 非法时
     */
    public function parseRequest(
        ServerRequestInterface $request,
        Bot|string|null $bot = null,
        bool $verifySecret = true
    ): Update {
        $client = $this->resolveBot($request, $bot);

        if ($verifySecret) {
            $secret = $request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token');
            if (! $client->verifyWebhookSecret($secret === '' ? null : $secret)) {
                throw new TelegramApiException('Invalid Telegram webhook secret token.', 403);
            }
        }

        return $client->parseUpdate((string) $request->getBody());
    }

    /**
     * 解析当前应使用的 Bot。
     *
     * 优先级：传入的 Bot 实例 > 传入的名称 > Request attribute > 默认 get()。
     *
     * @param Bot|string|null $bot
     */
    public function bot(Bot|string|null $bot = null, ?ServerRequestInterface $request = null): Bot
    {
        if ($bot instanceof Bot) {
            return $bot;
        }

        if (is_string($bot) && $bot !== '') {
            return $this->botFactory->get($bot);
        }

        if ($request instanceof ServerRequestInterface) {
            $attr = $request->getAttribute(self::REQUEST_BOT_ATTRIBUTE);
            if ($attr instanceof Bot) {
                return $attr;
            }
        }

        return $this->botFactory->get(is_string($bot) ? $bot : null);
    }

    /**
     * 用动态 Token 创建 Bot，并挂到 Request attribute，供后续中间件/控制器复用。
     *
     * 动态多 Bot + VerifyTelegramWebhookMiddleware 时，业务中间件应先调用本方法。
     *
     * @param string $token Bot Token
     * @param string|null $name 缓存名（推荐业务 ID）
     * @param array{webhook_secret?: string} $options
     * @return ServerRequestInterface 带 telegram.bot attribute 的新 Request
     */
    public function attach(
        ServerRequestInterface $request,
        string $token,
        ?string $name = null,
        array $options = []
    ): ServerRequestInterface {
        $bot = $this->botFactory->token($token, $name, $options);

        return $request->withAttribute(self::REQUEST_BOT_ATTRIBUTE, $bot);
    }

    /**
     * 内部解析 Bot：实例 > request attribute > 名称 > 路由参数 bot > 默认配置。
     *
     * @param Bot|string|null $bot
     */
    protected function resolveBot(ServerRequestInterface $request, Bot|string|null $bot): Bot
    {
        if ($bot instanceof Bot) {
            return $bot;
        }

        $attr = $request->getAttribute(self::REQUEST_BOT_ATTRIBUTE);
        if ($attr instanceof Bot) {
            return $attr;
        }

        if (is_string($bot) && $bot !== '') {
            return $this->botFactory->get($bot);
        }

        $routeBot = $request->getAttribute('bot');
        if (is_string($routeBot) && $routeBot !== '') {
            return $this->botFactory->get($routeBot);
        }

        return $this->botFactory->get();
    }
}
