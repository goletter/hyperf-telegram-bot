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
        // 请求耗时日志，默认关闭；开启后写 StdoutLogger，不含 Token
        'log_request' => (bool) env('TELEGRAM_HTTP_LOG_REQUEST', false),
    ],
];
```

开启后每次 Bot API 调用会打类似日志：

```text
[telegram] bot=default method=sendMessage duration_ms=128 ok=1
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
    'allowed_updates' => ['message', 'callback_query', 'my_chat_member', 'chat_member', 'chat_join_request'],
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

// 静音发送（无声音/震动提醒）
$bot->sendMessage([
    'chat_id' => 123456,
    'text' => '静默通知',
    'disable_notification' => true,
]);

// 单图：file_id / 远程 URL / 资源流 / SplFileInfo / ['contents'=>..., 'filename'=>...]
$bot->sendPhoto([
    'chat_id' => 123456,
    'photo' => 'https://example.com/cover.jpg', // 远程地址
    'caption' => '封面图',
    'disable_notification' => true,
]);

// 多图（1～10）：远程 URL、本地文件可混用；返回 Message 列表
$bot->sendPhotos(123456, [
    'https://example.com/1.jpg',
    'https://example.com/2.jpg',
    ['media' => fopen('/path/to/3.jpg', 'r'), 'caption' => '第三张说明'],
], [
    'disable_notification' => true,
]);

// 底层媒体组（可混 photo/video 等 InputMedia）
$bot->sendMediaGroup([
    'chat_id' => 123456,
    'media' => [
        ['type' => 'photo', 'media' => 'https://example.com/a.jpg'],
        ['type' => 'photo', 'media' => 'https://example.com/b.jpg', 'caption' => '相册'],
    ],
]);

// 单视频：远程 URL / file_id / 本地文件
$bot->sendVideo([
    'chat_id' => 123456,
    'video' => 'https://example.com/clip.mp4',
    'caption' => '演示视频',
    'supports_streaming' => true,
]);

// 多视频（1～10）：远程 URL、本地文件可混用
$bot->sendVideos(123456, [
    'https://example.com/a.mp4',
    ['media' => 'https://example.com/b.mp4', 'caption' => '第二段', 'supports_streaming' => true],
    fopen('/path/to/c.mp4', 'r'),
]);

// 文档：远程 URL / file_id / 本地文件
$bot->sendDocument([
    'chat_id' => 123456,
    'document' => 'https://example.com/report.pdf',
    'caption' => '报表',
    'disable_notification' => true,
]);
$bot->sendDocument([
    'chat_id' => 123456,
    'document' => fopen('/path/to/file.pdf', 'r'),
    'caption' => '本地上传',
]);
// 也支持 SplFileInfo，或 ['contents' => ..., 'filename' => 'a.pdf']
```

任意未封装的 API 可用：

```php
$bot->call('sendVenue', [/* ... */]);
```

### 输入框旁菜单按钮（Menu Button）

可设为命令列表、Mini App（`web_app`）或恢复默认。与系统「+」附件菜单无关。

Telegram **没有**独立的 create / update / delete：设置即覆盖，删除即恢复 `default`。

| 操作 | 方法 |
|------|------|
| 添加 / 修改 | `setWebAppMenuButton($text, $url, $chatId?)` |
| 查询 | `getWebAppMenuButton($chatId?)` → `['text'=>..., 'url'=>...]` 或 `null` |
| 删除 | `removeWebAppMenuButton($chatId?)`（恢复 default） |

```php
// 添加（或修改：再调一次覆盖文案/URL）
$bot->setWebAppMenuButton('工单', 'https://your.domain/ticket');
$bot->setWebAppMenuButton('工单', 'https://your.domain/ticket/v2', -1001234567890);

// 查询当前 web_app 配置（非 web_app 时为 null）
$current = $bot->getWebAppMenuButton(-1001234567890);
// ['text' => '工单', 'url' => 'https://...'] 或 null

// 删除（去掉 Mini App 菜单，恢复默认）
$bot->removeWebAppMenuButton(-1001234567890);
// 或：$bot->resetChatMenuButton($chatId);

// 命令列表：先 setMyCommands，再把菜单设为 commands
$bot->setMyCommands([
    'commands' => [
        ['command' => 'repair', 'description' => '提交报修'],
        ['command' => 'complaint', 'description' => '提交投诉'],
    ],
    'scope' => ['type' => 'all_group_chats'],
]);
$bot->setCommandsMenuButton(); // 或带 chat_id

// 原始参数也可
$bot->setChatMenuButton([
    'menu_button' => [
        'type' => 'web_app',
        'text' => '工单',
        'web_app' => ['url' => 'https://your.domain/ticket'],
    ],
]);
```

Menu Button 打开的 Mini App 一般自行调业务 API。若使用 ReplyKeyboard 上的 `web_app` 按钮回传，可用 `$update->getWebAppData()`。

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

$info = $bot->getGroupInfo($chatId);
// ['chat_id' => ..., 'chat' => [...], 'count' => 128, 'administrators' => [...]]

$map = $bot->getGroupsInfo([-100111, -100222]);
// chat_id 字符串键 => 资料|null
```

监听成员变动并落库（Webhook 需允许 `chat_member`）：

