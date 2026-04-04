<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Concerns;

use Nemesis\Core\Database;

/**
 * RefreshDatabase
 *
 * Wraps each test in a DB transaction and rolls it back on tearDown.
 * This keeps the database clean between tests without truncating tables.
 *
 * Mix into any TestCase:
 *   class UserTest extends TestCase {
 *       use RefreshDatabase;
 *   }
 *
 * Note: Does not work with tests that span multiple connections or
 * that explicitly commit transactions.
 */
trait RefreshDatabase
{
    protected bool $inTransaction = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
    }

    public function tearDown(): void
    {
        $this->rollbackDatabaseTransaction();
        parent::tearDown();
    }

    protected function beginDatabaseTransaction(): void
    {
        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();
            $this->inTransaction = true;
        } catch (\Throwable) {
            // No DB connection in this test — skip silently
            $this->inTransaction = false;
        }
    }

    protected function rollbackDatabaseTransaction(): void
    {
        if ($this->inTransaction) {
            try {
                Database::connect()->rollBack();
            } catch (\Throwable) {}
            $this->inTransaction = false;
        }
    }
}
