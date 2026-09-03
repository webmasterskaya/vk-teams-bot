<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

abstract class Event
{
    /** @param array<string, mixed> $payload */
    final public function __construct(
        private readonly int $eventId,
        private readonly array $payload,
        private readonly Bot $bot,
    ) {}

    abstract public function getType(): EventType;

    final public function getEventId(): int
    {
        return $this->eventId;
    }

    /** @return array<string, mixed> */
    final public function getPayload(): array
    {
        return $this->payload;
    }

    final public function getBot(): Bot
    {
        return $this->bot;
    }

    /** @param array<array-key, mixed>|null $source */
    final protected function stringValue(string $key, ?array $source = null): ?string
    {
        $values = $source ?? $this->payload;
        if (!isset($values[$key]) || !is_scalar($values[$key])) {
            return null;
        }

        return (string) $values[$key];
    }

    /**
     * @param array<array-key, mixed>|null $source
     * @return array<array-key, mixed>|null
     */
    final protected function arrayValue(string $key, ?array $source = null): ?array
    {
        $values = $source ?? $this->payload;
        if (!isset($values[$key]) || !is_array($values[$key])) {
            return null;
        }

        return $values[$key];
    }

    /** @param array<array-key, mixed>|null $source */
    final protected function intValue(string $key, ?array $source = null): ?int
    {
        $values = $source ?? $this->payload;
        if (!isset($values[$key]) || !is_scalar($values[$key])) {
            return null;
        }

        return (int) $values[$key];
    }
}
