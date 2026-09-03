<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Type;

final class KeyboardButton implements \JsonSerializable
{
    public function __construct(
        public readonly string $text,
        public readonly string $style = 'primary',
        public readonly ?string $callbackData = null,
        public readonly ?string $url = null,
    ) {}

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return array_filter([
            'text' => $this->text,
            'callbackData' => $this->callbackData,
            'style' => $this->style,
            'url' => $this->url,
        ], static fn(?string $value): bool => $value !== null && $value !== '');
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
