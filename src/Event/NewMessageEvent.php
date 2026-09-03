<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class NewMessageEvent extends AuthoredMessageEvent
{
    public function getType(): EventType
    {
        return EventType::NewMessage;
    }

    /** @return array<array-key, mixed>|null */
    public function getParts(): ?array
    {
        return $this->arrayValue('parts');
    }
}
