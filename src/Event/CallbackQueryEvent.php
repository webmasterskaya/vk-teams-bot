<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;
use Webmasterskaya\VkTeamsBot\Event;

final class CallbackQueryEvent extends Event
{
    public function getType(): EventType
    {
        return EventType::CallbackQuery;
    }

    public function getQueryId(): ?string
    {
        return $this->stringValue('queryId');
    }

    public function getCallbackData(): ?string
    {
        return $this->stringValue('callbackData');
    }

    /** @return array<array-key, mixed>|null */
    public function getFrom(): ?array
    {
        return $this->arrayValue('from');
    }

    /** @return array<array-key, mixed> */
    public function getMessage(): array
    {
        return $this->arrayValue('message') ?? [];
    }

    public function getMessageId(): ?string
    {
        return $this->stringValue('msgId', $this->getMessage());
    }

    public function getText(): ?string
    {
        return $this->stringValue('text', $this->getMessage());
    }

    /** @return array<array-key, mixed>|null */
    public function getFormat(): ?array
    {
        return $this->arrayValue('format', $this->getMessage());
    }

    public function getChatId(): ?string
    {
        return $this->stringValue('chatId', $this->messageChat());
    }

    public function getChatType(): ?string
    {
        return $this->stringValue('type', $this->messageChat());
    }

    public function getChatTitle(): ?string
    {
        return $this->stringValue('title', $this->messageChat());
    }

    public function getTimestamp(): ?int
    {
        return $this->intValue('timestamp', $this->getMessage());
    }

    public function getMessageAuthor(): ?string
    {
        $parts = explode(':', $this->getQueryId() ?? '', 3);

        return $parts[1] ?? null;
    }

    /** @return array<array-key, mixed> */
    private function messageChat(): array
    {
        return $this->arrayValue('chat', $this->getMessage()) ?? [];
    }
}
