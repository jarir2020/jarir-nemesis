<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * DatabaseChannel — persists notifications to the notifications table.
 *
 * Table (auto-created):
 *   notifications (id VARCHAR(36), type, notifiable_type, notifiable_id,
 *                  data JSON, read_at DATETIME NULL, created_at DATETIME)
 *
 * Falls back to in-memory storage when no DB is available.
 */
class DatabaseChannel implements NotificationChannelInterface
{
    private static string $table        = 'notifications';
    private static mixed  $db           = null;
    private static bool   $tableChecked = false;

    /** In-memory fallback: [id => row] */
    private static array $memory = [];

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toDatabase($notifiable);
        if (empty($payload)) return;

        $id   = static::uuid();
        $now  = date('Y-m-d H:i:s');
        $row  = [
            'id'               => $id,
            'type'             => get_class($notification),
            'notifiable_type'  => get_class($notifiable),
            'notifiable_id'    => $notifiable->id ?? 0,
            'data'             => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'read_at'          => null,
            'created_at'       => $now,
        ];

        static::$memory[$id] = $row;

        if (static::$db !== null) {
            static::ensureTable();
            try {
                static::dbExecute(
                    'INSERT INTO ' . static::$table . ' (id,type,notifiable_type,notifiable_id,data,read_at,created_at) VALUES (?,?,?,?,?,?,?)',
                    [$row['id'], $row['type'], $row['notifiable_type'], $row['notifiable_id'],
                     $row['data'], $row['read_at'], $row['created_at']]
                );
            } catch (\Throwable) {}
        }
    }

    // -------------------------------------------------------------------------
    // Query helpers (used by Notifiable trait)
    // -------------------------------------------------------------------------

    /**
     * Return all notifications for a notifiable entity.
     *
     * @return list<array>
     */
    public static function forNotifiable(object $notifiable): array
    {
        $type = get_class($notifiable);
        $id   = $notifiable->id ?? 0;

        if (static::$db !== null) {
            static::ensureTable();
            try {
                $rows = static::dbFetchAll(
                    'SELECT * FROM ' . static::$table . ' WHERE notifiable_type=? AND notifiable_id=? ORDER BY created_at DESC',
                    [$type, $id]
                );
                return array_map(fn(array $r) => static::decodeRow($r), $rows);
            } catch (\Throwable) {}
        }

        // Fallback: in-memory
        $results = array_values(array_filter(
            static::$memory,
            fn(array $r) => $r['notifiable_type'] === $type && (int) $r['notifiable_id'] === (int) $id
        ));
        return array_map(fn(array $r) => static::decodeRow($r), $results);
    }

    /** Return unread notifications for a notifiable. */
    public static function unreadForNotifiable(object $notifiable): array
    {
        return array_values(array_filter(
            static::forNotifiable($notifiable),
            fn(array $r) => $r['read_at'] === null
        ));
    }

    /** Mark all notifications for a notifiable as read. */
    public static function markAllRead(object $notifiable): void
    {
        $type = get_class($notifiable);
        $id   = $notifiable->id ?? 0;
        $now  = date('Y-m-d H:i:s');

        // Update memory
        foreach (static::$memory as $k => $row) {
            if ($row['notifiable_type'] === $type && (int) $row['notifiable_id'] === (int) $id) {
                static::$memory[$k]['read_at'] = $now;
            }
        }

        if (static::$db !== null) {
            static::ensureTable();
            try {
                static::dbExecute(
                    'UPDATE ' . static::$table . ' SET read_at=? WHERE notifiable_type=? AND notifiable_id=? AND read_at IS NULL',
                    [$now, $type, $id]
                );
            } catch (\Throwable) {}
        }
    }

    // -------------------------------------------------------------------------
    // Configuration + test helpers
    // -------------------------------------------------------------------------

    public static function setDb(mixed $db): void            { static::$db = $db; }
    public static function setTable(string $table): void     { static::$table = $table; static::$tableChecked = false; }

    public static function reset(): void
    {
        static::$memory       = [];
        static::$db           = null;
        static::$tableChecked = false;
    }

    public static function all(): array
    {
        return array_values(array_map([static::class, 'decodeRow'], static::$memory));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function decodeRow(array $row): array
    {
        if (isset($row['data']) && is_string($row['data'])) {
            try {
                $row['data'] = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {}
        }
        return $row;
    }

    private static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private static function ensureTable(): void
    {
        if (static::$tableChecked) return;
        static::$tableChecked = true;

        $sql = 'CREATE TABLE IF NOT EXISTS ' . static::$table . ' (
            id               VARCHAR(36)  PRIMARY KEY,
            type             VARCHAR(255) NOT NULL,
            notifiable_type  VARCHAR(255) NOT NULL,
            notifiable_id    INTEGER      NOT NULL,
            data             LONGTEXT,
            read_at          DATETIME     NULL,
            created_at       DATETIME
        )';

        try {
            static::dbExecute($sql, []);
        } catch (\Throwable) {}
    }

    private static function dbExecute(string $sql, array $params): void
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $db->prepare($sql)->execute($params);
        } elseif (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
        }
    }

    private static function dbFetchAll(string $sql, array $params): array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (method_exists($db, 'select')) {
            return (array) $db->select($sql, $params);
        }
        return [];
    }
}
