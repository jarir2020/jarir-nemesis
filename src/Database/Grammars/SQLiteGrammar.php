<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — SQLite Grammar | Updated: 2026-04-03

namespace Nemesis\Database\Grammars;

class SQLiteGrammar extends MySqlGrammar
{
    // -------------------------------------------------------------------------
    // DDL Compilation (SQLite-specific)
    // -------------------------------------------------------------------------

    public function compileCreate(string $table, array $columns, array $tableCommands): string
    {
        $cols   = array_map([$this, 'compileColumn'], $columns);
        $extras = $this->compileTableCommandsSQLite($tableCommands);

        $parts = array_filter(array_merge($cols, $extras));
        $body  = implode(",\n    ", $parts);

        return "CREATE TABLE {$this->quoteIdentifier($table)} (\n    {$body}\n)";
    }

    protected function compileTableCommandsSQLite(array $commands): array
    {
        $sql = [];
        foreach ($commands as $cmd) {
            $type = $cmd['type'] ?? '';
            $cols = (array) ($cmd['columns'] ?? []);
            $quotedCols = implode(', ', array_map([$this, 'quoteIdentifier'], $cols));

            $sql[] = match ($type) {
                'primary' => "PRIMARY KEY ({$quotedCols})",
                'unique'  => "UNIQUE ({$quotedCols})",
                default   => '',
            };
        }
        return array_filter($sql);
    }

    public function compileColumn(array $col): string
    {
        $name = $this->quoteIdentifier($col['name']);
        $type = $this->columnType($col);
        $sql  = "{$name} {$type}";

        // SQLite: id column uses INTEGER PRIMARY KEY AUTOINCREMENT
        if ($col['type'] === 'id' || !empty($col['autoIncrement'])) {
            return "{$name} INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        if (!empty($col['nullable']))  $sql .= ' NULL';
        else                            $sql .= ' NOT NULL';
        if (array_key_exists('default', $col)) {
            $default = $col['default'];
            $sql .= ' DEFAULT ' . ($default === null ? 'NULL' : $this->quoteValue($default));
        }
        if (!empty($col['unique'])) $sql .= ' UNIQUE';

        return $sql;
    }

    protected function columnType(array $col): string
    {
        // SQLite type affinity: INTEGER, REAL, TEXT, BLOB, NUMERIC
        return match ($col['type']) {
            'id', 'tinyInteger', 'smallInteger', 'integer', 'bigInteger' => 'INTEGER',
            'float', 'double', 'decimal' => 'REAL',
            'boolean'                    => 'INTEGER',
            'date', 'time', 'datetime', 'timestamp' => 'TEXT',
            'json', 'text', 'mediumText', 'longText', 'string', 'char',
            'uuid', 'ipAddress', 'macAddress' => 'TEXT',
            'binary'                     => 'BLOB',
            default                      => 'TEXT',
        };
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE {$this->quoteIdentifier($table)}";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->quoteIdentifier($table)}";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    /** SQLite uses pragma or sqlite_master to check table existence. */
    public function compileTableExists(string $table, string $database = ''): string
    {
        return "SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name='{$table}'";
    }

    /** SQLite does not support DROP COLUMN in older versions — compile for >= 3.35.0. */
    public function compileDropColumn(string $table, string $column): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}";
    }

    public function compileRenameColumn(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    // -------------------------------------------------------------------------
    // Quoting (SQLite uses double-quotes like Postgres, backticks also work)
    // -------------------------------------------------------------------------

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
