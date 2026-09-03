<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class EditedMessageEvent extends AuthoredMessageEvent
{
    public function getType(): EventType
    {
        return EventType::EditedMessage;
    }

    public function getEditedTimestamp(): ?int
    {
        return $this->intValue('editedTimestamp');
    }
}
