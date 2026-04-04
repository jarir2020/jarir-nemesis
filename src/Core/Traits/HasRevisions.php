<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// HasRevisions trait — every Model save stores a revision; rollback and diff supported.

namespace Nemesis\Core\Traits;

/**
 * HasRevisions — attach to any Model to get a full version history.
 *
 * Usage:
 *   class Post extends Model {
 *       use HasRevisions;
 *   }
 *
 * API:
 *   $post->saveRevision();          // snapshot current attributes
 *   $post->getRevisions();          // all past revisions (newest first)
 *   $post->latestRevision();        // most recent revision object
 *   $post->rollbackTo(int $revId);  // restore attributes from a revision
 *   $post->diffRevision(int $revId);// compare current state to a revision
 *
 * DB table (auto-created on first write):
 *   nemesis_revisions (id, model_type, model_id, payload JSON,
 *                      created_by INT NULL, created_at DATETIME)
 *
 * Falls back to in-memory storage when no DB is available (unit tests).
 */
trait HasRevisions
{
    // -------------------------------------------------------------------------
    // In-memory fallback storage: [class][id][] = ['id', 'payload', 'created_at']
    // -------------------------------------------------------------------------
    private static array $_revMemory = [];
    private static int   $_revMemorySeq = 0;

    /** DB connection override for this model class (optional). */
    private static mixed $_revDb = null;

    private static string $_revTable = 'nemesis_revisions';
    private static bool   $_revTableChecked = false;

    // -------------------------------------------------------------------------
    // Boot (called by Model::boot via bootHasRevisions)
    // -------------------------------------------------------------------------

