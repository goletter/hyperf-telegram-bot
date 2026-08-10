<?php

declare(strict_types=1);

namespace Goletter\Telegram\Middleware;

use Goletter\Telegram\Bot;
use Goletter\Telegram\Helper\Webhook;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 校验 Telegram Webhook 的 X-Telegram-Bot-Api-Secret-Token。
 *
 * Bot 解析顺序：
 * 1. Request attribute `telegram.bot`（动态 Token 场景由业务先 attach）
 * 2. 路由参数 `bot` 对应的已注册/配置 Bot
 *
 * 动态多 Bot 示例：
 * ```php
 * // 业务中间件里（本中间件之前）
 * $entity = $repo->find($request->getAttribute('id'));
 * $request = $webhook->attach($request, $entity->token, (string) $entity->id, [
 *     'webhook_secret' => $entity->webhook_secret,
 * ]);
 * ```
 *
 * 注意：本中间件不会查库；动态 Token 必须先 attach，否则只能走静态配置名。
 */
class VerifyTelegramWebhookMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected Webhook $webhook,
        protected HttpResponse $response
    ) {
    }

    /**
     * 校验 secret；失败返回 403 JSON；Bot 无法解析返回 404 JSON。
     *
     * 校验通过后若 Request 尚无 telegram.bot，会写入当前 Bot，便于控制器复用。
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $bot = $this->webhook->bot(null, $request);
        } catch (\Throwable $e) {
            return $this->response->json([
                'ok' => false,
                'description' => $e->getMessage(),
            ])->withStatus(404);
        }

        $secret = $request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token');
        if (! $bot->verifyWebhookSecret($secret === '' ? null : $secret)) {
            return $this->response->json([
                'ok' => false,
                'description' => 'Invalid Telegram webhook secret token.',
            ])->withStatus(403);
        }

        if (! $request->getAttribute(Webhook::REQUEST_BOT_ATTRIBUTE) instanceof Bot) {
            $request = $request->withAttribute(Webhook::REQUEST_BOT_ATTRIBUTE, $bot);
        }

        return $handler->handle($request);
    }
}
