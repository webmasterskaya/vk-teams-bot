<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Type;

final class Style implements \JsonSerializable
{
    /** @var list<array<string, mixed>> */
    private array $ranges = [];

    /** @param array<string, mixed> $arguments */
    public function add(int $offset, int $length, array $arguments = []): self
    {
        $this->ranges[] = ['offset' => $offset, 'length' => $length] + $arguments;
        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->ranges;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