```php
if ($changed = $update->getChatMemberUpdate()) {
    $user = $changed['new_chat_member']['user'];
    $status = $changed['new_chat_member']['status']; // member / left / ...
    // 写入群成员表，之后用 getGroupMembers 回查
}
```

### 按 Bot 获取已加入的群（需自行落库）

Telegram **没有**「列出该 Bot 所在全部群」的 API。本包提供：

| 组件 | 作用 |
|------|------|
| `BotChatTracker` | 从 Update 同步，再按 Bot 查询群列表 |
| `BotChatRepositoryInterface` | 仓储契约；默认内存实现，生产请换数据库 |
| `getMyChatMember()` / `isMyChatMemberUpdate()` | 识别 Bot 进退群事件 |
| `getGroupInfo` / `getGroupsInfo` | 已知 `chat_id` 时拉实时资料 |

Webhook 控制器里同步：

```php
use Goletter\Telegram\Service\BotChatTracker;

public function __construct(
    protected BotFactory $bots,
    protected Webhook $webhook,
    protected BotChatTracker $chats,
    protected BotRepository $repo,
) {
}

public function handle(int $id, RequestInterface $request)
{
    $entity = $this->repo->find($id);
    $bot = $this->bots->token($entity->token, (string) $entity->id, [
        'webhook_secret' => $entity->webhook_secret,
    ]);

    $update = $this->webhook->parseRequest($request, $bot);
    $this->chats->sync($bot, $update); // 写入 my_chat_member / 群消息中的 chat

    return ['ok' => true];
}
```

按 Bot 取群：

```php
// 本地已同步记录
$groups = $this->chats->getGroups($bot);

// 并对每条调用 getChat + 人数等刷新（失败会标记 inactive）
$groups = $this->chats->getGroups($bot, refresh: true);

// 单群
$group = $this->chats->getGroup($bot, -1001234567890, refresh: true);
```

生产环境请绑定自己的仓储（内存实现重启/多 Worker 会丢）：

```php
// config/autoload/dependencies.php
use Goletter\Telegram\Contract\BotChatRepositoryInterface;
use App\Telegram\DbBotChatRepository;

return [
    BotChatRepositoryInterface::class => DbBotChatRepository::class,
];
```

设置 Webhook 时务必包含 `my_chat_member`，否则 Bot 被拉进/踢出群时收不到事件。

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

## 已封装 API 一览

未列出的方法仍可用 `$bot->call('methodName', $params)`。

| 类别 | 方法 |
|------|------|
| 文本/编辑 | `sendMessage`、`editMessageText` / `Caption` / `Media` / `ReplyMarkup`、`deleteMessage(s)` |
| 转发复制 | `forwardMessage(s)`、`copyMessage(s)` |
| 媒体 | `sendPhoto` / `Video` / `Document` / `Animation` / `Audio` / `Voice` / `VideoNote` / `Sticker`、`sendMediaGroup`、`sendPhotos` / `sendVideos` |
| 其它消息 | `sendLocation` / `Venue` / `Contact` / `Poll` / `Dice`、`stopPoll`、`sendChatAction`、`setMessageReaction` |
| 回调/Inline/WebApp | `answerCallbackQuery`、`answerInlineQuery`、`answerWebAppQuery` |
| 菜单/命令/资料 | Menu Button 系列、`set/get/deleteMyCommands`、`set/getMyName`、`Description`、`ShortDescription`、默认管理员权限 |
| 群管理 | 邀请链接、进群审批、`ban`/`unban`/`restrict`/`promote`、权限与头衔、置顶、群资料、`leaveChat` |
| 论坛话题 | `create/edit/close/reopen/deleteForumTopic`、`unpinAllForumTopicMessages`、`getForumTopicIconStickers` |
| 更新与文件 | `getUpdates`、Webhook、`getFile`、`getMe`、`getUserProfilePhotos`、群查询便捷方法 |

```php
$bot->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);
$bot->pinChatMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
$bot->editMessageReplyMarkup([
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'reply_markup' => json_encode(['inline_keyboard' => []]),
]);
$bot->forwardMessage([
    'chat_id' => $to,
    'from_chat_id' => $from,
    'message_id' => $messageId,
]);
```

## Update 常用方法

| 方法 | 说明 |
|------|------|
| `getUpdateId()` | update_id |
| `getMessage()` | message / edited_message / channel_post 等 |
| `getCallbackQuery()` | 回调查询 |
| `getWebAppData()` | 键盘 Web App 回传（`message.web_app_data`） |
| `getInlineQuery()` | Inline 查询 |
| `getChosenInlineResult()` | 用户选择的 Inline 结果 |
| `getShippingQuery()` / `getPreCheckoutQuery()` | 支付相关查询 |
| `getPoll()` / `getPollAnswer()` | 投票更新 |
| `getMessageReaction()` / `getMessageReactionCount()` | 表情回应 |
| `getChatJoinRequest()` | 加群申请 |
| `getChatMemberUpdate()` | chat_member / my_chat_member |
| `getMyChatMember()` | 仅 my_chat_member（Bot 进退群） |
| `isMyChatMemberUpdate()` | 是否为 Bot 自身成员变更 |
| `getChatId()` / `getUserId()` | 会话与用户 ID |
| `getText()` | 消息文本、callback data 或 web_app_data.data |
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
