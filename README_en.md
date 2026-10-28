# VK Teams Bot SDK

[![CI](https://github.com/webmasterskaya/vk-teams-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/webmasterskaya/vk-teams-bot/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/webmasterskaya/vk-teams-bot/v/stable)](https://packagist.org/packages/webmasterskaya/vk-teams-bot)
[![PHP Version Require](https://poser.pugx.org/webmasterskaya/vk-teams-bot/require/php)](https://packagist.org/packages/webmasterskaya/vk-teams-bot)
[![License](https://poser.pugx.org/webmasterskaya/vk-teams-bot/license)](LICENSE)

[Русская версия](README.md)

Unofficial PHP SDK for the [VK Teams Bot API](https://teams.vk.com/botapi/). It sends messages and files, manages chats, and receives events through long polling.

> This is an independent community project. It is not affiliated with,
> maintained, authorized, or endorsed by VK, Mail.ru, or their affiliates.

## Requirements

- PHP 8.2 or newer with the JSON extension;
- [Composer](https://getcomposer.org/);
- PSR-18/PSR-17 implementations for HTTP requests and a PSR-14 event dispatcher.

The SDK discovers installed PSR implementations automatically. For a new project, the easiest option is Guzzle with Symfony EventDispatcher, as shown below. If your application already provides compatible implementations, you do not need to install them again.

## Installation

For a new project:

```bash
composer require webmasterskaya/vk-teams-bot guzzlehttp/guzzle symfony/event-dispatcher
```

If an HTTP client, HTTP factories, and an event dispatcher are already installed:

```bash
composer require webmasterskaya/vk-teams-bot
```

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

Set the bot token in the `VK_TEAMS_BOT_TOKEN` environment variable before running the script. See [`examples/echo_bot.php`](examples/echo_bot.php) for a complete example with the `/start` and `/id` commands, buttons, and callback queries.

Polling reconnects automatically after transient transport and HTTP 5xx errors, using an exponential retry delay capped at 30 seconds.

## Custom Bot API URL

The SDK sends requests to `https://api.icq.net/bot/v1` by default. For an on-premises or dedicated MyTeam installation, pass its Bot API endpoint through `apiUrlBase`:

```php
$bot = new Bot(
    $token,
    apiUrlBase: 'https://myteam.example.com/bot/v1',
    eventDispatcher: $dispatcher,
);
```

For the on-premises `createChat()` and `addChatMembers()` methods, also enable MyTeam mode with the `myTeam: true` argument.

## Common methods

All API methods return `Psr\Http\Message\ResponseInterface`.

### Send a message

```php
$bot->sendText('chat-id', 'Hello!');
```

Use a named argument to reply to a message:

```php
$bot->sendText('chat-id', 'Done', replyMsgId: 'message-id');
```

### Send a file

```php
$bot->sendFile(
    'chat-id',
    file: __DIR__ . '/report.pdf',
    caption: 'Report',
);
```

To send a previously uploaded file again, pass its `fileId` instead of `file`.

### Edit or delete a message

```php
$bot->editText('chat-id', 'message-id', 'Corrected text');
$bot->deleteMessages('chat-id', 'message-id');
```

### Get chat information

```php
$response = $bot->getChatInfo('chat-id');
$chat = json_decode(
    (string) $response->getBody(),
    true,
    flags: JSON_THROW_ON_ERROR,
);
```

Other available operations include:

- messages: `sendVoice`, `answerCallbackQuery`;
- chats: `sendActions`, member and administrator lookup, moderation, title/about/rules updates, and message pinning;
- files: `getFileInfo`;
- threads: `threadsGetSubscribers`, `threadsAutosubscribe`, `threadsAdd`;
- bot and events: `selfGet`, `eventsGet`, `pollOnce`, `startPolling`, `stop`;
- MyTeam: `createChat`, `addChatMembers`, `deleteChatMembers`.

## Keyboard and formatting

```php
use Webmasterskaya\VkTeamsBot\Enum\ParseMode;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;
use Webmasterskaya\VkTeamsBot\Type\KeyboardButton;

$keyboard = (new InlineKeyboardMarkup())->row(
    new KeyboardButton('Open', url: 'https://example.com'),
    new KeyboardButton('Confirm', callbackData: 'confirm'),
);

$bot->sendText(
    'chat-id',
    '<b>Hello!</b>',
    inlineKeyboardMarkup: $keyboard,
    parseMode: ParseMode::Html,
);
```

For explicit formatting ranges, use `Webmasterskaya\VkTeamsBot\Type\Format`. The `parseMode` and `format` arguments cannot be supplied together.

## PSR-14 events

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

## Known issues

Solutions for Windows TLS errors, missing PSR implementations, and other common problems are collected in the [known issues knowledge base](docs/known-issues_en.md).

If your problem is not covered there, [open an issue](https://github.com/webmasterskaya/vk-teams-bot/issues/new) with your PHP version, operating system, and the complete error message with the bot token removed.

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

## License

This project is licensed under the MIT License. You may freely use, copy, modify, and distribute it, including in commercial projects, provided that the copyright notice and license text are preserved.

See [LICENSE](LICENSE) for more information.
