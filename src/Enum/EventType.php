<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Enum;

enum EventType: string
{
    case NewMessage = 'newMessage';
    case EditedMessage = 'editedMessage';
    case DeletedMessage = 'deletedMessage';
    case PinnedMessage = 'pinnedMessage';
    case UnpinnedMessage = 'unpinnedMessage';
    case NewChatMembers = 'newChatMembers';
    case LeftChatMembers = 'leftChatMembers';
    case ChangedChatInfo = 'changedChatInfo';
    case CallbackQuery = 'callbackQuery';
}
