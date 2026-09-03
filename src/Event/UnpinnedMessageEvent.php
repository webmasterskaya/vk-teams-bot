<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class UnpinnedMessageEvent extends MessageEvent
{
    public function getType(): EventType
    {
        return EventType::UnpinnedMessage;
    }
}
