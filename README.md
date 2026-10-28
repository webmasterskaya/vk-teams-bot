# VK Teams Bot SDK

[![CI](https://github.com/webmasterskaya/vk-teams-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/webmasterskaya/vk-teams-bot/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/webmasterskaya/vk-teams-bot/v/stable)](https://packagist.org/packages/webmasterskaya/vk-teams-bot)
[![PHP Version Require](https://poser.pugx.org/webmasterskaya/vk-teams-bot/require/php)](https://packagist.org/packages/webmasterskaya/vk-teams-bot)
[![License](https://poser.pugx.org/webmasterskaya/vk-teams-bot/license)](LICENSE)

[English version](README_en.md)

Неофициальный PHP SDK для [VK Teams Bot API](https://teams.vk.com/botapi/). Позволяет отправлять сообщения и файлы, управлять чатами и получать события через long polling.

> Это независимый проект сообщества. Он не связан с VK, Mail.ru или их аффилированными лицами, не поддерживается, не авторизован и не одобрен ими.

## Требования

- PHP 8.2 или новее с расширением JSON;
- [Composer](https://getcomposer.org/);
- реализации PSR-18/PSR-17 для HTTP-запросов и PSR-14 для событий.

SDK автоматически находит установленные PSR-реализации. В новом проекте проще всего использовать Guzzle и Symfony EventDispatcher, как показано ниже. Если в приложении уже есть совместимые реализации, устанавливать их повторно не нужно.

## Установка

Для нового проекта:

```bash
composer require webmasterskaya/vk-teams-bot guzzlehttp/guzzle symfony/event-dispatcher
```

Если HTTP-клиент, HTTP-фабрики и диспетчер событий уже установлены:

```bash
composer require webmasterskaya/vk-teams-bot
```

## Быстрый старт

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
$bot->startPolling(); // блокирующий цикл длительного опроса
```

Перед запуском задайте токен бота в переменной окружения `VK_TEAMS_BOT_TOKEN`. Полный пример с командами `/start` и `/id`, кнопками и callback-запросами находится в [`examples/echo_bot.php`](examples/echo_bot.php).

При временных транспортных ошибках и HTTP-ошибках 5xx polling автоматически восстанавливает соединение. Между повторными попытками используется экспоненциальная задержка до 30 секунд.

## Сторонний URL Bot API

По умолчанию SDK отправляет запросы на `https://api.icq.net/bot/v1`. Для локальной или выделенной установки MyTeam передайте свой адрес Bot API в `apiUrlBase`:

```php
$bot = new Bot(
    $token,
    apiUrlBase: 'https://myteam.example.com/bot/v1',
    eventDispatcher: $dispatcher,
);
```

Для методов `createChat()` и `addChatMembers()` локальной установки также включите режим MyTeam с помощью аргумента `myTeam: true`.

## Часто используемые методы

Все методы API возвращают `Psr\Http\Message\ResponseInterface`.

### Отправить сообщение

```php
$bot->sendText('chat-id', 'Привет!');
```

Ответить на сообщение можно с помощью именованного аргумента:

```php
$bot->sendText('chat-id', 'Готово', replyMsgId: 'message-id');
```

### Отправить файл

```php
$bot->sendFile(
    'chat-id',
    file: __DIR__ . '/report.pdf',
    caption: 'Отчёт',
);
```

Чтобы повторно отправить уже загруженный файл, передайте `fileId` вместо `file`.

### Изменить или удалить сообщение

```php
$bot->editText('chat-id', 'message-id', 'Исправленный текст');
$bot->deleteMessages('chat-id', 'message-id');
```

### Получить информацию о чате

```php
$response = $bot->getChatInfo('chat-id');
$chat = json_decode(
    (string) $response->getBody(),
    true,
    flags: JSON_THROW_ON_ERROR,
);
```

Другие доступные операции:

- сообщения: `sendVoice`, `answerCallbackQuery`;
- чаты: `sendActions`, получение участников и администраторов, модерация, изменение заголовка, описания и правил, закрепление сообщений;
- файлы: `getFileInfo`;
- треды: `threadsGetSubscribers`, `threadsAutosubscribe`, `threadsAdd`;
- бот и события: `selfGet`, `eventsGet`, `pollOnce`, `startPolling`, `stop`;
- MyTeam: `createChat`, `addChatMembers`, `deleteChatMembers`.

## Клавиатура и форматирование

```php
use Webmasterskaya\VkTeamsBot\Enum\ParseMode;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;
use Webmasterskaya\VkTeamsBot\Type\KeyboardButton;

$keyboard = (new InlineKeyboardMarkup())->row(
    new KeyboardButton('Открыть', url: 'https://example.com'),
    new KeyboardButton('Подтвердить', callbackData: 'confirm'),
);

$bot->sendText(
    'chat-id',
    '<b>Привет!</b>',
    inlineKeyboardMarkup: $keyboard,
    parseMode: ParseMode::Html,
);
```

Для явного задания диапазонов форматирования используйте `Webmasterskaya\VkTeamsBot\Type\Format`. Параметры `parseMode` и `format` нельзя передавать одновременно.

## События PSR-14

События передаются через `Psr\EventDispatcher\EventDispatcherInterface`. Если аргумент `eventDispatcher:` не указан, установленная реализация обнаруживается с помощью `psr-discovery/event-dispatcher-implementations`. Для слушателей приложения сначала настройте диспетчер, а затем передайте этот экземпляр боту.

Абстрактный `Webmasterskaya\VkTeamsBot\Event` содержит общие геттеры
`getEventId()`, `getType()`, `getPayload()` и `getBot()`. Для каждого типа Bot
API отправляется отдельный класс:

- `NewMessageEvent`, `EditedMessageEvent`, `DeletedMessageEvent`;
- `PinnedMessageEvent`, `UnpinnedMessageEvent`;
- `NewChatMembersEvent`, `LeftChatMembersEvent`, `ChangedChatInfoEvent`;
- `CallbackQueryEvent`.

Подписывайтесь на конкретный класс, чтобы обработчик получал только доступные
для него геттеры. PSR-14 не гарантирует вызов слушателя родительского класса,
поэтому для обработки всех событий зарегистрируйте нужные конкретные классы.

Можно использовать любой диспетчер, совместимый с PSR-14:

```php
$dispatcher = new YourPsr14Dispatcher();
$bot = new Bot($token, eventDispatcher: $dispatcher);
```

## HTTP-клиент PSR-18

SDK использует `Psr\Http\Client\ClientInterface` и возвращает ответы PSR-7. Если клиент или фабрики PSR-17 не переданы явно, `php-http/discovery` находит установленные реализации. Явно заданные аргументы `httpClient:`, `requestFactory:` и `streamFactory:` всегда имеют приоритет. Параметры транспорта, например прокси и тайм-ауты, настраиваются в переданной реализации PSR-18.

## Известные проблемы

Решения для ошибок TLS в Windows, отсутствующих PSR-реализаций и других типовых проблем собраны в [базе знаний известных проблем](docs/known-issues.md).

Если нужного случая там нет, [создайте issue](https://github.com/webmasterskaya/vk-teams-bot/issues/new) и приложите версию PHP, операционную систему и полный текст ошибки без токена бота.

## Разработка

```bash
composer test
composer lint
composer cs:check
composer cs:fix
composer analyse
composer phpstan
composer psalm
```

Команда `cs:check` проверяет код на соответствие PER Coding Style 2.0 и применимым правилам миграции PHP 8.2; `cs:fix` применяет те же правила. Набор тестов PHPUnit использует поддельный HTTP-клиент и не требует настоящего токена бота. Guzzle и Symfony EventDispatcher используются только при разработке для проверки механизма обнаружения реализаций. Команда `composer analyse` запускает PHPStan на уровне `max` и Psalm с уровнем ошибок 1.

## Лицензия

Проект распространяется на условиях лицензии MIT. Вы можете свободно использовать, копировать, изменять и распространять его, в том числе в коммерческих проектах, при условии сохранения уведомления об авторских правах и текста лицензии.

Подробнее см. в файле [LICENSE](LICENSE).
