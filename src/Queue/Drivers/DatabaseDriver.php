<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — DatabaseDriver updated | Updated: 2026-04-03

namespace Nemesis\Queue\Drivers;

use Nemesis\Queue\QueueDriver;
use Nemesis\Queue\Job;
use Nemesis\Core\Database;
use PDO;

class DatabaseDriver implements QueueDriver
{
    protected string $table;

    public function __construct(string $table = 'jobs')
    {
        $this->table = $table;
        $this->ensureTable();
    }

    // -------------------------------------------------------------------------
    // QueueDriver contract
    // -------------------------------------------------------------------------

    public function push(Job $job, string $queue = 'default'): mixed
    {
        $pdo  = Database::connect();
        $sql  = "INSERT INTO {$this->table} (queue, payload, available_at, created_at)
                 VALUES (:queue, :payload, :available_at, :created_at)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'queue'        => $queue,
            'payload'      => serialize($job),
            'available_at' => time() + $job->getDelay(),
            'created_at'   => time(),
        ]);
    }

    public function pop(string $queue = 'default'): ?array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $sql  = "SELECT * FROM {$this->table}
                     WHERE queue = :queue
                       AND reserved_at IS NULL
                       AND available_at <= :now
                     ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['queue' => $queue, 'now' => time()]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $pdo->prepare(
                    "UPDATE {$this->table}
                     SET reserved_at = :now, attempts = attempts + 1
                     WHERE id = :id"
                )->execute(['now' => time(), 'id' => $row['id']]);
                $pdo->commit();
                return $row;
            }

            $pdo->rollBack();
            return null;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return null;
        }
    }

    public function delete(int|string $id): bool
    {
        $stmt = Database::connect()->prepare(
            "DELETE FROM {$this->table} WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    public function release(int|string $id, int $delay = 0): bool
    {
        $stmt = Database::connect()->prepare(
            "UPDATE {$this->table}
             SET reserved_at = NULL, available_at = :available_at
             WHERE id = :id"
        );
        return $stmt->execute([
            'available_at' => time() + $delay,
            'id'           => $id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Self-healing table creation (CLAUDE.md rule 10)
    // -------------------------------------------------------------------------

    public function ensureTable(): void
    {
        $driver = Database::getDriverName();
        $sql    = match ($driver) {
            'pgsql'  => "CREATE TABLE IF NOT EXISTS {$this->table} (
                            id           BIGSERIAL PRIMARY KEY,
                            queue        VARCHAR(191) NOT NULL DEFAULT 'default',
                            payload      TEXT NOT NULL,
                            attempts     INTEGER NOT NULL DEFAULT 0,
                            reserved_at  INTEGER NULL,
                            available_at INTEGER NOT NULL,
                            created_at   INTEGER NOT NULL
                         )",
            'sqlite' => "CREATE TABLE IF NOT EXISTS \"{$this->table}\" (
                            id           INTEGER PRIMARY KEY AUTOINCREMENT,
                            queue        TEXT NOT NULL DEFAULT 'default',
                            payload      TEXT NOT NULL,
                            attempts     INTEGER NOT NULL DEFAULT 0,
                            reserved_at  INTEGER NULL,
                            available_at INTEGER NOT NULL,
                            created_at   INTEGER NOT NULL
                         )",
            default  => "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                            `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            `queue`        VARCHAR(191) NOT NULL DEFAULT 'default',
                            `payload`      LONGTEXT NOT NULL,
                            `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
                            `reserved_at`  INT UNSIGNED NULL,
                            `available_at` INT UNSIGNED NOT NULL,
                            `created_at`   INT UNSIGNED NOT NULL
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        };

        try {
            Database::connect()->exec($sql);
        } catch (\Throwable) {
            // Table may already exist with different schema — silently continue
        }
    }
}
