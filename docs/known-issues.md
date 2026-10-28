# База знаний известных проблем

[English version](known-issues_en.md)

В этом документе собраны типовые проблемы при установке и запуске VK Teams Bot SDK. Если решения для вашей ошибки здесь нет, [создайте issue](https://github.com/webmasterskaya/vk-teams-bot/issues/new).

Перед публикацией логов удалите из них токен бота. SDK скрывает токен в транспортных исключениях, но он всё ещё может присутствовать в пользовательском коде, дампах или логах HTTP-клиента.

## `cURL error 60` в Windows

### Симптом

Запрос завершается ошибкой `SSL certificate problem: unable to get local issuer certificate` или `cURL error 60`.

### Причина

Процесс PHP не может построить доверенную цепочку сертификатов: пакет корневых сертификатов отсутствует, устарел или указан не в том `php.ini`.

### Решение

Не отключайте проверку сертификатов с помощью `verify => false`.

Проверьте, какую конфигурацию загружает тот исполняемый файл PHP, которым запускается бот:

```powershell
php --ini
php -i | findstr /I "curl.cainfo openssl.cafile"
```

Скачайте актуальный пакет корневых сертификатов Mozilla со [страницы CA Extract проекта curl](https://curl.se/docs/caextract.html). Укажите абсолютный путь к PEM-файлу для `curl.cainfo` и `openssl.cafile` в загруженном `php.ini`, затем перезапустите терминал, IDE или службу PHP.

Путь также можно задать непосредственно в настройках Guzzle:

```php
use GuzzleHttp\Client;
use Webmasterskaya\VkTeamsBot\Bot;

$httpClient = new Client([
    'verify' => 'C:/php/extras/ssl/cacert.pem',
]);

$bot = new Bot(
    $token,
    httpClient: $httpClient,
    eventDispatcher: $dispatcher,
);
```

Пример `examples/echo_bot.php` принимает этот путь через переменную окружения `VK_TEAMS_CA_BUNDLE`.

## HTTP-клиент или HTTP-фабрика не найдены

### Симптом

При создании `Bot` возникает исключение discovery-компонента о том, что реализация PSR-18 или PSR-17 не найдена.

### Причина

Пакеты `psr/http-client` и `psr/http-factory` содержат только интерфейсы. SDK требуется совместимая реализация.

### Решение

Установите HTTP-клиент с PSR-17-фабриками, например Guzzle:

```bash
composer require guzzlehttp/guzzle
```

Либо явно передайте совместимые `httpClient`, `requestFactory` и `streamFactory` в конструктор `Bot`.

## Диспетчер событий PSR-14 не найден

### Симптом

При создании `Bot` возникает ошибка `No PSR-14 event dispatcher implementation was discovered.`

### Причина

В проекте установлен интерфейс PSR-14, но отсутствует его реализация.

### Решение

Установите совместимый диспетчер, например Symfony EventDispatcher:

```bash
composer require symfony/event-dispatcher
```

Если диспетчер в приложении уже есть, настройте слушателей и передайте его явно:

```php
$bot = new Bot($token, eventDispatcher: $dispatcher);
```
