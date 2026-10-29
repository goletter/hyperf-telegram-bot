<?php

declare(strict_types=1);

namespace Goletter\Telegram\Http;

use Goletter\Telegram\Exceptions\TelegramApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Telegram Bot API 底层 HTTP 客户端。
 *
 * 负责拼装 URL、JSON/multipart 编码，并将非 ok 响应转为 TelegramApiException。
 */
class TelegramHttpClient
{
    /**
     * @param ClientInterface $http Guzzle 客户端
     * @param string $token 已规范化的 Bot Token
     * @param string $baseUri API 根地址，默认 https://api.telegram.org
     * @param string $botName Bot 逻辑名称（仅用于耗时日志）
     * @param bool $logRequest 是否记录请求耗时
     * @param LoggerInterface|null $logger 日志实现；logRequest 为 true 且为 null 时不写日志
     */
    public function __construct(
        protected ClientInterface $http,
        protected string $token,
        protected string $baseUri = 'https://api.telegram.org',
        protected string $botName = 'default',
        protected bool $logRequest = false,
        protected ?LoggerInterface $logger = null
    ) {
        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * 调用 Bot API 方法，返回响应中的 result 字段。
     *
     * @param string $method 方法名，如 sendMessage
     * @param array<string, mixed> $params 请求参数；含文件资源时自动走 multipart
     * @return mixed Telegram API result 字段
     * @throws TelegramApiException API 返回 ok=false、网络错误或非法 JSON
     */
    public function request(string $method, array $params = []): mixed
    {
        $url = sprintf('%s/bot%s/%s', $this->baseUri, $this->token, ltrim($method, '/'));
        $options = $this->buildOptions($params);
        $startedAt = hrtime(true);
        $ok = false;
        $error = null;

        try {
            $response = $this->http->request('POST', $url, $options);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if (! is_array($decoded)) {
                throw new TelegramApiException('Invalid JSON response from Telegram Bot API', 0, [
                    'raw' => $body,
                ]);
            }

            if (($decoded['ok'] ?? false) !== true) {
                $description = (string) ($decoded['description'] ?? 'Telegram Bot API error');
                $errorCode = (int) ($decoded['error_code'] ?? 0);

                // Token 无效时 Telegram 常返回 404 Not Found，补充可读提示
                if ($errorCode === 404 || strcasecmp($description, 'Not Found') === 0) {
                    $description = 'Not Found (usually invalid bot token or wrong API path). '
                        . 'Check token format "{bot_id}:{secret}".';
                }

                throw new TelegramApiException($description, $errorCode, $decoded);
            }

            $ok = true;

            return $decoded['result'] ?? null;
        } catch (TelegramApiException $e) {
            $error = $e->getMessage();
            throw $e;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $raw = $response ? (string) $response->getBody() : $e->getMessage();
            $decoded = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            $error = (string) ($decoded['description'] ?? $e->getMessage());

            throw new TelegramApiException(
                $error,
                (int) ($decoded['error_code'] ?? $e->getCode()),
                is_array($decoded) ? $decoded : ['raw' => $raw],
                $e
            );
        } catch (GuzzleException $e) {
            $error = $e->getMessage();
            throw new TelegramApiException($e->getMessage(), (int) $e->getCode(), [], $e);
        } finally {
            $this->writeRequestLog($method, $startedAt, $ok, $error);
        }
    }

    /**
     * 根据参数是否含上传内容，构建 JSON 或 multipart 请求选项。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function buildOptions(array $params): array
    {
        if ($this->requiresMultipart($params)) {
            return [
                RequestOptions::MULTIPART => $this->toMultipart($params),
            ];
        }

        return [
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            RequestOptions::JSON => $params,
        ];
    }

    /**
     * 判断参数中是否包含需要 multipart 上传的文件字段。
     *
     * @param array<string, mixed> $params
     */
    protected function requiresMultipart(array $params): bool
    {
        foreach ($params as $value) {
            if ($this->isUpload($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 是否为可上传内容：resource、SplFileInfo，或含 contents 的数组。
     */
    protected function isUpload(mixed $value): bool
    {
        if (is_resource($value)) {
            return true;
        }

        if ($value instanceof \SplFileInfo) {
            return true;
        }

        return is_array($value)
            && isset($value['contents'])
            && (is_resource($value['contents']) || is_string($value['contents']));
    }

    /**
     * 将参数转换为 Guzzle multipart 格式。
     *
     * 非文件字段中的 bool / array / object 会做字符串化处理。
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function toMultipart(array $params): array
    {
        $multipart = [];

        foreach ($params as $name => $value) {
            if ($this->isUpload($value)) {
                if (is_resource($value)) {
                    $multipart[] = [
                        'name' => (string) $name,
                        'contents' => $value,
                    ];
                    continue;
                }

                if ($value instanceof \SplFileInfo) {
                    $path = $value->getPathname();
                    $multipart[] = [
                        'name' => (string) $name,
                        'contents' => fopen($path, 'r'),
                        'filename' => $value->getFilename(),
                    ];
                    continue;
                }

                /** @var array{contents: mixed, filename?: string, headers?: array<string, string>} $value */
                $part = [
                    'name' => (string) $name,
                    'contents' => $value['contents'],
                ];
                if (isset($value['filename'])) {
                    $part['filename'] = $value['filename'];
                }
                if (isset($value['headers'])) {
                    $part['headers'] = $value['headers'];
                }
                $multipart[] = $part;
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $multipart[] = [
                'name' => (string) $name,
                'contents' => (string) $value,
            ];
        }

        return $multipart;
    }

    /**
     * 写入请求耗时日志（不含 Token / 请求体）。
     */
    protected function writeRequestLog(string $method, int $startedAt, bool $ok, ?string $error): void
    {
        if (! $this->logRequest || $this->logger === null) {
            return;
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1e6);
        $context = [
            'bot' => $this->botName,
            'method' => $method,
            'duration_ms' => $durationMs,
            'ok' => $ok,
        ];
        if ($error !== null && $error !== '') {
            $context['error'] = $error;
        }

        $message = sprintf(
            '[telegram] bot=%s method=%s duration_ms=%d ok=%d',
            $this->botName,
            $method,
            $durationMs,
            $ok ? 1 : 0
        );

        if ($ok) {
            $this->logger->info($message, $context);
        } else {
            $this->logger->warning($message, $context);
        }
    }
}
