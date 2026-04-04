<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * MailChannel — delivers notifications via email.
 *
 * The notification must implement toMail() returning:
 *   ['subject' => '...', 'body' => '...', 'from' => 'optional@email.com']
 *
 * The notifiable must expose an email address via:
 *   - $notifiable->email property
 *   - $notifiable->routeNotificationForMail() method
 */
class MailChannel implements NotificationChannelInterface
{
    /** Sent mail log (for testing — avoids real SMTP in tests). */
    private static array $sent = [];

    /** When true, skip actual sending and just log to $sent. */
    private static bool $fake = false;

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toMail($notifiable);
        if (empty($payload)) return;

        $to      = $this->resolveEmail($notifiable);
        $subject = $payload['subject'] ?? '(no subject)';
        $body    = $payload['body']    ?? '';
        $from    = $payload['from']    ?? null;

        if ($to === '') return;

        // Record in sent log
        static::$sent[] = compact('to', 'subject', 'body', 'from');

        if (static::$fake) return;

        // Attempt real send via Nemesis Mailer, fall back to PHP mail()
        try {
            if (class_exists(\Nemesis\Services\Mailer::class)) {
                $mailer = new \Nemesis\Services\Mailer();
                $mailer->send($to, $subject, $body);
                return;
            }
        } catch (\Throwable) {}

        // Minimal PHP mail() fallback
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
        if ($from) $headers .= "From: {$from}\r\n";
        @mail($to, $subject, $body, $headers);
    }

    // -------------------------------------------------------------------------
    // Test / fake helpers
    // -------------------------------------------------------------------------

    public static function fake(): void  { static::$fake = true; }
    public static function real(): void  { static::$fake = false; }
    public static function sent(): array { return static::$sent; }
    public static function lastSent(): ?array { return empty(static::$sent) ? null : end(static::$sent); }
    public static function countSent(): int   { return count(static::$sent); }

    public static function assertSentTo(string $email): bool
    {
        foreach (static::$sent as $m) {
            if ($m['to'] === $email) return true;
        }
        return false;
    }

    public static function reset(): void
    {
        static::$sent = [];
        static::$fake = false;
    }

    // -------------------------------------------------------------------------

    private function resolveEmail(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            return (string) $notifiable->routeNotificationForMail();
        }
        return (string) ($notifiable->email ?? '');
    }
}