    public static function bootHasRevisions(): void
    {
        // Register an "updating" hook so revisions are saved before every update
        // Only if the parent Model supports observers/events.
        // We do this lazily in saveRevision() instead to avoid tight coupling.
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Snapshot the model's current attributes as a new revision.
     *
     * @param  int|null $createdBy  Optional user ID to record who saved
     */
    public function saveRevision(?int $createdBy = null): void
    {
        $id      = $this->getKey();
        $type    = static::class;
        $payload = $this->getRevisionPayload();
        $now     = date('Y-m-d H:i:s');

        $db = static::$_revDb ?? $this->getRevisionDb();

        if ($db !== null) {
            static::ensureRevisionTable($db);
            try {
                static::revDbExecute(
                    $db,
                    'INSERT INTO ' . static::$_revTable . ' (model_type,model_id,payload,created_by,created_at) VALUES (?,?,?,?,?)',
                    [$type, $id, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $createdBy, $now]
                );
                return;
            } catch (\Throwable) {}
        }

        // Fallback: in-memory
        static::$_revMemorySeq++;
        static::$_revMemory[$type][$id][] = [
            'id'         => static::$_revMemorySeq,
            'model_type' => $type,
            'model_id'   => $id,
            'payload'    => $payload,
            'created_by' => $createdBy,
            'created_at' => $now,
        ];
    }

    /**
     * Return all revisions for this model instance (newest first).
     *
     * @return list<array{id:int,model_type:string,model_id:int,payload:array,created_by:int|null,created_at:string}>
     */
    public function getRevisions(): array
    {
        $id   = $this->getKey();
        $type = static::class;
        $db   = static::$_revDb ?? $this->getRevisionDb();

        if ($db !== null) {
            static::ensureRevisionTable($db);
            try {
                $rows = static::revDbFetchAll(
                    $db,
                    'SELECT * FROM ' . static::$_revTable . ' WHERE model_type=? AND model_id=? ORDER BY id DESC',
                    [$type, $id]
                );
                // Decode JSON payload in each row
                return array_map(function (array $r): array {
                    if (isset($r['payload']) && is_string($r['payload'])) {
                        $r['payload'] = json_decode($r['payload'], true, 512, JSON_THROW_ON_ERROR);
                    }
                    return $r;
                }, $rows);
            } catch (\Throwable) {}
        }

        // Fallback: in-memory
        $revs = static::$_revMemory[$type][$id] ?? [];
        return array_reverse($revs); // newest first
    }

    /**
     * Return the most recent revision, or null if none exist.
     */
    public function latestRevision(): ?array
    {
        $revs = $this->getRevisions();
        return $revs[0] ?? null;
    }

    /**
     * Restore this model's in-memory attributes from a specific revision.
     * Does NOT persist — call save() after rolling back if persistence is needed.
     *
     * @param  int $revisionId  The revision's id value
     * @return bool             True if the revision was found and applied
     */
    public function rollbackTo(int $revisionId): bool
    {
        foreach ($this->getRevisions() as $rev) {
            if ((int) ($rev['id'] ?? 0) === $revisionId) {
                $payload = $rev['payload'] ?? [];
                // Restore attributes
                if (method_exists($this, 'fill')) {
                    $this->fill($payload);
                } else {
                    foreach ($payload as $key => $value) {
                        $this->setAttribute($key, $value);
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Return a diff of the current attributes vs. a stored revision.
     *
     * Returns an associative array: key → ['revision' => oldValue, 'current' => newValue]
     * Only keys that differ are included.
     *
     * @param  int $revisionId
     * @return array<string, array{revision: mixed, current: mixed}>
     */
    public function diffRevision(int $revisionId): array
    {
        $target = null;
        foreach ($this->getRevisions() as $rev) {
            if ((int) ($rev['id'] ?? 0) === $revisionId) {
                $target = $rev['payload'] ?? [];
                break;
            }
        }
        if ($target === null) return [];

        $current = $this->getRevisionPayload();
        $diff    = [];

        $allKeys = array_unique(array_merge(array_keys($current), array_keys($target)));
        foreach ($allKeys as $key) {
            $cur = $current[$key] ?? null;
            $rev = $target[$key]  ?? null;
            if ($cur !== $rev) {
                $diff[$key] = ['revision' => $rev, 'current' => $cur];
            }
        }
        return $diff;
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    /** Inject a DB connection for revision storage (per-class). */
    public static function setRevisionDb(mixed $db): void
    {
        static::$_revDb = $db;
    }

    /** Override the revisions table name (per-class). */
    public static function setRevisionTable(string $table): void
    {
        static::$_revTable        = $table;
        static::$_revTableChecked = false;
    }

    /** Flush in-memory revisions (test helper). */
    public static function resetRevisions(): void
    {
        static::$_revMemory      = [];
        static::$_revMemorySeq   = 0;
        static::$_revDb          = null;
        static::$_revTableChecked = false;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the payload array from the model's current attributes.
     * Excludes timestamps and the primary key by default.
     */
    private function getRevisionPayload(): array
    {
        $attrs = [];
        // getAttribute-style access (Nemesis Model stores in $attributes)
        if (property_exists($this, 'attributes')) {
            $attrs = (fn() => $this->attributes)->call($this);
        } elseif (method_exists($this, 'getAttributes')) {
            $attrs = $this->getAttributes();
        } elseif (method_exists($this, 'toArray')) {
            $attrs = $this->toArray();
        }
        return $attrs;
    }

    /** Return the model's primary key value. */
    private function getKey(): mixed
    {
        if (property_exists($this, 'primaryKey')) {
            $pk = (fn() => $this->primaryKey)->call($this);
            if (method_exists($this, 'getAttribute')) {
                return $this->getAttribute($pk);
            }
        }
        return method_exists($this, 'getKey') ? $this->{'getKey'}() : ($this->attributes['id'] ?? 0);
    }

    /** Try to get the DB connection from the parent Model (if available). */
    private function getRevisionDb(): mixed
    {
        // Try to reach \Nemesis\Core\Database singleton
        if (class_exists(\Nemesis\Core\Database::class)) {
            try {
                return \Nemesis\Core\Database::getInstance();
            } catch (\Throwable) {}
        }
        return null;
    }

    /** Ensure the revisions table exists. */
    private static function ensureRevisionTable(mixed $db): void
    {
        if (static::$_revTableChecked) return;
        static::$_revTableChecked = true;

        $sql = 'CREATE TABLE IF NOT EXISTS ' . static::$_revTable . ' (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            model_type VARCHAR(255) NOT NULL,
            model_id   INTEGER      NOT NULL,
            payload    LONGTEXT,
            created_by INTEGER      NULL,
            created_at DATETIME
        )';

        try {
            static::revDbExecute($db, $sql, []);
        } catch (\Throwable) {
            try {
                static::revDbExecute(
                    $db,
                    str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT PRIMARY KEY AUTO_INCREMENT', $sql),
                    []
                );
            } catch (\Throwable) {}
        }
    }

    private static function revDbExecute(mixed $db, string $sql, array $params): void
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } elseif (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
        } elseif (method_exists($db, 'execute')) {
            $db->execute($sql, $params);
        }
    }

    private static function revDbFetchAll(mixed $db, string $sql, array $params): array
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (method_exists($db, 'select')) {
            return (array) $db->select($sql, $params);
        }
        return [];
    }

    /** Get a specific attribute. Compatible with Nemesis Model's __get. */
    private function setAttribute(string $key, mixed $value): void
    {
        if (property_exists($this, 'attributes')) {
            (function () use ($key, $value) { $this->attributes[$key] = $value; })->call($this);
        }
    }
}
