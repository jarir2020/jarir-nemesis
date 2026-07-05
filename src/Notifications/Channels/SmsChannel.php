<?php
declare(strict_types=1);

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * SmsChannel — simple SMS delivery channel with fake/test support.
 */
class SmsChannel implements NotificationChannelInterface
{
    private static array $sent = [];
    private static bool $fake = false;

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toSms($notifiable);
        if (empty($payload) || empty($payload['message'])) {
            return;
        }

        $to = $this->resolvePhone($notifiable, $payload['to'] ?? null);
        if ($to === '') {
            return;
        }

        static::$sent[] = [
            'to' => $to,
            'message' => (string) $payload['message'],
        ];

        if (static::$fake) {
            return;
        }

        // Real SMS transport can be wired via a custom channel override.
    }

    public static function fake(): void
    {
        static::$fake = true;
    }

    public static function real(): void
    {
        static::$fake = false;
    }

    public static function sent(): array
    {
        return static::$sent;
    }

    public static function lastSent(): ?array
    {
        return empty(static::$sent) ? null : end(static::$sent);
    }

    public static function reset(): void
    {
        static::$sent = [];
        static::$fake = false;
    }

    public static function assertSentTo(string $phone): bool
    {
        foreach (static::$sent as $entry) {
            if (($entry['to'] ?? '') === $phone) {
                return true;
            }
        }
        return false;
    }

    private function resolvePhone(object $notifiable, mixed $override = null): string
    {
        if (is_string($override) && $override !== '') {
            return $override;
        }

        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return (string) $notifiable->routeNotificationForSms();
        }

        return (string) ($notifiable->phone ?? '');
    }
}
