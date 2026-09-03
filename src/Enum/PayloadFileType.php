<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum PayloadFileType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
