<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Grammar Contract | Updated: 2026-04-03

namespace Nemesis\Database\Grammars;

interface GrammarInterface
{
    /** Compile a CREATE TABLE statement. */
    public function compileCreate(string $table, array $columns, array $tableCommands): string;

    /** Compile column definition fragments (used inside CREATE TABLE). */
    public function compileColumn(array $column): string;

    /** Compile DROP TABLE. */
    public function compileDrop(string $table): string;

    /** Compile DROP TABLE IF EXISTS. */
    public function compileDropIfExists(string $table): string;

    /** Compile RENAME TABLE. */
    public function compileRename(string $from, string $to): string;

    /** Compile ALTER TABLE … ADD COLUMN. */
    public function compileAddColumn(string $table, array $column): string;

    /** Compile ALTER TABLE … DROP COLUMN. */
    public function compileDropColumn(string $table, string $column): string;

    /** Compile ALTER TABLE … RENAME COLUMN. */
    public function compileRenameColumn(string $table, string $from, string $to): string;

    /** Compile a check for whether a table exists (returns SELECT statement). */
    public function compileTableExists(string $table, string $database = ''): string;

    /** Quote an identifier (table or column name). */
    public function quoteIdentifier(string $name): string;
}
