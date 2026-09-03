<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Event;

abstract class ChatEvent extends Event
{
    public function getChatId(): ?string
    {
        return $this->stringValue('chatId', $this->chat());
    }

    public function getChatType(): ?string
    {
        return $this->stringValue('type', $this->chat());
    }

    public function getChatTitle(): ?string
    {
        return $this->stringValue('title', $this->chat());
    }

    /** @return array<array-key, mixed> */
    final protected function chat(): array
    {
        return $this->arrayValue('chat') ?? [];
    }
}
