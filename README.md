# Hyperf Telegram Bot

Hyperf 协程友好的 Telegram Bot API 客户端，支持多 Bot、动态 Token、Webhook 校验与常用消息能力。

## 心智模型

| 角色 | 类 | 作用 |
|------|-----|------|
| 工厂 | `Goletter\Telegram\Factory\BotFactory` | **唯一推荐入口**：按 Token / 名称拿到 `Bot` |
| 客户端 | `Goletter\Telegram\Bot` | 调用 Telegram API（发消息、设 Webhook 等） |
| 解析 | `Helper\Webhook` + `Update\Update` | 接收推送、校验 secret、读取命令 / chat_id |
| 中间件 | `Middleware\VerifyTelegramWebhookMiddleware` | 只做 secret 校验；动态多 Bot 需先 `attach` |

请注入 `BotFactory`。包默认**不绑定** `BotInterface`（未配置静态 Token 时注入会失败）。

---

## 安装

### 本仓库（已通过 PSR-4 引入）

`composer.json`：

```json
"Goletter\\Telegram\\": "packages/goletter/hyperf-telegram-bot/src/"
```

`config/autoload/annotations.php` 扫描路径需包含：

```php
BASE_PATH . '/packages/goletter/hyperf-telegram-bot/src',
```

发布配置（若尚未有 `config/autoload/telegram.php`）：

```bash
php bin/hyperf.php vendor:publish goletter/hyperf-telegram-bot
```

### 独立项目

```bash
composer require goletter/hyperf-telegram-bot
php bin/hyperf.php vendor:publish goletter/hyperf-telegram-bot
```

---

## 配置

`config/autoload/telegram.php` **主要只配 HTTP**。多机器人、Token 存在数据库时，不必写死 `bots`：

```php
return [
    'default' => env('TELEGRAM_BOT', 'default'),
    'bots' => [
        // 可选静态 Bot；动态场景可留空
    ],
    'http' => [
        'base_uri' => env('TELEGRAM_API_BASE_URI', 'https://api.telegram.org'),
        'timeout' => (float) env('TELEGRAM_HTTP_TIMEOUT', 30),
        'proxy' => env('TELEGRAM_HTTP_PROXY'), // 例如 http://127.0.0.1:7890
    ],
];
```

静态单 Bot 时可这样写：

```php
'bots' => [
    'default' => [
        'token' => env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    ],
],
```

Token 格式必须是 `{bot_id}:{secret}`，例如 `7123456789:AAHxxxx`。不要只填 secret 段，也不要把整段 API URL 塞进来。

---

## 场景 A：动态多 Bot（推荐）

Token 来自数据库 / 业务配置，可随时变更。同名 Bot 在 Token 变化时会自动重建缓存。

```php
use Goletter\Telegram\Factory\BotFactory;
use Hyperf\Di\Annotation\Inject;

#[Inject]
protected BotFactory $bots;

// 按 Token 创建（默认缓存名：bot:{bot_id}，如 123456:ABC -> bot:123456）
$bot = $this->bots->token($row->token);

// 推荐：用业务 ID 作为名称，便于 Token 轮换后命中同一缓存槽
$bot = $this->bots->token($row->token, (string) $row->id, [
    'webhook_secret' => (string) $row->webhook_secret,
]);

$bot->sendMessage([
    'chat_id' => 123456,
    'text' => 'Hello',
]);

// Token 更新后再 resolve，旧实例自动失效
$bot = $this->bots->resolve((string) $row->id, $newToken, [
    'webhook_secret' => $newSecret,
]);

// 主动丢弃缓存
$this->bots->forget((string) $row->id);
```

### `BotFactory` 方法怎么选

| 方法 | 何时用 |
|------|--------|
| `token($token)` | 临时用，缓存名默认 `bot:{bot_id}` |
| `token($token, $bizId, $options)` | **业务侧推荐**，名称稳定、便于轮换 |
| `resolve($name, $token, $options)` | 显式按名称绑定；Token 变了会重建 |
| `get('default')` | 仅当配置文件里有静态 `bots.xxx.token` |
| `make(...)` | 只创建、不进缓存（一次性调用） |
| `forget($name)` | 主动清缓存；`forget()` 清空全部 |

`$options` 目前支持：`webhook_secret`（设置 Webhook / 校验推送时用）。

---

## 场景 B：静态单 Bot

在 `telegram.bots.default.token` 配好后：

```php
$bot = $this->bots->get('default');
// 或省略名称，取 telegram.default
$bot = $this->bots->get();
```

如仍希望注入 `BotInterface`，在项目 `config/autoload/dependencies.php` 自行绑定：

```php
use Goletter\Telegram\Contract\BotInterface;
use Goletter\Telegram\Factory\BotFactory;

return [
    BotInterface::class => static fn ($c) => $c->get(BotFactory::class)->get(),
];
```

---

## 场景 C：Webhook（完整可跑）

### 1. 路由建议

`POST /telegram/webhook/{id}`，用业务 ID 区分机器人。

### 2. 控制器（推荐写法）

在控制器里用 `BotFactory` 拿到 Bot，再交给 `Webhook::parseRequest`（会校验 `X-Telegram-Bot-Api-Secret-Token`）。

```php
use Goletter\Telegram\Factory\BotFactory;
use Goletter\Telegram\Helper\Webhook;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller]
class TelegramWebhookController
{
    public function __construct(
        protected BotFactory $bots,
        protected Webhook $webhook,
        protected BotRepository $repo, // 你们自己的仓储
    ) {
    }

    #[PostMapping(path: '/telegram/webhook/{id}')]
    public function handle(int $id, RequestInterface $request)
    {
        $entity = $this->repo->find($id);

        $bot = $this->bots->token($entity->token, (string) $entity->id, [
            'webhook_secret' => $entity->webhook_secret,
        ]);

        $update = $this->webhook->parseRequest($request, $bot);

        if ($update->isCommand('start')) {
            $bot->sendMessage([
                'chat_id' => $update->getChatId(),
                'text' => 'Welcome!',
            ]);
        }

        return ['ok' => true];
    }
}
```

