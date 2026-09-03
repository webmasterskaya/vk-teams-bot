<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

abstract class MessageEvent extends ChatEvent
{
    public function getMessageId(): ?string
    {
        return $this->stringValue('msgId');
    }

    public function getTimestamp(): ?int
    {
        return $this->intValue('timestamp');
    }
}
