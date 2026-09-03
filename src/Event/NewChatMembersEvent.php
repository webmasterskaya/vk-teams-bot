<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Enum\EventType;

final class NewChatMembersEvent extends ChatEvent
{
    public function getType(): EventType
    {
        return EventType::NewChatMembers;
    }

    /** @return array<array-key, mixed>|null */
    public function getNewMembers(): ?array
    {
        return $this->arrayValue('newMembers');
    }

    /** @return array<array-key, mixed>|null */
    public function getAddedBy(): ?array
    {
        return $this->arrayValue('addedBy');
    }

}
