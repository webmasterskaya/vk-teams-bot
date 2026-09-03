# VK Teams Bot SDK

Unofficial PHP 8.2+ SDK for the [VK Teams Bot API](https://teams.vk.com/botapi/), based on the public API and behavior of the official [Python SDK](https://github.com/mail-ru-im/bot-python).

> This is an independent community project. It is not affiliated with,
> maintained, authorized, or endorsed by VK, Mail.ru, or their affiliates.

## Installation

Choose PSR-18/PSR-17 and PSR-14 implementations used by your application. For
example, with Guzzle and Symfony EventDispatcher:

```bash
composer require webmasterskaya/vk-teams-bot guzzlehttp/guzzle symfony/event-dispatcher
```

The package requires PHP 8.2 and JSON.

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Webmasterskaya\VkTeamsBot\Bot;
use Webmasterskaya\VkTeamsBot\Event\NewMessageEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(NewMessageEvent::class, static function (NewMessageEvent $event): void {
    $event->getBot()->sendText($event->getChatId() ?? '', $event->getText() ?? '');
});

$bot = new Bot(getenv('VK_TEAMS_BOT_TOKEN'), eventDispatcher: $dispatcher);
$bot->startPolling(); // blocking long-polling loop
```

See [`examples/echo_bot.php`](examples/echo_bot.php) for buttons and callback queries.

By default the SDK uses `https://api.icq.net/bot/v1`, as does the original
Python SDK. For an on-premises or dedicated MyTeam installation, pass its Bot
API endpoint explicitly:

```php
$bot = new Bot(
    $token,
    apiUrlBase: 'https://myteam.example.com/bot/v1',
    eventDispatcher: $dispatcher,
);
```

Polling automatically reconnects after transient transport and HTTP 5xx
failures, using an exponential delay capped at 30 seconds.

## API

`Bot` exposes the Python SDK endpoints in PHP camelCase:

- messages: `sendText`, `sendFile`, `sendVoice`, `editText`, `deleteMessages`, `answerCallbackQuery`;
- chats: `sendActions`, chat info/member/moderation methods, title/about/rules, pin/unpin;
- files: `getFileInfo`;
- threads: `threadsGetSubscribers`, `threadsAutosubscribe`, `threadsAdd`;
- bot and events: `selfGet`, `eventsGet`, `pollOnce`, `startPolling`, `stop`;
- on-prem/MyTeam: `createChat`, `addChatMembers`, `deleteChatMembers` (construct the bot with `myTeam: true` for create/add).

Every API method returns the standard `Psr\Http\Message\ResponseInterface`.
Decode a JSON response with `json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)`.

### Keyboard and formatting

```php
use Webmasterskaya\VkTeamsBot\Enum\ParseMode;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;
use Webmasterskaya\VkTeamsBot\Type\KeyboardButton;

$keyboard = (new InlineKeyboardMarkup())->row(
    new KeyboardButton('Open', url: 'https://example.com'),
    new KeyboardButton('Confirm', callbackData: 'confirm'),
);

$bot->sendText('chat-id', '<b>Hello</b>', inlineKeyboardMarkup: $keyboard, parseMode: ParseMode::Html);
```

For explicit formatting ranges use `Webmasterskaya\VkTeamsBot\Type\Format`. As in the Python SDK, `parseMode` and `format` cannot be supplied together.

### PSR-14 event dispatcher

Events are dispatched through `Psr\EventDispatcher\EventDispatcherInterface`.
When `eventDispatcher:` is omitted, an installed implementation is discovered
with `psr-discovery/event-dispatcher-implementations`. For application listeners,
configure the dispatcher first and inject that configured instance. Every
event extends the abstract `Webmasterskaya\VkTeamsBot\Event`, which exposes
`getEventId()`, `getType()`, `getPayload()` and `getBot()`.

Each Bot API event type has a dedicated class: `NewMessageEvent`,
`EditedMessageEvent`, `DeletedMessageEvent`, `PinnedMessageEvent`,
`UnpinnedMessageEvent`, `NewChatMembersEvent`, `LeftChatMembersEvent`,
`ChangedChatInfoEvent` and `CallbackQueryEvent`. Subscribe to a concrete class
to get only its applicable getters. PSR-14 does not guarantee parent-class
listener resolution, so register the required concrete classes to observe
multiple event types.

You may use any PSR-14 compatible dispatcher:

```php
$dispatcher = new YourPsr14Dispatcher();
$bot = new Bot($token, eventDispatcher: $dispatcher);
```

## PSR-18 HTTP client

The SDK uses `Psr\Http\Client\ClientInterface` and returns PSR-7 responses.
When no client or PSR-17 factories are injected, `php-http/discovery` finds
installed implementations. Explicit `httpClient:`, `requestFactory:` and
`streamFactory:` arguments always take priority. Configure transport-specific
options such as proxies and timeouts on the injected PSR-18 implementation.

### TLS certificates on Windows

`cURL error 60` means that the PHP process cannot build a trusted certificate
chain. Do not work around it with `verify => false`. First check which
configuration the same PHP executable actually loads:

```powershell
php --ini
php -i | findstr /I "curl.cainfo openssl.cafile"
```

Point both `curl.cainfo` and `openssl.cafile` in that `php.ini` to a current
PEM CA bundle, then restart the terminal, IDE or PHP service. A transport can
also be configured explicitly without coupling the SDK itself to Guzzle:

```php
use GuzzleHttp\Client;
use Webmasterskaya\VkTeamsBot\Bot;

$httpClient = new Client([
    'verify' => 'C:/php/extras/ssl/cacert.pem',
]);

$bot = new Bot($token, httpClient: $httpClient, eventDispatcher: $dispatcher);
```

The echo example accepts the same path through `VK_TEAMS_CA_BUNDLE`. Download
the current Mozilla CA bundle only from the [curl CA Extract page](https://curl.se/docs/caextract.html).
Transport exception messages redact the bot token before they leave the SDK.

## Development

```bash
composer test
composer lint
composer cs:check
composer cs:fix
composer analyse
composer phpstan
composer psalm
```

`cs:check` validates the code against PER Coding Style 2.0 and the applicable
PHP 8.2 migration rules; `cs:fix` applies the same rules. The PHPUnit suite uses
a fake HTTP client and never requires a real bot token. Guzzle and Symfony
EventDispatcher are development-only implementations used to exercise discovery.
`composer analyse` runs PHPStan at level `max` and Psalm at error level 1.
