<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class LeftChatMembersEvent extends ChatEvent
{
    public function getType(): EventType
    {
        return EventType::LeftChatMembers;
    }

    /** @return array<array-key, mixed>|null */
    public function getLeftMembers(): ?array
    {
        return $this->arrayValue('leftMembers');
    }

    /** @return array<array-key, mixed>|null */
    public function getRemovedBy(): ?array
    {
        return $this->arrayValue('removedBy');
    }

}
