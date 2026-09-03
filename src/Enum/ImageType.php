<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum ImageType: string
{
    case Regular = '0';
    case Snap = '1';
    case Sticker = '2';
    case Reserved3 = '3';
    case AnimatedImage = '4';
    case AnimatedSticker = '5';
    case Reserved6 = '6';
    case Reserved7 = '7';
}
