<?php

declare(strict_types=1);

namespace Goletter\Telegram;

use Goletter\Telegram\Contract\BotChatRepositoryInterface;
use Goletter\Telegram\Store\InMemoryBotChatRepository;

/**
 * Hyperf ConfigProvider：注册注解扫描路径，并发布 telegram.php 配置。
 *
 * 默认不绑定 BotInterface；动态多 Bot 请注入 BotFactory。
 * Bot ↔ 群关系默认使用进程内内存仓储，生产请自行绑定数据库实现。
 */
class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                // 动态多 Bot 请注入 BotFactory。
                // 若仍需默认 BotInterface，请自行在项目 dependencies 中绑定：
                // BotInterface::class => fn ($c) => $c->get(BotFactory::class)->get(),
                BotChatRepositoryInterface::class => InMemoryBotChatRepository::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of Telegram Bot client.',
                    'source' => __DIR__ . '/../publish/telegram.php',
                    'destination' => BASE_PATH . '/config/autoload/telegram.php',
                ],
            ],
        ];
    }
}
