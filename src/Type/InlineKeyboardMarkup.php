<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Type;

final class InlineKeyboardMarkup implements \JsonSerializable
{
    /** @var list<list<KeyboardButton>> */
    private array $keyboard = [];

    /** @var int<1, max> */
    private readonly int $buttonsInRow;

    public function __construct(int $buttonsInRow = 8)
    {
        if ($buttonsInRow < 1) {
            throw new \InvalidArgumentException('buttonsInRow must be greater than zero.');
        }
        $this->buttonsInRow = $buttonsInRow;
    }

    public function add(KeyboardButton ...$buttons): self
    {
        foreach (array_chunk($buttons, $this->buttonsInRow) as $row) {
            $this->keyboard[] = $row;
        }
        return $this;
    }

    public function row(KeyboardButton ...$buttons): self
    {
        if ($buttons !== []) {
            $this->keyboard[] = array_values($buttons);
        }
        return $this;
    }

    /** @return list<list<KeyboardButton>> */
    public function jsonSerialize(): array
    {
        return $this->keyboard;
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
