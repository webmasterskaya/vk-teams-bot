<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Event;

use Webmasterskaya\VkTeamsBot\Bot;
use Webmasterskaya\VkTeamsBot\Enum\EventType;
use Webmasterskaya\VkTeamsBot\Event;

final class EventFactory
{
    /** @param array<string, mixed> $payload */
    public static function create(EventType $type, int $eventId, array $payload, Bot $bot): Event
    {
        return match ($type) {
            EventType::NewMessage => new NewMessageEvent($eventId, $payload, $bot),
            EventType::EditedMessage => new EditedMessageEvent($eventId, $payload, $bot),
            EventType::DeletedMessage => new DeletedMessageEvent($eventId, $payload, $bot),
            EventType::PinnedMessage => new PinnedMessageEvent($eventId, $payload, $bot),
            EventType::UnpinnedMessage => new UnpinnedMessageEvent($eventId, $payload, $bot),
            EventType::NewChatMembers => new NewChatMembersEvent($eventId, $payload, $bot),
            EventType::LeftChatMembers => new LeftChatMembersEvent($eventId, $payload, $bot),
            EventType::ChangedChatInfo => new ChangedChatInfoEvent($eventId, $payload, $bot),
            EventType::CallbackQuery => new CallbackQueryEvent($eventId, $payload, $bot),
        };
    }
}
