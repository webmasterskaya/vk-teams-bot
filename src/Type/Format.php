<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Type;

use Webmasterskaya\VkTeamsBot\Enum\StyleType;

final class Format implements \JsonSerializable
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $styles = [];

    /** @param array<string, mixed> $arguments */
    public function add(StyleType|string $style, int $offset, int $length, array $arguments = []): self
    {
        $style = is_string($style) ? StyleType::from($style) : $style;
        $this->styles[$style->value][] = ['offset' => $offset, 'length' => $length] + $arguments;
        return $this;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function jsonSerialize(): array
    {
        return $this->styles;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
