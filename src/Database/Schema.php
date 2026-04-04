<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Schema Builder | Updated: 2026-04-03

namespace Nemesis\Database;

use Nemesis\Core\Database;
use Nemesis\Database\Grammars\GrammarInterface;

/**
 * Schema — grammar-aware DDL builder.
 *
 * Usage:
 *   Schema::create('users', function(Blueprint $table) {
 *       $table->id();
 *       $table->string('email')->unique();
 *       $table->timestamps();
 *   });
 *
 *   Schema::table('users', function(Blueprint $table) {
 *       $table->string('phone', 20)->nullable()->after('email');
 *   });
 *
 *   Schema::drop('users');
 *   Schema::dropIfExists('users');
 *   Schema::rename('old_name', 'new_name');
 *   Schema::hasTable('users');
 */
class Schema
{
    // -------------------------------------------------------------------------
    // Create / Drop / Rename
    // -------------------------------------------------------------------------

    /**
     * Create a new table.
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $grammar = self::grammar();
        $sql     = $grammar->compileCreate(
            $table,
            $blueprint->getColumns(),
            self::tableCommands($blueprint->getCommands())
        );
        Database::unprepared($sql);

        // Register any extra indexes as standalone statements
        self::runIndexCommands($table, $blueprint->getCommands(), $grammar);
    }

    /**
     * Modify an existing table (ALTER TABLE).
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $grammar = self::grammar();

        // New columns → ADD COLUMN
        foreach ($blueprint->getColumns() as $col) {
            Database::unprepared($grammar->compileAddColumn($table, $col));
        }

        // Commands → DROP COLUMN, RENAME COLUMN, foreign keys, indexes
        foreach ($blueprint->getCommands() as $cmd) {
            $type = $cmd['type'] ?? '';
            $stmt = match ($type) {
                'dropColumn'   => $grammar->compileDropColumn($table, $cmd['name']),
                'renameColumn' => $grammar->compileRenameColumn($table, $cmd['from'], $cmd['to']),
                'dropIndex'    => self::compileDropIndex($table, $cmd['name'], $grammar),
                'dropForeign'  => self::compileDropForeign($table, $cmd['name'], $grammar),
                default        => '',
            };
            if ($stmt !== '') {
                Database::unprepared($stmt);
            }
        }
    }

    /**
     * Drop a table.
     */
    public static function drop(string $table): void
    {
        Database::unprepared(self::grammar()->compileDrop($table));
    }

    /**
     * Drop a table if it exists.
     */
    public static function dropIfExists(string $table): void
    {
        Database::unprepared(self::grammar()->compileDropIfExists($table));
    }

    /**
     * Rename a table.
     */
    public static function rename(string $from, string $to): void
    {
        Database::unprepared(self::grammar()->compileRename($from, $to));
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Check whether a table exists in the database.
     */
    public static function hasTable(string $table): bool
    {
        $grammar = self::grammar();
        $driver  = Database::getDriverName();
        $db      = '';

        // For MySQL we need the current database name
        if ($driver === 'mysql') {
            $row = Database::view("SELECT DATABASE() as db");
            $db  = $row[0]['db'] ?? '';
            $sql = $grammar->compileTableExists($table, "'{$db}'");
        } else {
            $sql = $grammar->compileTableExists($table);
        }

        $result = Database::view($sql);
        return (int) ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Check whether a column exists on a table.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $driver = Database::getDriverName();
        $sql = match ($driver) {
            'mysql'  => "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'",
            'pgsql'  => "SELECT column_name FROM information_schema.columns WHERE table_name='{$table}' AND column_name='{$column}'",
            'sqlite' => "PRAGMA table_info(\"{$table}\")",
            default  => "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'",
        };

        if ($driver === 'sqlite') {
            $rows = Database::view($sql);
            foreach ($rows as $row) {
                if (($row['name'] ?? '') === $column) return true;
            }
            return false;
        }

        return count(Database::view($sql)) > 0;
    }

    // -------------------------------------------------------------------------
    // Raw
    // -------------------------------------------------------------------------

    /**
     * Execute raw DDL (no binding — DDL cannot use parameters).
     */
    public static function raw(string $sql): void
    {
        Database::unprepared($sql);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected static function grammar(): GrammarInterface
    {
        return Database::getGrammar();
    }

    /** Filter commands that belong in the CREATE TABLE body (primary, unique, foreign). */
    protected static function tableCommands(array $commands): array
    {
        $inTable = ['primary', 'unique', 'foreign'];
        return array_values(array_filter($commands, fn($c) => in_array($c['type'] ?? '', $inTable)));
    }

    /** Run CREATE INDEX for standalone index commands after table creation. */
    protected static function runIndexCommands(string $table, array $commands, GrammarInterface $grammar): void
    {
        foreach ($commands as $cmd) {
            if (($cmd['type'] ?? '') === 'index') {
                $cols  = implode(', ', array_map(fn($c) => $grammar->quoteIdentifier($c), $cmd['columns']));
                $name  = $grammar->quoteIdentifier($cmd['name'] ?? ('idx_' . $table));
                $tbl   = $grammar->quoteIdentifier($table);
                Database::unprepared("CREATE INDEX {$name} ON {$tbl} ({$cols})");
            }
        }
    }

    protected static function compileDropIndex(string $table, string $name, GrammarInterface $grammar): string
    {
        $driver = Database::getDriverName();
        return match ($driver) {
            'mysql'  => "ALTER TABLE {$grammar->quoteIdentifier($table)} DROP INDEX {$grammar->quoteIdentifier($name)}",
            default  => "DROP INDEX {$grammar->quoteIdentifier($name)}",
        };
    }

    protected static function compileDropForeign(string $table, string $name, GrammarInterface $grammar): string
    {
        $driver = Database::getDriverName();
        return match ($driver) {
            'mysql'  => "ALTER TABLE {$grammar->quoteIdentifier($table)} DROP FOREIGN KEY {$grammar->quoteIdentifier($name)}",
            'pgsql'  => "ALTER TABLE {$grammar->quoteIdentifier($table)} DROP CONSTRAINT {$grammar->quoteIdentifier($name)}",
            'sqlite' => '', // SQLite doesn't support dropping FK constraints
            default  => '',
        };
    }
}
