<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * BroadcastChannel — dispatches notification data via the Nemesis EventDispatcher.
 * Fires a 'notification.sent' action hook with the payload.
 */
class BroadcastChannel implements NotificationChannelInterface
{
    private static array $broadcast = [];

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toBroadcast($notifiable);

        $event = [
            'type'          => get_class($notification),
            'notifiable'    => get_class($notifiable),
            'notifiable_id' => $notifiable->id ?? null,
            'data'          => $payload,
            'at'            => date('Y-m-d H:i:s'),
        ];

        static::$broadcast[] = $event;

        // Fire via HookDispatcher if available
        if (class_exists(\Nemesis\Hooks\HookDispatcher::class)) {
            try {
                \Nemesis\Hooks\HookDispatcher::doAction('notification.sent', $event);
            } catch (\Throwable) {}
        }

        // Fire via EventDispatcher if available
        if (class_exists(\Nemesis\Events\EventDispatcher::class)) {
            try {
                \Nemesis\Events\EventDispatcher::dispatch((object) $event);
            } catch (\Throwable) {}
        }
    }

    public static function broadcasts(): array { return static::$broadcast; }
    public static function lastBroadcast(): ?array { return empty(static::$broadcast) ? null : end(static::$broadcast); }
    public static function reset(): void { static::$broadcast = []; }
}
