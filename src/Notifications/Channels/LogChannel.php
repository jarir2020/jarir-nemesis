<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * LogChannel — writes notification details to an in-memory log and optionally a file.
 * Ideal for development and testing.
 */
class LogChannel implements NotificationChannelInterface
{
    /** In-memory log entries (for testing/assertion). */
    private static array $log = [];

    /** Optional file path to write log entries to. */
    private static ?string $logFile = null;

    public function send(object $notifiable, Notification $notification): void
    {
        $entry = [
            'notification' => get_class($notification),
            'notifiable'   => get_class($notifiable),
            'notifiable_id'=> $notifiable->id ?? null,
            'channels'     => $notification->via($notifiable),
            'data'         => $notification->toDatabase($notifiable),
            'at'           => date('Y-m-d H:i:s'),
        ];

        static::$log[] = $entry;

        if (static::$logFile !== null) {
            $line = '[' . $entry['at'] . '] NOTIFICATION ' . $entry['notification']
                  . ' → ' . $entry['notifiable'] . ' #' . ($entry['notifiable_id'] ?? '?')
                  . ' | ' . json_encode($entry['data']) . PHP_EOL;
            @file_put_contents(static::$logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }

    /** Write a raw string directly to the log (used by NotificationManager for errors). */
    public static function writeRaw(string $message): void
    {
        static::$log[] = ['raw' => $message, 'at' => date('Y-m-d H:i:s')];
    }

    // -------------------------------------------------------------------------
    // Introspection (test helpers)
    // -------------------------------------------------------------------------

    public static function all(): array
    {
        return static::$log;
    }

    public static function count(): int
    {
        return count(static::$log);
    }

    /**
     * Return entries for a specific notification class.
     * @param  string $class  e.g. OrderShipped::class
     */
    public static function for(string $class): array
    {
        return array_values(array_filter(
            static::$log,
            fn(array $e) => ($e['notification'] ?? '') === $class
        ));
    }

    public static function last(): ?array
    {
        return empty(static::$log) ? null : end(static::$log);
    }

    public static function setLogFile(?string $path): void
    {
        static::$logFile = $path;
    }

    public static function reset(): void
    {
        static::$log     = [];
        static::$logFile = null;
    }
}
