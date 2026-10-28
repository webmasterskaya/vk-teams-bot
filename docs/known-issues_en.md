# Known issues knowledge base

[Русская версия](known-issues.md)

This document collects common installation and runtime problems for VK Teams Bot SDK. If your error is not covered here, [open an issue](https://github.com/webmasterskaya/vk-teams-bot/issues/new).

Remove the bot token before publishing logs. The SDK redacts it from transport exceptions, but it may still appear in application code, dumps, or HTTP client logs.

## `cURL error 60` on Windows

### Symptom

A request fails with `SSL certificate problem: unable to get local issuer certificate` or `cURL error 60`.

### Cause

The PHP process cannot build a trusted certificate chain because its CA bundle is missing, outdated, or configured in a different `php.ini`.

### Solution

Do not disable certificate verification with `verify => false`.

Check which configuration is loaded by the same PHP executable that runs the bot:

```powershell
php --ini
php -i | findstr /I "curl.cainfo openssl.cafile"
```

Download a current Mozilla CA bundle from the [curl CA Extract page](https://curl.se/docs/caextract.html). Set `curl.cainfo` and `openssl.cafile` in the loaded `php.ini` to the absolute path of that PEM file, then restart the terminal, IDE, or PHP service.

The path can also be configured directly in Guzzle:

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

The `examples/echo_bot.php` example accepts the same path through the `VK_TEAMS_CA_BUNDLE` environment variable.

## HTTP client or HTTP factory not found

### Symptom

Creating `Bot` throws a discovery exception saying that no PSR-18 or PSR-17 implementation was found.

### Cause

The `psr/http-client` and `psr/http-factory` packages contain interfaces only. The SDK also needs compatible implementations.

### Solution

Install an HTTP client with PSR-17 factories, such as Guzzle:

```bash
composer require guzzlehttp/guzzle
```

Alternatively, pass compatible `httpClient`, `requestFactory`, and `streamFactory` instances to the `Bot` constructor explicitly.

## PSR-14 event dispatcher not found

### Symptom

Creating `Bot` fails with `No PSR-14 event dispatcher implementation was discovered.`

### Cause

The PSR-14 interface is installed, but its implementation is missing.

### Solution

Install a compatible dispatcher, such as Symfony EventDispatcher:

```bash
composer require symfony/event-dispatcher
```

If your application already provides a dispatcher, configure its listeners and inject it explicitly:

```php
$bot = new Bot($token, eventDispatcher: $dispatcher);
```
