<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum VideoType: string
{
    case Regular = '8';
    case Snap = '9';
    case Pts = 'A';
    case PtsB = 'B';
    case ReservedC = 'C';
    case Sticker = 'D';
    case ReservedE = 'E';
    case ReservedF = 'F';
}
