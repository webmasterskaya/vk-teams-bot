# VK Teams Bot SDK

[English version](README_en.md)

Неофициальный SDK для [VK Teams Bot API](https://teams.vk.com/botapi/) на PHP 8.2+, основанный на публичном API и поведении официального [SDK для Python](https://github.com/mail-ru-im/bot-python).

> Это независимый проект сообщества. Он не связан с VK, Mail.ru или их аффилированными лицами, не поддерживается, не авторизован и не одобрен ими.

## Установка

Выберите реализации PSR-18/PSR-17 и PSR-14, которые используются в вашем приложении. Например, для Guzzle и Symfony EventDispatcher выполните:

```bash
composer require webmasterskaya/vk-teams-bot guzzlehttp/guzzle symfony/event-dispatcher
```

Пакету требуются PHP 8.2 и расширение JSON.

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

Пример с кнопками и callback-запросами находится в файле [`examples/echo_bot.php`](examples/echo_bot.php).

По умолчанию SDK использует `https://api.icq.net/bot/v1`, как и оригинальный SDK для Python. Для локальной или выделенной установки MyTeam явно передайте адрес Bot API:

```php
$bot = new Bot(
    $token,
    apiUrlBase: 'https://myteam.example.com/bot/v1',
    eventDispatcher: $dispatcher,
);
```

При временных транспортных ошибках и HTTP-ошибках 5xx опрос автоматически возобновляет соединение. Для повторных попыток используется экспоненциальная задержка, ограниченная 30 секундами.

## API

Класс `Bot` предоставляет методы для конечных точек SDK для Python в стиле camelCase:

- сообщения: `sendText`, `sendFile`, `sendVoice`, `editText`, `deleteMessages`, `answerCallbackQuery`;
- чаты: `sendActions`, методы получения информации, управления участниками и модерации, а также методы для заголовка, описания, правил и закрепления сообщений;
- файлы: `getFileInfo`;
- треды: `threadsGetSubscribers`, `threadsAutosubscribe`, `threadsAdd`;
- бот и события: `selfGet`, `eventsGet`, `pollOnce`, `startPolling`, `stop`;
- локальные установки/MyTeam: `createChat`, `addChatMembers`, `deleteChatMembers` (для создания чатов и добавления участников передайте `myTeam: true` в конструктор бота).

Каждый метод API возвращает стандартный `Psr\Http\Message\ResponseInterface`. Для декодирования JSON-ответа используйте `json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)`.

### Клавиатура и форматирование

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

Для явного задания диапазонов форматирования используйте `Webmasterskaya\VkTeamsBot\Type\Format`. Как и в SDK для Python, параметры `parseMode` и `format` нельзя передавать одновременно.

### Диспетчер событий PSR-14

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

### TLS-сертификаты в Windows

Ошибка `cURL error 60` означает, что процесс PHP не может построить доверенную цепочку сертификатов. Не обходите эту проблему с помощью `verify => false`. Сначала проверьте, какую конфигурацию фактически загружает тот же исполняемый файл PHP:

```powershell
php --ini
php -i | findstr /I "curl.cainfo openssl.cafile"
```

Укажите для `curl.cainfo` и `openssl.cafile` в этом файле `php.ini` актуальный пакет корневых сертификатов в формате PEM, а затем перезапустите терминал, IDE или службу PHP. Транспорт также можно настроить явно, не привязывая сам SDK к Guzzle:

```php
use GuzzleHttp\Client;
use Webmasterskaya\VkTeamsBot\Bot;

$httpClient = new Client([
    'verify' => 'C:/php/extras/ssl/cacert.pem',
]);

$bot = new Bot($token, httpClient: $httpClient, eventDispatcher: $dispatcher);
```

Пример эхо-бота принимает тот же путь через переменную `VK_TEAMS_CA_BUNDLE`. Загружайте актуальный пакет корневых сертификатов Mozilla только со [страницы CA Extract проекта curl](https://curl.se/docs/caextract.html). Токен бота скрывается в сообщениях о транспортных исключениях до того, как они покинут SDK.

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
