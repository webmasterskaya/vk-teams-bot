<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Tests;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmasterskaya\VkTeamsBot\Bot;
use Webmasterskaya\VkTeamsBot\Enum\EventType;
use Webmasterskaya\VkTeamsBot\Enum\ParseMode;
use Webmasterskaya\VkTeamsBot\Enum\StyleType;
use Webmasterskaya\VkTeamsBot\Event;
use Webmasterskaya\VkTeamsBot\Event\EditedMessageEvent;
use Webmasterskaya\VkTeamsBot\Event\NewMessageEvent;
use Webmasterskaya\VkTeamsBot\Exception\ApiException;
use Webmasterskaya\VkTeamsBot\Exception\TransportException;
use Webmasterskaya\VkTeamsBot\Type\Format;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;
use Webmasterskaya\VkTeamsBot\Type\KeyboardButton;

final class BotTest extends TestCase
{
    public function testDefaultApiUrlMatchesReferenceSdk(): void
    {
        self::assertSame('https://api.icq.net/bot/v1', Bot::DEFAULT_API_URL);
    }

    public function testSendTextMapsOptionsToPsrRequest(): void
    {
        $http = new FakeClient();
        $bot = new Bot('prefix:42', 'https://example.test/v1/', 'Echo', '1.2.3', httpClient: $http);
        $keyboard = (new InlineKeyboardMarkup())->row(new KeyboardButton('Open', url: 'https://example.test'));

        $response = $bot->sendText(
            'chat',
            'Hello',
            replyMsgId: '10',
            inlineKeyboardMarkup: $keyboard,
            parseMode: ParseMode::Html,
        );

        $request = $http->requests[0];
        $query = self::query($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.test/v1/messages/sendText', self::uriWithoutQuery($request));
        self::assertSame('prefix:42', $query['token']);
        self::assertSame('HTML', $query['parseMode']);
        self::assertSame(
            '[[{"text":"Open","style":"primary","url":"https://example.test"}]]',
            $query['inlineKeyboardMarkup'],
        );
        self::assertSame('Echo/1.2.3 (uin=42) bot-php/' . Bot::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testFormatAndParseModeAreMutuallyExclusive(): void
    {
        $bot = new Bot('token', httpClient: new FakeClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use format and parseMode fields at the same time.');

        $bot->sendText('chat', 'text', parseMode: ParseMode::Html, format: new Format());
    }

    public function testFileUploadCreatesMultipartPsrRequest(): void
    {
        $http = new FakeClient();
        $bot = new Bot('token', httpClient: $http);

        $bot->sendFile('chat', file: __FILE__, caption: 'test');

        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('name="file"', (string) $request->getBody());
        self::assertStringContainsString('filename="BotTest.php"', (string) $request->getBody());
    }

    public function testEventsAreDispatchedThroughInjectedPsrDispatcher(): void
    {
        $http = new FakeClient();
        $http->responses[] = new Response(200, [], json_encode(['events' => [
            [
                'eventId' => 4,
                'type' => 'newMessage',
                'payload' => [
                    'msgId' => '1',
                    'text' => '/ping ok',
                    'chat' => ['chatId' => 'c', 'type' => 'private'],
                ],
            ],
            ['eventId' => 7, 'type' => 'editedMessage', 'payload' => ['msgId' => '1', 'text' => 'edited']],
        ]], JSON_THROW_ON_ERROR));
        $dispatcher = new EventDispatcher();
        $events = [];
        $listener = static function (Event $event) use (&$events): void {
            $events[] = $event;
        };
        $dispatcher->addListener(NewMessageEvent::class, $listener);
        $dispatcher->addListener(EditedMessageEvent::class, $listener);
        $bot = new Bot('token', httpClient: $http, eventDispatcher: $dispatcher);

        self::assertSame(2, $bot->pollOnce(1));
        self::assertCount(2, $events);
        self::assertInstanceOf(NewMessageEvent::class, $events[0]);
        self::assertSame(EventType::NewMessage, $events[0]->getType());
        self::assertSame(4, $events[0]->getEventId());
        self::assertSame('/ping ok', $events[0]->getText());
        self::assertSame('c', $events[0]->getChatId());
        self::assertSame($bot, $events[0]->getBot());
        self::assertInstanceOf(EditedMessageEvent::class, $events[1]);
        self::assertSame(7, $bot->lastEventId());
        self::assertSame('0', self::query($http->requests[0])['lastEventId']);
    }

    public function testDevelopmentImplementationsAreDiscovered(): void
    {
        $bot = new Bot('token');

        self::assertInstanceOf(\GuzzleHttp\Client::class, $bot->httpClient());
        self::assertInstanceOf(EventDispatcherInterface::class, $bot->eventDispatcher());
        self::assertInstanceOf(EventDispatcher::class, $bot->eventDispatcher());
    }

    public function testTransportExceptionDoesNotExposeToken(): void
    {
        $http = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('Request failed for ' . (string) $request->getUri()) extends \RuntimeException implements ClientExceptionInterface {};
            }
        };
        $bot = new Bot('prefix:secret', httpClient: $http);

        try {
            $bot->selfGet();
            self::fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            self::assertStringContainsString('token=[REDACTED]', $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testMalformedApiResponseProvidesSafeDiagnostics(): void
    {
        $http = new FakeClient();
        $response = new Response(502, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>');
        $http->responses[] = $response;
        $bot = new Bot('token', httpClient: $http);

        try {
            $bot->pollOnce(1);
            self::fail('Expected malformed response to throw.');
        } catch (ApiException $exception) {
            self::assertSame($response, $exception->response);
            self::assertSame(
                'Bot API returned malformed JSON (HTTP 502, Content-Type text/html, 24 bytes): Syntax error',
                $exception->getMessage(),
            );
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testTypesSerializeUnicodeAndFormattingRanges(): void
    {
        $format = (new Format())
            ->add(StyleType::Bold, 0, 4)
            ->add('link', 5, 4, ['url' => 'https://vk.com']);

        self::assertSame(
            '{"bold":[{"offset":0,"length":4}],"link":[{"offset":5,"length":4,"url":"https://vk.com"}]}',
            $format->toJson(),
        );
        self::assertSame('{"text":"Да","style":"primary"}', (new KeyboardButton('Да'))->toJson());
    }

    public function testChatThreadAndMyTeamMethodsPreserveEndpointContract(): void
    {
        $http = new FakeClient();
        $bot = new Bot('token', 'https://api.test', httpClient: $http, myTeam: true);

        $bot->getChatInfo('c');
        $bot->chatBlockUser('c', 'u', true);
        $bot->sendActions('c', []);
        $bot->threadsGetSubscribers('t', pageSize: 100);
        $bot->threadsAutosubscribe('c', true, true);
        $bot->createChat('name', members: ['a@example.test'], public: true);
        $bot->deleteChatMembers('c', ['a@example.test']);

        self::assertSame('https://api.test/chats/getInfo', self::uriWithoutQuery($http->requests[0]));
        self::assertSame('true', self::query($http->requests[1])['delLastMessages']);
        self::assertSame('', self::query($http->requests[2])['actions']);
        self::assertSame('100', self::query($http->requests[3])['pageSize']);
        self::assertSame('true', self::query($http->requests[4])['withExisting']);
        self::assertSame('[{"sn":"a@example.test"}]', self::query($http->requests[5])['members']);
        self::assertSame('https://api.test/chats/members/delete', self::uriWithoutQuery($http->requests[6]));
    }

    /** @return array<string, string> */
    private static function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        foreach ($query as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                self::fail('Expected query parameters to contain strings only.');
            }
        }

        /** @var array<string, string> $query */
        return $query;
    }

    private static function uriWithoutQuery(RequestInterface $request): string
    {
        return (string) $request->getUri()->withQuery('');
    }
}
