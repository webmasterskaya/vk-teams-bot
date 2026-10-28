<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webmasterskaya\VkTeamsBot\Bot;
use Webmasterskaya\VkTeamsBot\Enum\EventType;
use Webmasterskaya\VkTeamsBot\Event\CallbackQueryEvent;
use Webmasterskaya\VkTeamsBot\Event\ChangedChatInfoEvent;
use Webmasterskaya\VkTeamsBot\Event\DeletedMessageEvent;
use Webmasterskaya\VkTeamsBot\Event\EditedMessageEvent;
use Webmasterskaya\VkTeamsBot\Event\EventFactory;
use Webmasterskaya\VkTeamsBot\Event\LeftChatMembersEvent;
use Webmasterskaya\VkTeamsBot\Event\NewChatMembersEvent;
use Webmasterskaya\VkTeamsBot\Event\NewMessageEvent;
use Webmasterskaya\VkTeamsBot\Event\PinnedMessageEvent;
use Webmasterskaya\VkTeamsBot\Event\UnpinnedMessageEvent;

final class EventTest extends TestCase
{
    /** @return iterable<string, array{EventType, class-string}> */
    public static function eventClasses(): iterable
    {
        yield 'new message' => [EventType::NewMessage, NewMessageEvent::class];
        yield 'edited message' => [EventType::EditedMessage, EditedMessageEvent::class];
        yield 'deleted message' => [EventType::DeletedMessage, DeletedMessageEvent::class];
        yield 'pinned message' => [EventType::PinnedMessage, PinnedMessageEvent::class];
        yield 'unpinned message' => [EventType::UnpinnedMessage, UnpinnedMessageEvent::class];
        yield 'new chat members' => [EventType::NewChatMembers, NewChatMembersEvent::class];
        yield 'left chat members' => [EventType::LeftChatMembers, LeftChatMembersEvent::class];
        yield 'changed chat info' => [EventType::ChangedChatInfo, ChangedChatInfoEvent::class];
        yield 'callback query' => [EventType::CallbackQuery, CallbackQueryEvent::class];
    }

    /** @param class-string $expectedClass */
    #[DataProvider('eventClasses')]
    public function testFactoryCreatesDedicatedClass(EventType $type, string $expectedClass): void
    {
        $bot = new Bot('token', httpClient: new FakeClient());
        $event = EventFactory::create($type, 42, [], $bot);

        self::assertInstanceOf($expectedClass, $event);
        self::assertSame($type, $event->getType());
        self::assertSame(42, $event->getEventId());
        self::assertSame($bot, $event->getBot());
    }

    public function testNewMessageGettersReadPayload(): void
    {
        $bot = new Bot('token', httpClient: new FakeClient());
        $payload = [
            'msgId' => 'message-1',
            'chat' => ['chatId' => 'chat-1', 'type' => 'private', 'title' => 'Chat'],
            'from' => ['userId' => 'user-1'],
            'timestamp' => 1_546_290_000,
            'text' => 'Hello!',
            'format' => ['bold' => []],
            'parts' => [['type' => 'sticker']],
        ];

        $event = new NewMessageEvent(1, $payload, $bot);

        self::assertSame('message-1', $event->getMessageId());
        self::assertSame('chat-1', $event->getChatId());
        self::assertSame('private', $event->getChatType());
        self::assertSame('Chat', $event->getChatTitle());
        self::assertSame(['userId' => 'user-1'], $event->getAuthor());
        self::assertSame('user-1', $event->getAuthorId());
        self::assertSame(1_546_290_000, $event->getTimestamp());
        self::assertSame('Hello!', $event->getText());
        self::assertSame(['bold' => []], $event->getFormat());
        self::assertSame([['type' => 'sticker']], $event->getParts());
        self::assertSame($payload, $event->getPayload());
    }

    public function testCallbackQueryGettersReadNestedMessage(): void
    {
        $bot = new Bot('token', httpClient: new FakeClient());
        $event = new CallbackQueryEvent(2, [
            'queryId' => 'SVR:user-1:query',
            'callbackData' => 'ping',
            'from' => ['userId' => 'user-1'],
            'message' => [
                'msgId' => 'message-1',
                'text' => 'Button',
                'timestamp' => 1_546_290_000,
                'chat' => ['chatId' => 'chat-1', 'type' => 'private'],
            ],
        ], $bot);

        self::assertSame('SVR:user-1:query', $event->getQueryId());
        self::assertSame('ping', $event->getCallbackData());
        self::assertSame(['userId' => 'user-1'], $event->getFrom());
        self::assertSame('user-1', $event->getMessageAuthor());
        self::assertSame('message-1', $event->getMessageId());
        self::assertSame('Button', $event->getText());
        self::assertSame(1_546_290_000, $event->getTimestamp());
        self::assertSame('chat-1', $event->getChatId());
        self::assertSame('private', $event->getChatType());
    }
}
