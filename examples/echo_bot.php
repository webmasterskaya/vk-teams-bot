<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmasterskaya\VkTeamsBot\Bot;
use Webmasterskaya\VkTeamsBot\Enum\ChatType;
use Webmasterskaya\VkTeamsBot\Event\CallbackQueryEvent;
use Webmasterskaya\VkTeamsBot\Event\NewMessageEvent;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;
use Webmasterskaya\VkTeamsBot\Type\KeyboardButton;

$token = getenv('VK_TEAMS_BOT_TOKEN');
if (!is_string($token) || $token === '') {
    throw new RuntimeException('Set VK_TEAMS_BOT_TOKEN.');
}

$httpClientOptions = [];
$caBundle = getenv('VK_TEAMS_CA_BUNDLE');
if (is_string($caBundle) && $caBundle !== '') {
    if (!is_file($caBundle) || !is_readable($caBundle)) {
        throw new RuntimeException("CA bundle is not readable: {$caBundle}");
    }

    $httpClientOptions['verify'] = $caBundle;
}

$httpClient = new Client($httpClientOptions);
$dispatcher = new EventDispatcher();

$dispatcher->addListener(CallbackQueryEvent::class, static function (CallbackQueryEvent $event): void {
    if ($event->getCallbackData() === 'ping') {
        $event->getBot()->answerCallbackQuery($event->getQueryId() ?? '', 'Pong!');
    }
});

$dispatcher->addListener(NewMessageEvent::class, static function (NewMessageEvent $event): void {
    $command = trim($event->getText() ?? '');

    if ($command === '/id') {
        $chatType = ChatType::tryFrom($event->getChatType() ?? '');
        $id = match ($chatType) {
            ChatType::Private => $event->getAuthorId() ?? $event->getChatId(),
            ChatType::Group, ChatType::Channel, null => $event->getChatId(),
        };
        $entity = match ($chatType) {
            ChatType::Private => 'пользователя',
            ChatType::Group => 'группы',
            ChatType::Channel => 'канала',
            null => 'чата',
        };
        $message = $id === null
            ? 'Не удалось определить ID.'
            : sprintf('ID %s: %s', $entity, $id);

        $event->getBot()->sendText($event->getChatId() ?? '', $message);

        return;
    }

    if ($command === '/start') {
        $keyboard = (new InlineKeyboardMarkup())->row(
            new KeyboardButton('VK Teams', url: 'https://teams.vk.com'),
            new KeyboardButton('Ping', callbackData: 'ping'),
        );
        $event->getBot()->sendText(
            $event->getChatId() ?? '',
            'Привет! Пришлите мне сообщение.',
            inlineKeyboardMarkup: $keyboard,
        );

        return;
    }

    $event->getBot()->sendText($event->getChatId() ?? '', $event->getText() ?? '');
});

$bot = new Bot(
    $token,
    name: 'echo',
    version: '1.0.0',
    httpClient: $httpClient,
    eventDispatcher: $dispatcher,
);
$bot->startPolling();
