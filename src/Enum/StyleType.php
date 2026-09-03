<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum StyleType: string
{
    case Bold = 'bold';
    case Italic = 'italic';
    case Underline = 'underline';
    case Strikethrough = 'strikethrough';
    case Link = 'link';
    case Mention = 'mention';
    case InlineCode = 'inline_code';
    case Pre = 'pre';
    case OrderedList = 'ordered_list';
    case UnorderedList = 'unordered_list';
    case Quote = 'quote';
}
