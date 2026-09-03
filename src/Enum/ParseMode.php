<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum ParseMode: string
{
    case MarkdownV2 = 'MarkdownV2';
    case Html = 'HTML';
}
