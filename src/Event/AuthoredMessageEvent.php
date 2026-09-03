<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

abstract class AuthoredMessageEvent extends MessageEvent
{
    public function getText(): ?string
    {
        return $this->stringValue('text');
    }

    /** @return array<array-key, mixed>|null */
    public function getFormat(): ?array
    {
        return $this->arrayValue('format');
    }

    /** @return array<array-key, mixed>|null */
    public function getAuthor(): ?array
    {
        return $this->arrayValue('from');
    }
}
