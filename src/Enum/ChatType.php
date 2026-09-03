<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum ChatType: string
{
    case Private = 'private';
    case Group = 'group';
    case Channel = 'channel';
}