### 3. 设置 Webhook

每个机器人各自 URL。若创建 Bot 时带了 `webhook_secret`，`setWebhook` 会自动附带 `secret_token`：

```php
$bot->setWebhook([
    'url' => 'https://example.com/telegram/webhook/' . $entity->id,
    'allowed_updates' => ['message', 'callback_query', 'chat_member', 'chat_join_request'],
]);
```

### 4. 可选：中间件校验

`VerifyTelegramWebhookMiddleware` **不会**自己从数据库查 Token。动态多 Bot 时，必须先在业务中间件里 `attach`：

```php
// 业务中间件（在 VerifyTelegramWebhookMiddleware 之前）
$entity = $repo->find($request->getAttribute('id'));
$request = $webhook->attach($request, $entity->token, (string) $entity->id, [
    'webhook_secret' => $entity->webhook_secret,
]);
```

控制器里再解析：

```php
$update = $this->webhook->parseRequest($request); // 从 request attribute 取 Bot
$bot = $this->webhook->bot(null, $request);
```

静态配置且路由带 `{bot}` 参数时，中间件可直接按配置名 `get($bot)`，无需 `attach`。

---

## 场景 D：Long Polling

```php
$updates = $bot->getUpdates([
    'offset' => $offset,
    'timeout' => 30,
]);

foreach ($updates as $item) {
    $update = $bot->parseUpdate($item);
    // 使用 $update->getChatId() / isCommand() 等
}
```

Webhook 与 Long Polling 不要同时用于同一 Bot。

---

## 常用能力

### 发送消息 / 文件

```php
$bot->sendMessage([
    'chat_id' => 123456,
    'text' => 'Hello',
]);

$bot->sendDocument([
    'chat_id' => 123456,
    'document' => fopen('/path/to/file.pdf', 'r'),
    'caption' => '报表',
]);
// document 也支持 SplFileInfo，或 ['contents' => ..., 'filename' => 'a.pdf']
```

任意未封装的 API 可用：

```php
$bot->call('sendVenue', [/* ... */]);
```

### 获取群用户（重要限制）

Telegram Bot API **不能一次拉全群成员**，只能：

1. 查人数 / 管理员  
2. 按已知 `user_id` 查单个或批量  
3. 监听 `chat_member` 进退群事件自行落库后再查  

```php
$chatId = -1001234567890;

$users = $bot->getGroupUsers($chatId);
// ['chat_id' => ..., 'count' => 128, 'administrators' => [...]]

$count = $bot->getGroupMemberCount($chatId);
$admins = $bot->getGroupAdmins($chatId);
$member = $bot->getGroupMember($chatId, 123456789);
// $member['status'] => creator|administrator|member|restricted|left|kicked

$map = $bot->getGroupMembers($chatId, [111, 222, 333]);
// 不在群内 / 查询失败的为 null

$chat = $bot->getChat(['chat_id' => $chatId]);
```

监听成员变动并落库（Webhook 需允许 `chat_member`）：

```php
if ($changed = $update->getChatMemberUpdate()) {
    $user = $changed['new_chat_member']['user'];
    $status = $changed['new_chat_member']['status']; // member / left / ...
    // 写入群成员表，之后用 getGroupMembers 回查
}
```

### 拉群 / 邀请链接

Bot **不能直接把用户拉进群**，需具备管理员的 `can_invite_users`，通过邀请链接或审批加群申请完成。

```php
$link = $bot->inviteToChat(-1001234567890, [
    'name' => '活动拉群',
    'member_limit' => 100,
    'expire_date' => time() + 86400,
    // 'creates_join_request' => true,
]);
// $link['invite_link'] => https://t.me/+xxxx

$url = $bot->exportChatInviteLink(['chat_id' => -1001234567890]);

if ($join = $update->getChatJoinRequest()) {
    $bot->approveChatJoinRequest([
        'chat_id' => $update->getChatId(),
        'user_id' => $update->getUserId(),
    ]);
    // 或：$bot->declineChatJoinRequest([...]);
}

$bot->revokeChatInviteLink([
    'chat_id' => -1001234567890,
    'invite_link' => $link['invite_link'],
]);
```

---

## Update 常用方法

| 方法 | 说明 |
|------|------|
| `getUpdateId()` | update_id |
| `getMessage()` | message / edited_message / channel_post 等 |
| `getCallbackQuery()` | 回调查询 |
| `getChatJoinRequest()` | 加群申请 |
| `getChatMemberUpdate()` | chat_member / my_chat_member |
| `getChatId()` / `getUserId()` | 会话与用户 ID |
| `getText()` | 消息文本或 callback data |
| `isCommand('start')` | 是否为 `/start`（兼容 `/start@BotName`） |
| `toArray()` | 原始数组 |

---

## 异常

API 失败抛出 `Goletter\Telegram\Exceptions\TelegramApiException`：

```php
use Goletter\Telegram\Exceptions\TelegramApiException;

try {
    $bot->sendMessage(['chat_id' => 1, 'text' => 'hi']);
} catch (TelegramApiException $e) {
    $e->getDescription();
    $e->getErrorCode();
    $e->getParameters(); // 如 retry_after
    $e->getResponse();
}
```

Webhook secret 校验失败同样抛该异常（HTTP 语义上对应 403）。
