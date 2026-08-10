<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // 仅当使用 BotFactory::get() / 注入 BotInterface 时需要默认名
    'default' => env('TELEGRAM_BOT', 'default'),

    // 可选：静态配置的 Bot。动态 Token 场景可不配，改用 BotFactory::token()/resolve()
    'bots' => [
        // 'default' => [
        //     'token' => env('TELEGRAM_BOT_TOKEN', ''),
        //     'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        // ],
    ],

    'http' => [
        'base_uri' => env('TELEGRAM_API_BASE_URI', 'https://api.telegram.org'),
        'timeout' => (float) env('TELEGRAM_HTTP_TIMEOUT', 30),
        // 可选代理，例如 http://127.0.0.1:7890
        'proxy' => env('TELEGRAM_HTTP_PROXY'),
    ],
];
