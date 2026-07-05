<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03
// NotificationManager — routes notifications to the appropriate channels.

namespace Nemesis\Notifications;

use Nemesis\Notifications\Channels\DatabaseChannel;
use Nemesis\Notifications\Channels\LogChannel;
use Nemesis\Notifications\Channels\MailChannel;
use Nemesis\Notifications\Channels\SmsChannel;
use Nemesis\Notifications\Channels\BroadcastChannel;
use Nemesis\Notifications\Channels\SlackChannel;
use Nemesis\Notifications\Channels\WebhookChannel;

/**
 * NotificationManager — singleton that dispatches notifications to channels.
 *
 * Usage:
 *   NotificationManager::getInstance()->send($user, new OrderShipped($order));
 *
 * Register a custom channel:
 *   NotificationManager::getInstance()->extend('sms', new SmsChannel());
 */
class NotificationManager
{
    /** @var array<string, NotificationChannelInterface> */
    private array $channels = [];

    private static ?self $instance = null;

    private function __construct()
    {
        // Register built-in channels
        $this->channels = [
            'mail'      => new MailChannel(),
            'sms'       => new SmsChannel(),
            'database'  => new DatabaseChannel(),
            'log'       => new LogChannel(),
            'broadcast' => new BroadcastChannel(),
            'slack'     => new SlackChannel(),
            'webhook'   => new WebhookChannel(),
        ];
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Replace singleton (test helper). */
    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    /** Reset singleton and channel state (test helper). */
    public static function reset(): void
    {
        static::$instance = null;
        DatabaseChannel::reset();
        LogChannel::reset();
        SmsChannel::reset();
    }

    // -------------------------------------------------------------------------
    // Channel registry
    // -------------------------------------------------------------------------

    /** Register a custom (or override built-in) channel driver. */
    public function extend(string $name, NotificationChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    /** Return a channel by name, or null if not registered. */
    public function channel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /** Return all registered channel names. */
    public function channelNames(): array
    {
        return array_keys($this->channels);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Send a notification to a notifiable through all channels returned by via().
     *
     * @param  object       $notifiable   The recipient (e.g. a User model)
     * @param  Notification $notification The notification to send
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($channels as $channelName) {
            $channel = $this->channels[$channelName] ?? null;
            if ($channel === null) continue;

            try {
                $channel->send($notifiable, $notification);
            } catch (\Throwable $e) {
                // Log silently — don't let one channel failure block others
                LogChannel::writeRaw(
                    'Notification channel [' . $channelName . '] failed: ' . $e->getMessage()
                );
            }
        }
    }
}
