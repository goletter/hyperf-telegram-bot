<?php

declare(strict_types=1);

namespace Goletter\Telegram\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Telegram Bot API / 客户端调用异常。
 *
 * 通常携带 Telegram 原始响应片段，便于读取 error_code、parameters.retry_after 等。
 */
class TelegramApiException extends RuntimeException
{
    /**
     * @param string $message 错误描述
     * @param int $code 优先使用 Telegram error_code
     * @param array<string, mixed> $response Telegram 原始响应或部分字段（含 description / parameters 等）
     * @param Throwable|null $previous 底层异常（如 Guzzle）
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        protected array $response = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取构造时保存的原始响应数组。
     *
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * 获取 Telegram description，缺失时回退到异常 message。
     */
    public function getDescription(): string
    {
        return (string) ($this->response['description'] ?? $this->getMessage());
    }

    /**
     * 获取 Telegram error_code，缺失时回退到异常 code。
     */
    public function getErrorCode(): int
    {
        return (int) ($this->response['error_code'] ?? $this->getCode());
    }

    /**
     * 获取 Telegram parameters（如 flood control 的 retry_after）。
     *
     * @return array<string, mixed>|null
     */
    public function getParameters(): ?array
    {
        $parameters = $this->response['parameters'] ?? null;

        return is_array($parameters) ? $parameters : null;
    }
}
