<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing;

use Nemesis\Core\Database;

/**
 * DatabaseAssertions
 *
 * Mix into a TestCase to get database assertion helpers.
 * Requires a live DB connection (use with RefreshDatabase for rollback).
 *
 * Usage:
 *   class MyTest extends TestCase {
 *       use DatabaseAssertions;
 *   }
 */
trait DatabaseAssertions
{
    /**
     * Assert a row matching $data exists in $table.
     *
     * @param array<string, mixed> $data  Column → value pairs (all must match)
     */
    protected function assertDatabaseHas(string $table, array $data): void
    {
        $result = $this->queryForData($table, $data);
        if (empty($result)) {
            throw new \Exception(
                "Failed asserting that table [{$table}] contains row: " . json_encode($data)
            );
        }
    }

    /**
     * Assert NO row matching $data exists in $table.
     *
     * @param array<string, mixed> $data
     */
    protected function assertDatabaseMissing(string $table, array $data): void
    {
        $result = $this->queryForData($table, $data);
        if (!empty($result)) {
            throw new \Exception(
                "Failed asserting that table [{$table}] does NOT contain row: " . json_encode($data)
            );
        }
    }

    /**
     * Assert $table has exactly $count rows (optionally filtered by $data).
     *
     * @param array<string, mixed> $data  Optional WHERE conditions
     */
    protected function assertDatabaseCount(string $table, int $count, array $data = []): void
    {
        [$where, $bindings] = $this->buildWhere($data);

        $sql = "SELECT COUNT(*) as cnt FROM `{$table}`" . ($where ? " WHERE {$where}" : '');
        $pdo  = Database::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $actual = (int) $stmt->fetchColumn();

        if ($actual !== $count) {
            throw new \Exception(
                "Table [{$table}] expected {$count} row(s), found {$actual}."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** @return array<array<string, mixed>> */
    private function queryForData(string $table, array $data): array
    {
        [$where, $bindings] = $this->buildWhere($data);
        $sql  = "SELECT * FROM `{$table}` WHERE {$where} LIMIT 1";
        $pdo  = Database::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array{string, list<mixed>} */
    private function buildWhere(array $data): array
    {
        $clauses  = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            if ($value === null) {
                $clauses[] = "`{$column}` IS NULL";
            } else {
                $clauses[]  = "`{$column}` = ?";
                $bindings[] = $value;
            }
        }
        return [implode(' AND ', $clauses) ?: '1=1', $bindings];
    }
}
