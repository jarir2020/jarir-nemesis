<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03
// Notification — abstract base class for all application notifications.

namespace Nemesis\Notifications;

/**
 * Notification — base class for every notification type.
 *
 * Extend this and override the channel methods you need:
 *
 *   class OrderShipped extends Notification
 *   {
 *       public function __construct(private Order $order) {}
 *
 *       public function via(object $notifiable): array
 *       {
 *           return ['mail', 'database'];
 *       }
 *
 *       public function toMail(object $notifiable): array
 *       {
 *           return [
 *               'subject' => 'Your order has shipped!',
 *               'body'    => 'Order #' . $this->order->id . ' is on its way.',
 *           ];
 *       }
 *
 *       public function toDatabase(object $notifiable): array
 *       {
 *           return ['order_id' => $this->order->id, 'status' => 'shipped'];
 *       }
 *   }
 *
 * Sending:
 *   Notification::send($user, new OrderShipped($order));
 *   // or via Notifiable trait:
 *   $user->notify(new OrderShipped($order));
 */
abstract class Notification
{
    /**
     * The channels this notification should be sent through.
     * Override in subclass to specify: ['mail', 'database', 'log', 'slack', 'webhook']
     *
     * @param  object $notifiable  The entity receiving the notification
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['log'];
    }

    /**
     * Build the mail channel payload.
     * Keys: subject, body, from (optional), html (optional bool)
     *
     * @return array{subject:string,body:string}
     */
    public function toMail(object $notifiable): array
    {
        return [];
    }

    /**
     * Build the database channel payload (stored as JSON in notifications table).
     *
     * @return array<string,mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [];
    }

    /**
     * Build the broadcast channel payload.
     *
     * @return array<string,mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Build the Slack channel payload.
     * Keys: text, channel (optional), username (optional), icon_emoji (optional)
     *
     * @return array{text:string}
     */
    public function toSlack(object $notifiable): array
    {
        return [];
    }

    /**
     * Build the SMS channel payload.
     *
     * @return array{to?:string,message?:string}
     */
    public function toSms(object $notifiable): array
    {
        return [];
    }

    /**
     * Build the webhook channel payload.
     * Keys: url (required), method (default POST), data (array), headers (optional)
     *
     * @return array{url:string,data:array}
     */
    public function toWebhook(object $notifiable): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Static send helpers
    // -------------------------------------------------------------------------

    /**
     * Send a notification to one or more notifiables via the NotificationManager.
     *
     * @param  object|list<object>  $notifiables  Single notifiable or array
     * @param  Notification         $notification  The notification to send
     */
    public static function send(object|array $notifiables, Notification $notification): void
    {
        $manager = NotificationManager::getInstance();

        $targets = is_array($notifiables) ? $notifiables : [$notifiables];
        foreach ($targets as $notifiable) {
            $manager->send($notifiable, $notification);
        }
    }

    /**
     * Send to many notifiables at once.
     *
     * @param  list<object> $notifiables
     */
    public static function sendToMany(array $notifiables, Notification $notification): void
    {
        static::send($notifiables, $notification);
    }
}
