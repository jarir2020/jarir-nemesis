<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications;

use Nemesis\Notifications\Channels\DatabaseChannel;

/**
 * Notifiable — add to any Model to make it a notification recipient.
 *
 * Usage:
 *   class User extends Model {
 *       use Notifiable;
 *   }
 *
 *   $user->notify(new OrderShipped($order));
 *   $user->notifications();           // all notifications
 *   $user->unreadNotifications();     // unread only
 *   $user->markNotificationsRead();   // mark all as read
 *   $user->unreadNotificationCount(); // integer
 */
trait Notifiable
{
    /**
     * Send a notification to this entity.
     */
    public function notify(Notification $notification): void
    {
        NotificationManager::getInstance()->send($this, $notification);
    }

    /**
     * Return all database notifications for this entity (newest first).
     * Delegates to DatabaseChannel; empty if database channel not used.
     *
     * @return list<array>
     */
    public function notifications(): array
    {
        return DatabaseChannel::forNotifiable($this);
    }

    /**
     * Return only unread notifications.
     *
     * @return list<array>
     */
    public function unreadNotifications(): array
    {
        return DatabaseChannel::unreadForNotifiable($this);
    }

    /**
     * Return the count of unread notifications.
     */
    public function unreadNotificationCount(): int
    {
        return count($this->unreadNotifications());
    }

    /**
     * Mark all notifications as read.
     */
    public function markNotificationsRead(): void
    {
        DatabaseChannel::markAllRead($this);
    }

    /**
     * Override to customise the email address used for mail notifications.
     * By default uses $this->email.
     */
    public function routeNotificationForMail(): string
    {
        return (string) ($this->email ?? '');
    }

    /**
     * Override to supply a Slack webhook URL for this notifiable.
     */
    public function routeNotificationForSlack(): string
    {
        return (string) ($this->slack_webhook_url ?? '');
    }

    /**
     * Override to supply a phone number for SMS notifications.
     */
    public function routeNotificationForSms(): string
    {
        return (string) ($this->phone ?? '');
    }
}
