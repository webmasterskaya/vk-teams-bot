<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class PinnedMessageEvent extends MessageEvent
{
    public function getType(): EventType
    {
        return EventType::PinnedMessage;
    }

    public function getText(): ?string
    {
        return $this->stringValue('text');
    }

    /** @return array<array-key, mixed>|null */
    public function getAuthor(): ?array
    {
        return $this->arrayValue('from');
    }
}
