<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use RuntimeException;
use Throwable;
use ValueError;
use Webmasterskaya\VkTeamsBot\Enum\EventType;
use Webmasterskaya\VkTeamsBot\Enum\ParseMode;
use Webmasterskaya\VkTeamsBot\Event\EventFactory;
use Webmasterskaya\VkTeamsBot\Exception\ApiException;
use Webmasterskaya\VkTeamsBot\Exception\InvalidTokenException;
use Webmasterskaya\VkTeamsBot\Exception\TransportException;
use Webmasterskaya\VkTeamsBot\Type\Format;
use Webmasterskaya\VkTeamsBot\Type\InlineKeyboardMarkup;

final class Bot
{
    public const VERSION = '1.1.0';
    public const DEFAULT_API_URL = 'https://api.icq.net/bot/v1';

    public readonly string $uin;
    private bool $running = false;
    private int $lastEventId = 0;

    /**
     * @throws \PsrDiscovery\Exceptions\SupportPackageNotFoundException
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $token,
        private readonly string $apiUrlBase = self::DEFAULT_API_URL,
        private readonly ?string $name = null,
        private readonly ?string $version = null,
        private readonly int $pollTime = 60,
        ?ClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly bool $myTeam = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->eventDispatcher = $eventDispatcher ?? Discover::eventDispatcher(singleton: true)
            ?? throw new RuntimeException('No PSR-14 event dispatcher implementation was discovered.');
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = $logger ?? new NullLogger();
        $tokenParts = explode(':', $token);
        $this->uin = end($tokenParts);
    }

    private readonly ClientInterface $httpClient;
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly LoggerInterface $logger;

    public function httpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function eventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function userAgent(): string
    {
        return sprintf(
            '%s/%s (uin=%s) bot-php/%s',
            $this->name ?? 'bot',
            $this->version ?? 'base',
            $this->uin,
            self::VERSION
        );
    }

    public function lastEventId(): int
    {
        return $this->lastEventId;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @throws \Random\RandomException
     */
    public function eventsGet(?int $pollTime = null, ?int $lastEventId = null): ResponseInterface
    {
        $pollTime ??= $this->pollTime;

        return $this->request('events/get', [
            'pollTime' => $pollTime,
            'lastEventId' => $lastEventId ?? $this->lastEventId,
        ]);
    }

    /** @phpstan-impure
     * @throws ApiException
     * @throws \Random\RandomException
     * @throws TransportException
     */
    public function pollOnce(?int $pollTime = null): int
    {
        $response = $this->eventsGet($pollTime);
        $data = $this->decodeResponse($response);
        if (($data['description'] ?? null) === 'Invalid token') {
            throw new InvalidTokenException($response);
        }
        if ($response->getStatusCode() >= 400) {
            throw new ApiException($response);
        }
        $count = 0;
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            return 0;
        }
        foreach (array_filter($events, 'is_array') as $rawEvent) {
            $eventId = 0;
            if (isset($rawEvent['eventId']) && is_scalar($rawEvent['eventId'])) {
                $eventId = (int) $rawEvent['eventId'];
                $this->lastEventId = max($this->lastEventId, $eventId);
            }

            if (!isset($rawEvent['type'], $rawEvent['payload'])
                || !is_string($rawEvent['type'])
                || !is_array($rawEvent['payload'])) {
                continue;
            }
            try {
                $type = EventType::from($rawEvent['type']);
            } catch (ValueError) {
                continue;
            }
            /** @var array<string, mixed> $payload */
            $payload = $rawEvent['payload'];
            $this->eventDispatcher->dispatch(EventFactory::create($type, $eventId, $payload, $this));
            ++$count;
        }

