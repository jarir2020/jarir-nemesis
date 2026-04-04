<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Failed Job Manager | Updated: 2026-04-03

namespace Nemesis\Queue;

use Nemesis\Core\Database;

/**
 * Manages the `failed_jobs` table.
 *
 * Schema expected:
 *   id            BIGINT AUTO_INCREMENT PRIMARY KEY
 *   uuid          CHAR(36) NOT NULL
 *   queue         VARCHAR(191) NOT NULL DEFAULT 'default'
 *   payload       LONGTEXT NOT NULL
 *   exception     LONGTEXT NOT NULL
 *   failed_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
 */
class FailedJobManager
{
    protected string $table;

    public function __construct(string $table = 'failed_jobs')
    {
        $this->table = $table;
    }

    // -------------------------------------------------------------------------
    // Record
    // -------------------------------------------------------------------------

    public function store(Job $job, \Throwable $e, string $queue = 'default'): void
    {
        $pdo = Database::connect();
        $sql = "INSERT INTO {$this->table} (uuid, queue, payload, exception, failed_at)
                VALUES (:uuid, :queue, :payload, :exception, :failed_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uuid'      => $this->uuid(),
            'queue'     => $queue,
            'payload'   => serialize($job),
            'exception' => $this->formatException($e),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    public function all(): array
    {
        return Database::view("SELECT * FROM {$this->table} ORDER BY failed_at DESC");
    }

    public function find(int|string $id): ?array
    {
        $rows = Database::view("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1", ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $rows = Database::view("SELECT COUNT(*) as c FROM {$this->table}");
        return (int) ($rows[0]['c'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Retry
    // -------------------------------------------------------------------------

    /** Re-queue a failed job by id. Returns the Job instance on success. */
    public function retry(int|string $id, Queue $queue = null): Job
    {
        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException("Failed job [{$id}] not found.");
        }

        $job = unserialize($row['payload']);
        if (!$job instanceof Job) {
            throw new \RuntimeException("Failed job [{$id}] payload is corrupt.");
        }

        ($queue ?? new Queue())->push($job);
        $this->delete($id);

        return $job;
    }

    /** Re-queue ALL failed jobs. */
    public function retryAll(Queue $queue = null): int
    {
        $rows  = $this->all();
        $count = 0;
        foreach ($rows as $row) {
            try {
                $this->retry($row['id'], $queue);
                $count++;
            } catch (\Throwable) {
                // Skip corrupt entries silently
            }
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function delete(int|string $id): void
    {
        Database::delete("DELETE FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }

    public function flush(): void
    {
        Database::connect()->exec("DELETE FROM {$this->table}");
    }

    // -------------------------------------------------------------------------
    // Schema auto-creation (self-healing per CLAUDE.md rule 10)
    // -------------------------------------------------------------------------

    public function ensureTable(): void
    {
        $driver = Database::getDriverName();
        $sql    = match ($driver) {
            'pgsql'  => "CREATE TABLE IF NOT EXISTS {$this->table} (
                            id          BIGSERIAL PRIMARY KEY,
                            uuid        CHAR(36) NOT NULL,
                            queue       VARCHAR(191) NOT NULL DEFAULT 'default',
                            payload     TEXT NOT NULL,
                            exception   TEXT NOT NULL,
                            failed_at   TIMESTAMP NOT NULL DEFAULT NOW()
                         )",
            'sqlite' => "CREATE TABLE IF NOT EXISTS {$this->table} (
                            id          INTEGER PRIMARY KEY AUTOINCREMENT,
                            uuid        TEXT NOT NULL,
                            queue       TEXT NOT NULL DEFAULT 'default',
                            payload     TEXT NOT NULL,
                            exception   TEXT NOT NULL,
                            failed_at   TEXT NOT NULL DEFAULT (datetime('now'))
                         )",
            default  => "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                            `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            `uuid`        CHAR(36) NOT NULL,
                            `queue`       VARCHAR(191) NOT NULL DEFAULT 'default',
                            `payload`     LONGTEXT NOT NULL,
                            `exception`   LONGTEXT NOT NULL,
                            `failed_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        };
        Database::connect()->exec($sql);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function formatException(\Throwable $e): string
    {
        return get_class($e) . ': ' . $e->getMessage() .
               "\n\nStack trace:\n" . $e->getTraceAsString();
    }
}
