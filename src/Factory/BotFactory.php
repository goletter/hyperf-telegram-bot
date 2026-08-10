<?php

declare(strict_types=1);

namespace Goletter\Telegram\Factory;

use Goletter\Telegram\Bot;
use Goletter\Telegram\Exceptions\TelegramApiException;
use GuzzleHttp\ClientInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use InvalidArgumentException;

/**
 * Bot 工厂：支持配置文件与运行时动态 Token。
 *
 * 多机器人、Token 可变场景优先使用 token() / resolve()：
 *
 * ```php
 * $bot = $factory->token($row->token, name: (string) $row->id, options: [
 *     'webhook_secret' => $row->webhook_secret,
 * ]);
 * // Token 更新后再次 resolve 同名 Bot 会自动重建
 * $bot = $factory->resolve((string) $row->id, $row->token);
 * ```
 */
class BotFactory
{
    /**
     * 已缓存的 Bot 实例，键为逻辑名称。
     *
     * @var array<string, Bot>
     */
    protected array $bots = [];

    /**
     * 缓存中对应名称的 Token，用于检测变更。
     *
     * @var array<string, string>
     */
    protected array $tokens = [];

    /**
     * 共享 HTTP 客户端（懒创建）。
     */
    protected ?ClientInterface $httpClient = null;

    public function __construct(
        protected ConfigInterface $config,
        protected ClientFactory $clientFactory
    ) {
    }

    /**
     * 按配置名获取 Bot（可被 register/resolve 覆盖）。
     *
     * 名称默认取 `telegram.default`。需在 `telegram.bots.{name}.token` 有配置，
     * 或此前已通过 token()/resolve() 注册。
     *
     * @param string|null $name Bot 名称；null 使用默认名
     * @throws TelegramApiException 未配置且未缓存时
     */
    public function get(?string $name = null): Bot
    {
        $name = $name ?: (string) $this->config->get('telegram.default', 'default');

        if (isset($this->bots[$name])) {
            return $this->bots[$name];
        }

        $botConfig = (array) $this->config->get("telegram.bots.{$name}", []);
        $token = (string) ($botConfig['token'] ?? '');
        if ($token === '') {
            throw new TelegramApiException(sprintf(
                'Telegram bot [%s] token is not configured. Use token()/resolve() for dynamic bots.',
                $name
            ));
        }

        return $this->resolve($name, $token, [
            'webhook_secret' => (string) ($botConfig['webhook_secret'] ?? ''),
        ]);
    }

    /**
     * 使用动态 Token 创建（或复用）Bot。
     *
     * 未传 name 时，默认用 Token 中的 bot_id 生成缓存名：`bot:{bot_id}`。
     * 业务侧推荐显式传入业务 ID，便于 Token 轮换后仍命中同一缓存槽。
     *
     * @param string $token Bot Token
     * @param string|null $name 缓存/逻辑名称
     * @param array{webhook_secret?: string, name?: string} $options
     * @throws TelegramApiException Token 为空时
     */
    public function token(string $token, ?string $name = null, array $options = []): Bot
    {
        $token = trim($token);
        if ($token === '') {
            throw new TelegramApiException('Telegram bot token is empty.');
        }

        $name = $name ?? $this->nameFromToken($token);

        return $this->resolve($name, $token, $options);
    }

    /**
     * 按名称绑定动态 Token；Token 或 webhook_secret 变化时自动丢弃旧实例。
     *
     * @param string $name 缓存/逻辑名称
     * @param string $token Bot Token
     * @param array{webhook_secret?: string} $options
     * @throws TelegramApiException Token 为空时
     */
    public function resolve(string $name, string $token, array $options = []): Bot
    {
        $token = trim($token);
        if ($token === '') {
            throw new TelegramApiException(sprintf('Telegram bot [%s] token is empty.', $name));
        }

        if (isset($this->bots[$name], $this->tokens[$name]) && $this->tokens[$name] === $token) {
            $bot = $this->bots[$name];
            $secret = (string) ($options['webhook_secret'] ?? $bot->getWebhookSecret());
            if ($secret === $bot->getWebhookSecret()) {
                return $bot;
            }
        }

        $bot = $this->create($name, $token, $options);
        $this->bots[$name] = $bot;
        $this->tokens[$name] = $token;

        return $bot;
    }

    /**
     * 运行时注册/更新某个命名 Bot 的 Token（等同 resolve）。
     *
     * @param array{webhook_secret?: string} $options
     */
    public function register(string $name, string $token, array $options = []): Bot
    {
        return $this->resolve($name, $token, $options);
    }

