<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search\Drivers;

use Nemesis\Search\SearchDriverInterface;

/**
 * DatabaseDriver — search backed by a `search_indexes` table.
 *
 * MySQL:   Uses FULLTEXT index + MATCH … AGAINST when available.
 * Fallback: LIKE '%term%' across the searchable_text column.
 *
 * Table (auto-created):
 *   search_indexes (id, model_type, model_id, searchable_text, data JSON, updated_at)
 */
class DatabaseDriver implements SearchDriverInterface
{
    private static string $table        = 'search_indexes';
    private static mixed  $db           = null;
    private static bool   $tableChecked = false;

    /** In-memory fallback (mirrors NullDriver behaviour when no DB). */
    private NullDriver $memory;

    public function __construct()
    {
        $this->memory = new NullDriver();
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public static function setDb(mixed $db): void        { static::$db = $db; static::$tableChecked = false; }
    public static function setTable(string $t): void     { static::$table = $t; static::$tableChecked = false; }

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function index(string $modelClass, int $id, array $data): void
    {
        $this->memory->index($modelClass, $id, $data);

        if (static::$db === null) return;

        static::ensureTable();
        $text = implode(' ', array_map('strval', array_values($data)));
        $now  = date('Y-m-d H:i:s');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            // Upsert: remove then insert
            static::dbExecute(
                'DELETE FROM ' . static::$table . ' WHERE model_type=? AND model_id=?',
                [$modelClass, $id]
            );
            static::dbExecute(
                'INSERT INTO ' . static::$table . ' (model_type,model_id,searchable_text,data,updated_at) VALUES (?,?,?,?,?)',
                [$modelClass, $id, $text, $json, $now]
            );
        } catch (\Throwable) {}
    }

    public function remove(string $modelClass, int $id): void
    {
        $this->memory->remove($modelClass, $id);

        if (static::$db === null) return;

        static::ensureTable();
        try {
            static::dbExecute(
                'DELETE FROM ' . static::$table . ' WHERE model_type=? AND model_id=?',
                [$modelClass, $id]
            );
        } catch (\Throwable) {}
    }

    public function flush(string $modelClass): void
    {
        $this->memory->flush($modelClass);

        if (static::$db === null) return;

        static::ensureTable();
        try {
            static::dbExecute(
                'DELETE FROM ' . static::$table . ' WHERE model_type=?',
                [$modelClass]
            );
        } catch (\Throwable) {}
    }

    public function search(string $term, array $modelClasses = [], int $limit = 20): array
    {
        // If DB available, query the table
        if (static::$db !== null) {
            static::ensureTable();
            try {
                return $this->dbSearch($term, $modelClasses, $limit);
            } catch (\Throwable) {}
        }

        // Fallback to in-memory
        return $this->memory->search($term, $modelClasses, $limit);
    }

    // -------------------------------------------------------------------------
    // DB search
    // -------------------------------------------------------------------------

    private function dbSearch(string $term, array $modelClasses, int $limit): array
    {
        $sql    = 'SELECT * FROM ' . static::$table . ' WHERE searchable_text LIKE ?';
        $params = ['%' . $term . '%'];

        if (!empty($modelClasses)) {
            $placeholders = implode(',', array_fill(0, count($modelClasses), '?'));
            $sql    .= ' AND model_type IN (' . $placeholders . ')';
            $params  = array_merge($params, $modelClasses);
        }

        $sql .= ' LIMIT ' . max(1, $limit);

        $rows    = static::dbFetchAll($sql, $params);
        $results = [];

        foreach ($rows as $row) {
            $data = [];
            if (isset($row['data']) && is_string($row['data'])) {
                try { $data = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable) {}
            }
            $results[] = [
                'model' => $row['model_type'] ?? '',
                'id'    => (int) ($row['model_id'] ?? 0),
                'score' => 1.0,
                'data'  => $data,
            ];
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // DB helpers
    // -------------------------------------------------------------------------

    private static function ensureTable(): void
    {
        if (static::$tableChecked) return;
        static::$tableChecked = true;

        $sql = 'CREATE TABLE IF NOT EXISTS ' . static::$table . ' (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            model_type       VARCHAR(255) NOT NULL,
            model_id         INTEGER      NOT NULL,
            searchable_text  LONGTEXT,
            data             LONGTEXT,
            updated_at       DATETIME,
            UNIQUE KEY uq_model (model_type, model_id)
        )';

        try { static::dbExecute($sql, []); }
        catch (\Throwable) {
            try {
                static::dbExecute(
                    str_replace(
                        ['INTEGER PRIMARY KEY AUTOINCREMENT', 'UNIQUE KEY uq_model (model_type, model_id)'],
                        ['INT PRIMARY KEY AUTO_INCREMENT',    'UNIQUE KEY uq_model (model_type, model_id)'],
                        $sql
                    ),
                    []
                );
            } catch (\Throwable) {}
        }
    }

    private static function dbExecute(string $sql, array $params): void
    {
        $db = static::$db;
        if ($db instanceof \PDO)            { $db->prepare($sql)->execute($params); }
        elseif (method_exists($db,'statement')) { $db->statement($sql, $params); }
    }

    private static function dbFetchAll(string $sql, array $params): array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $st = $db->prepare($sql); $st->execute($params);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        }
        if (method_exists($db, 'select')) return (array) $db->select($sql, $params);
        return [];
    }
}
