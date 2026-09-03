<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum PartType: string
{
    case File = 'file';
    case Sticker = 'sticker';
    case Mention = 'mention';
    case Voice = 'voice';
    case Forward = 'forward';
    case Reply = 'reply';
}