    /**
     * 仅创建 Bot，不写入工厂缓存（适合一次性调用）。
     *
     * 未传 token 时从 `telegram.bots.{name}` 读取。
     *
     * @param string|null $name 逻辑名称，默认取 telegram.default
     * @param string|null $token 为空则读配置
     * @param array{webhook_secret?: string} $options
     * @throws TelegramApiException Token 不可用时
     */
    public function make(?string $name = null, ?string $token = null, array $options = []): Bot
    {
        $name = $name ?: (string) $this->config->get('telegram.default', 'default');

        if ($token === null || $token === '') {
            $botConfig = (array) $this->config->get("telegram.bots.{$name}", []);
            $token = (string) ($botConfig['token'] ?? '');
            if (! isset($options['webhook_secret'])) {
                $options['webhook_secret'] = (string) ($botConfig['webhook_secret'] ?? '');
            }
        }

        if ($token === '') {
            throw new TelegramApiException(sprintf('Telegram bot [%s] token is not configured.', $name));
        }

        return $this->create($name, $token, $options);
    }

    /**
     * 列出已知 Bot 名称（配置文件 + 运行时缓存）。
     *
     * @return list<string>
     */
    public function names(): array
    {
        $configured = $this->config->get('telegram.bots', []);
        $configured = is_array($configured) ? array_map('strval', array_keys($configured)) : [];

        return array_values(array_unique([...$configured, ...array_keys($this->bots)]));
    }

    /**
     * 判断名称是否已有配置 Token 或运行时缓存。
     */
    public function has(string $name): bool
    {
        if (isset($this->bots[$name])) {
            return true;
        }

        return (string) $this->config->get("telegram.bots.{$name}.token", '') !== '';
    }

    /**
     * 丢弃缓存中的 Bot 实例。
     *
     * @param string|null $name 指定名称；null 清空全部
     */
    public function forget(?string $name = null): void
    {
        if ($name === null) {
            $this->bots = [];
            $this->tokens = [];
            return;
        }

        unset($this->bots[$name], $this->tokens[$name]);
    }

    /**
     * 当前缓存中该名称对应的 Token（未缓存返回 null）。
     */
    public function cachedToken(string $name): ?string
    {
        return $this->tokens[$name] ?? null;
    }

    /**
     * 将未定义方法代理到默认 Bot（`$factory->get()`）。
     *
     * 便于 `$factory->sendMessage([...])` 这类快捷调用；多 Bot 场景请先 token()/get()。
     *
     * @param list<mixed> $arguments
     * @throws InvalidArgumentException 默认 Bot 上不存在该方法时
     */
    public function __call(string $method, array $arguments): mixed
    {
        $bot = $this->get();
        if (! method_exists($bot, $method)) {
            throw new InvalidArgumentException(sprintf('Method %s::%s does not exist.', Bot::class, $method));
        }

        return $bot->{$method}(...$arguments);
    }

    /**
     * 创建新的 Bot 实例（不负责缓存写入）。
     *
     * @param array{webhook_secret?: string} $options
     */
    protected function create(string $name, string $token, array $options = []): Bot
    {
        $http = (array) $this->config->get('telegram.http', []);
        $baseUri = (string) ($http['base_uri'] ?? 'https://api.telegram.org');

        return new Bot(
            token: $token,
            client: $this->httpClient(),
            name: $name,
            baseUri: $baseUri,
            webhookSecret: (string) ($options['webhook_secret'] ?? '')
        );
    }

    /**
     * 获取（或懒创建）共享 Guzzle 客户端，读取 telegram.http 配置。
     */
    protected function httpClient(): ClientInterface
    {
        if ($this->httpClient instanceof ClientInterface) {
            return $this->httpClient;
        }

        $http = (array) $this->config->get('telegram.http', []);
        $options = [
            'base_uri' => (string) ($http['base_uri'] ?? 'https://api.telegram.org'),
            'timeout' => (float) ($http['timeout'] ?? 30),
            'http_errors' => false,
        ];

        $proxy = $http['proxy'] ?? null;
        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return $this->httpClient = $this->clientFactory->create($options);
    }

    /**
     * 从 Token 推导默认缓存名：`bot:{bot_id}`；无法解析时用 sha256 前缀。
     */
    protected function nameFromToken(string $token): string
    {
        $botId = explode(':', $token, 2)[0] ?? '';
        if ($botId !== '' && ctype_digit($botId)) {
            return 'bot:' . $botId;
        }

        return 'token:' . hash('sha256', $token);
    }
}