        return $count;
    }

    /** Blocking long-polling loop. Call stop() from a handler or signal callback to end it. */
    public function startPolling(?callable $onError = null): void
    {
        if ($this->running) {
            return;
        }
        $this->running = true;
        $consecutiveErrors = 0;
        /** @psalm-suppress RedundantCondition The flag can be changed by an event listener. */
        while ($this->running) {
            try {
                $this->pollOnce();
                $consecutiveErrors = 0;
            } catch (Throwable $error) {
                $retryDelay = $error instanceof InvalidTokenException
                    ? 5
                    : min(30, 1 << min($consecutiveErrors, 5));
                $this->logger->error('Polling failed.', [
                    'exception' => $error,
                    'retry_delay' => $retryDelay,
                    'consecutive_errors' => $consecutiveErrors + 1,
                ]);
                if ($onError !== null) {
                    $onError($error, $this);
                }
                ++$consecutiveErrors;
                sleep($retryDelay);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function idle(): void
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([2, 15] as $signal) {
                pcntl_signal($signal, fn() => $this->stop());
            }
        }
        while ($this->running) {
            usleep(100_000);
        }
    }

    /**
     * @throws \Random\RandomException
     * @throws TransportException
     */
    public function selfGet(): ResponseInterface
    {
        return $this->request('self/get');
    }

    /**
     * @param InlineKeyboardMarkup|array<array-key, mixed>|string|null $inlineKeyboardMarkup
     * @param Format|array<array-key, mixed>|string|null $format
     * @throws \JsonException|\Random\RandomException
     */
    public function sendText(
        string $chatId,
        string $text,
        ?string $replyMsgId = null,
        ?string $forwardChatId = null,
        ?string $forwardMsgId = null,
        InlineKeyboardMarkup|array|string|null $inlineKeyboardMarkup = null,
        ParseMode|string|null $parseMode = null,
        Format|array|string|null $format = null,
    ): ResponseInterface {
        $this->assertTextFormat($parseMode, $format);

        return $this->request('messages/sendText', [
            'chatId' => $chatId,
            'text' => $text,
            'replyMsgId' => $replyMsgId,
            'forwardChatId' => $forwardChatId,
            'forwardMsgId' => $forwardMsgId,
            'inlineKeyboardMarkup' => $this->toJson($inlineKeyboardMarkup),
            'parseMode' => $this->parseMode($parseMode),
            'format' => $this->toJson($format),
        ]);
    }

    /**
     * @param InlineKeyboardMarkup|array<array-key, mixed>|string|null $inlineKeyboardMarkup
     * @param Format|array<array-key, mixed>|string|null $format
     * @throws \JsonException|\Random\RandomException
     */
    public function sendFile(
        string $chatId,
        ?string $fileId = null,
        ?string $file = null,
        ?string $caption = null,
        ?string $replyMsgId = null,
        ?string $forwardChatId = null,
        ?string $forwardMsgId = null,
        InlineKeyboardMarkup|array|string|null $inlineKeyboardMarkup = null,
        ParseMode|string|null $parseMode = null,
        Format|array|string|null $format = null,
    ): ResponseInterface {
        $this->assertTextFormat($parseMode, $format);

        return $this->request('messages/sendFile', [
            'chatId' => $chatId,
            'fileId' => $fileId,
            'caption' => $caption,
            'replyMsgId' => $replyMsgId,
            'forwardChatId' => $forwardChatId,
            'forwardMsgId' => $forwardMsgId,
            'inlineKeyboardMarkup' => $this->toJson($inlineKeyboardMarkup),
            'parseMode' => $this->parseMode($parseMode),
            'format' => $this->toJson($format),
        ], $file, $file === null ? 'GET' : 'POST');
    }

    /**
     * @param InlineKeyboardMarkup|array<array-key, mixed>|string|null $inlineKeyboardMarkup
     * @throws \JsonException|\Random\RandomException
     */
    public function sendVoice(
        string $chatId,
        ?string $fileId = null,
        ?string $file = null,
        ?string $replyMsgId = null,
        ?string $forwardChatId = null,
        ?string $forwardMsgId = null,
        InlineKeyboardMarkup|array|string|null $inlineKeyboardMarkup = null,
    ): ResponseInterface {
        return $this->request('messages/sendVoice', [
            'chatId' => $chatId,
            'fileId' => $fileId,
            'replyMsgId' => $replyMsgId,
            'forwardChatId' => $forwardChatId,
            'forwardMsgId' => $forwardMsgId,
            'inlineKeyboardMarkup' => $this->toJson($inlineKeyboardMarkup),
        ], $file, $file === null ? 'GET' : 'POST');
    }

    /**
     * @param InlineKeyboardMarkup|array<array-key, mixed>|string|null $inlineKeyboardMarkup
     * @param Format|array<array-key, mixed>|string|null $format
     * @throws \JsonException
     * @throws \Random\RandomException
     */
    public function editText(
        string $chatId,
        string $msgId,
        string $text,
        InlineKeyboardMarkup|array|string|null $inlineKeyboardMarkup = null,
        ParseMode|string|null $parseMode = null,
        Format|array|string|null $format = null,
    ): ResponseInterface {
        $this->assertTextFormat($parseMode, $format);

        return $this->request('messages/editText', [
            'chatId' => $chatId,
            'msgId' => $msgId,
            'text' => $text,
            'inlineKeyboardMarkup' => $this->toJson($inlineKeyboardMarkup),
            'parseMode' => $this->parseMode($parseMode),
            'format' => $this->toJson($format),
        ]);
    }

    public function deleteMessages(string $chatId, string $msgId): ResponseInterface
    {
        return $this->request('messages/deleteMessages', compact('chatId', 'msgId'));
    }

    public function answerCallbackQuery(
        string $queryId,
        string $text,
        bool $showAlert = false,
        ?string $url = null
    ): ResponseInterface {
        return $this->request(
            'messages/answerCallbackQuery',
            ['queryId' => $queryId, 'text' => $text, 'showAlert' => $this->bool($showAlert), 'url' => $url]
        );
    }

    /** @param list<string> $actions
     * @throws \Random\RandomException
     */
    public function sendActions(string $chatId, array $actions): ResponseInterface
    {
        return $this->request('chats/sendActions', ['chatId' => $chatId, 'actions' => $actions === [] ? '' : $actions]);
    }

    public function getChatInfo(string $chatId): ResponseInterface
    {
        return $this->request('chats/getInfo', compact('chatId'));
    }

    public function getChatAdmins(string $chatId): ResponseInterface
    {
        return $this->request('chats/getAdmins', compact('chatId'));
    }

    public function getChatMembers(string $chatId, ?string $cursor = null): ResponseInterface
    {
        return $this->request('chats/getMembers', compact('chatId', 'cursor'));
    }

    public function getChatBlockedUsers(string $chatId): ResponseInterface
    {
        return $this->request('chats/getBlockedUsers', compact('chatId'));
    }

    public function getChatPendingUsers(string $chatId): ResponseInterface
    {
        return $this->request('chats/getPendingUsers', compact('chatId'));
    }

    public function chatBlockUser(string $chatId, string $userId, bool $delLastMessages = false): ResponseInterface
    {
        return $this->request(
            'chats/blockUser',
            ['chatId' => $chatId, 'userId' => $userId, 'delLastMessages' => $this->bool($delLastMessages)]
        );
    }

    public function chatUnblockUser(string $chatId, string $userId): ResponseInterface
    {
        return $this->request('chats/unblockUser', compact('chatId', 'userId'));
    }

    public function chatResolvePending(
        string $chatId,
        bool $approve = true,
        string $userId = '',
        bool $everyone = false
    ): ResponseInterface {
        return $this->request(
            'chats/resolvePending',
            [
                'chatId' => $chatId,
                'approve' => $this->bool($approve),
                'userId' => $userId,
                'everyone' => $this->bool($everyone),
            ]
        );
    }

    public function setChatTitle(string $chatId, string $title): ResponseInterface
    {
        return $this->request('chats/setTitle', compact('chatId', 'title'));
    }

    public function setChatAbout(string $chatId, string $about): ResponseInterface
    {
        return $this->request('chats/setAbout', compact('chatId', 'about'));
    }

    public function setChatRules(string $chatId, string $rules): ResponseInterface
    {
        return $this->request('chats/setRules', compact('chatId', 'rules'));
    }

    public function getFileInfo(string $fileId): ResponseInterface
    {
        return $this->request('files/getInfo', compact('fileId'));
    }

    public function pinMessage(string $chatId, string $msgId): ResponseInterface
    {
        return $this->request('chats/pinMessage', compact('chatId', 'msgId'));
    }

    public function unpinMessage(string $chatId, string $msgId): ResponseInterface
    {
        return $this->request('chats/unpinMessage', compact('chatId', 'msgId'));
    }

    /**
     * @param list<string> $members
     * @throws \JsonException
     */
    public function addChatMembers(string $chatId, array $members): ResponseInterface
    {
        $this->assertMyTeam();

        return $this->request('chats/members/add', ['chatId' => $chatId, 'members' => $this->membersJson($members)]);
    }

    /**
     * @param list<string> $members
     * @throws \JsonException
     */
    public function deleteChatMembers(string $chatId, array $members): ResponseInterface
    {
        return $this->request('chats/members/delete', ['chatId' => $chatId, 'members' => $this->membersJson($members)]);
    }

    /**
     * @param list<string> $members
     * @throws \JsonException
     */
    public function createChat(
        string $name,
        string $about = '',
        string $rules = '',
        array $members = [],
        bool $public = false,
        bool $joinModeration = false,
        string $defaultRole = 'member'
    ): ResponseInterface {
        $this->assertMyTeam();

        return $this->request('chats/createChat', [
            'name' => $name,
            'about' => $about,
            'rules' => $rules,
            'members' => $this->membersJson($members),
            'public' => $this->bool($public),
            'defaultRole' => $defaultRole,
            'joinModeration' => $this->bool($joinModeration),
        ]);
    }

    public function threadsGetSubscribers(
        string $threadId,
        ?int $pageSize = null,
        ?string $cursor = null
    ): ResponseInterface {
        if ($pageSize === null && $cursor === null) {
            throw new InvalidArgumentException('pageSize or cursor must be provided.');
        }

        return $this->request('threads/subscribers/get', compact('threadId', 'pageSize', 'cursor'));
    }

    public function threadsAutosubscribe(
        string $chatId,
        bool|string $enable,
        bool|string $withExisting = false
    ): ResponseInterface {
        return $this->request('threads/autosubscribe', [
            'chatId' => $chatId,
            'enable' => is_bool($enable) ? $this->bool($enable) : $enable,
            'withExisting' => is_bool($withExisting) ? $this->bool($withExisting) : $withExisting,
        ]);
    }

    /**
     * @throws \Random\RandomException
     */
    public function threadsAdd(string $chatId, string $msgId): ResponseInterface
    {
        return $this->request('threads/add', compact('chatId', 'msgId'));
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @throws \Random\RandomException
     * @throws TransportException
     */
    private function request(
        string $path,
        array $query = [],
        ?string $file = null,
        string $method = 'GET'
    ): ResponseInterface {
        $uri = rtrim($this->apiUrlBase, '/') . '/' . ltrim($path, '/');
        $queryString = $this->buildQuery(['token' => $this->token] + $query);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        $request = $this->requestFactory
            ->createRequest($method, $uri)
            ->withHeader('User-Agent', $this->userAgent());

        if ($file !== null) {
            if (!is_file($file) || !is_readable($file)) {
                throw new RuntimeException("File is not readable: {$file}");
            }

            $boundary = bin2hex(random_bytes(16));
            $body = $this->streamFactory->createStream();
            $filename = addcslashes(basename($file), "\\\"");
            $body->write("--{$boundary}\r\n");
            $body->write("Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n");
            $body->write("Content-Type: application/octet-stream\r\n\r\n");

            $fileStream = $this->streamFactory->createStreamFromFile($file, 'r');
            while (!$fileStream->eof()) {
                $body->write($fileStream->read(8192));
            }
            $body->write("\r\n--{$boundary}--\r\n");
            $body->rewind();

            $request = $request
                ->withBody($body)
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $message = str_replace(
                [$this->token, rawurlencode($this->token)],
                '[REDACTED]',
                $exception->getMessage(),
            );

            // Do not chain the transport exception: its request URI may contain the bot token.
            throw new TransportException($message, $exception->getCode());
        }
    }

    /** @param array<string, scalar|list<scalar>|null> $query */
    private function buildQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                $parts[] = rawurlencode($name) . '=' . rawurlencode((string) $item);
            }
        }

        return implode('&', $parts);
    }

    /** @return array<string, mixed> */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $contentType = $response->getHeaderLine('Content-Type') ?: 'unknown';
            throw new ApiException(
                $response,
                sprintf(
                    'Bot API returned malformed JSON (HTTP %d, Content-Type %s, %d bytes): %s',
                    $response->getStatusCode(),
                    $contentType,
                    strlen($body),
                    $exception->getMessage(),
                ),
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new ApiException($response, 'Bot API response is not a JSON object.');
        }

        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                throw new ApiException($response, 'Bot API response is not a JSON object.');
            }
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function parseMode(ParseMode|string|null $mode): ?string
    {
        if ($mode === null) {
            return null;
        }

        return is_string($mode) ? ParseMode::from($mode)->value : $mode->value;
    }

    /** @param Format|array<array-key, mixed>|string|null $format */
    private function assertTextFormat(ParseMode|string|null $parseMode, Format|array|string|null $format): void
    {
        if ($parseMode !== null && $format !== null) {
            throw new InvalidArgumentException('Cannot use format and parseMode fields at the same time.');
        }
    }

    /**
     * @param InlineKeyboardMarkup|Format|array<array-key, mixed>|string|null $value
     * @throws \JsonException
     */
    private function toJson(InlineKeyboardMarkup|Format|array|string|null $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return is_array($value)
            ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $value->toJson();
    }

    /**
     * @param list<string> $members
     * @throws \JsonException
     */
    private function membersJson(array $members): string
    {
        return json_encode(
            array_map(static fn(string $member): array => ['sn' => $member], $members),
            JSON_THROW_ON_ERROR
        );
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function assertMyTeam(): void
    {
        if (!$this->myTeam) {
            throw new LogicException('This method is available only when myTeam is enabled.');
        }
    }
}
