<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — PostgreSQL Grammar | Updated: 2026-04-03

namespace Nemesis\Database\Grammars;

class PostgresGrammar extends MySqlGrammar
{
    // -------------------------------------------------------------------------
    // DDL Compilation (Postgres-specific overrides)
    // -------------------------------------------------------------------------

    public function compileCreate(string $table, array $columns, array $tableCommands): string
    {
        $cols   = array_map([$this, 'compileColumn'], $columns);
        $extras = $this->compileTableCommands($tableCommands);

        $parts = array_filter(array_merge($cols, $extras));
        $body  = implode(",\n    ", $parts);

        return "CREATE TABLE {$this->quoteIdentifier($table)} (\n    {$body}\n)";
    }

    public function compileColumn(array $col): string
    {
        $name = $this->quoteIdentifier($col['name']);
        $type = $this->columnType($col);
        $sql  = "{$name} {$type}";

        if (!empty($col['nullable']))  $sql .= ' NULL';
        else                            $sql .= ' NOT NULL';
        if (array_key_exists('default', $col)) {
            $default = $col['default'];
            $sql .= ' DEFAULT ' . ($default === null ? 'NULL' : $this->quoteValue($default));
        }
        if (!empty($col['primary'])) $sql .= ' PRIMARY KEY';
        if (!empty($col['unique']))  $sql .= ' UNIQUE';

        return $sql;
    }

    protected function columnType(array $col): string
    {
        // id in Postgres uses BIGSERIAL for auto-increment
        if ($col['type'] === 'id' || (!empty($col['autoIncrement']) && $col['type'] === 'bigInteger')) {
            return 'BIGSERIAL';
        }
        if (!empty($col['autoIncrement']) && $col['type'] === 'integer') {
            return 'SERIAL';
        }
        return match ($col['type']) {
            'id'           => 'BIGSERIAL',
            'tinyInteger'  => 'SMALLINT',
            'smallInteger' => 'SMALLINT',
            'integer'      => 'INTEGER',
            'bigInteger'   => 'BIGINT',
            'decimal'      => 'NUMERIC(' . ($col['total'] ?? 8) . ',' . ($col['places'] ?? 2) . ')',
            'float'        => 'REAL',
            'double'       => 'DOUBLE PRECISION',
            'boolean'      => 'BOOLEAN',
            'char'         => 'CHAR(' . ($col['length'] ?? 10) . ')',
            'string'       => 'VARCHAR(' . ($col['length'] ?? 255) . ')',
            'text'         => 'TEXT',
            'mediumText'   => 'TEXT',
            'longText'     => 'TEXT',
            'binary'       => 'BYTEA',
            'json'         => 'JSONB',
            'date'         => 'DATE',
            'time'         => 'TIME',
            'datetime'     => 'TIMESTAMP',
            'timestamp'    => 'TIMESTAMP',
            'uuid'         => 'UUID',
            'ipAddress'    => 'INET',
            'macAddress'   => 'MACADDR',
            default        => strtoupper($col['type']),
        };
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE {$this->quoteIdentifier($table)}";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->quoteIdentifier($table)} CASCADE";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    public function compileTableExists(string $table, string $database = ''): string
    {
        return "SELECT COUNT(*) as count FROM information_schema.tables "
             . "WHERE table_schema = 'public' AND table_name = '{$table}'";
    }

    // -------------------------------------------------------------------------
    // Quoting (Postgres uses double-quotes)
    // -------------------------------------------------------------------------

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
