<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications;

/**
 * NotificationChannelInterface — contract for all notification delivery channels.
 */
interface NotificationChannelInterface
{
    /**
     * Send the given notification to the notifiable entity.
     *
     * @param  object       $notifiable   The recipient
     * @param  Notification $notification The notification instance
     */
    public function send(object $notifiable, Notification $notification): void;
}
