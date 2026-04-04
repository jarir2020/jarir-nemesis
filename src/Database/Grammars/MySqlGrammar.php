<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — MySQL Grammar | Updated: 2026-04-03

namespace Nemesis\Database\Grammars;

class MySqlGrammar implements GrammarInterface
{
    // -------------------------------------------------------------------------
    // DDL Compilation
    // -------------------------------------------------------------------------

    public function compileCreate(string $table, array $columns, array $tableCommands): string
    {
        $cols   = array_map([$this, 'compileColumn'], $columns);
        $extras = $this->compileTableCommands($tableCommands);

        $parts = array_filter(array_merge($cols, $extras));
        $body  = implode(",\n    ", $parts);

        return "CREATE TABLE {$this->quoteIdentifier($table)} (\n    {$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    protected function compileTableCommands(array $commands): array
    {
        $sql = [];
        foreach ($commands as $cmd) {
            $type = $cmd['type'] ?? '';
            $cols = (array) ($cmd['columns'] ?? []);
            $name = $cmd['name'] ?? '';

            $quotedCols = implode(', ', array_map([$this, 'quoteIdentifier'], $cols));

            $sql[] = match ($type) {
                'primary' => "PRIMARY KEY ({$quotedCols})",
                'unique'  => "UNIQUE KEY {$this->quoteIdentifier($name)} ({$quotedCols})",
                'index'   => "KEY {$this->quoteIdentifier($name)} ({$quotedCols})",
                'foreign' => $this->compileForeignKey($cmd),
                default   => '',
            };
        }
        return array_filter($sql);
    }

    protected function compileForeignKey(array $cmd): string
    {
        $col = $this->quoteIdentifier($cmd['column'] ?? '');
        $ref = $this->quoteIdentifier($cmd['references'] ?? 'id');
        $on  = $this->quoteIdentifier($cmd['on'] ?? '');
        $del = strtoupper($cmd['onDelete'] ?? 'RESTRICT');
        $upd = strtoupper($cmd['onUpdate'] ?? 'RESTRICT');
        $idx = $this->quoteIdentifier($cmd['name'] ?? ('fk_' . ($cmd['column'] ?? 'col')));
        return "CONSTRAINT {$idx} FOREIGN KEY ({$col}) REFERENCES {$on} ({$ref}) ON DELETE {$del} ON UPDATE {$upd}";
    }

    public function compileColumn(array $col): string
    {
        $name = $this->quoteIdentifier($col['name']);
        $type = $this->columnType($col);
        $sql  = "{$name} {$type}";

        if (!empty($col['unsigned']))   $sql .= ' UNSIGNED';
        if (!empty($col['nullable']))   $sql .= ' NULL';
        else                             $sql .= ' NOT NULL';
        if (array_key_exists('default', $col)) {
            $default = $col['default'];
            $sql .= ' DEFAULT ' . ($default === null ? 'NULL' : $this->quoteValue($default));
        }
        if (!empty($col['autoIncrement'])) $sql .= ' AUTO_INCREMENT';
        if (!empty($col['primary']))       $sql .= ' PRIMARY KEY';
        if (!empty($col['unique']))        $sql .= ' UNIQUE';
        if (!empty($col['comment']))       $sql .= " COMMENT " . $this->quoteValue($col['comment']);
        if (!empty($col['after']))         $sql .= " AFTER {$this->quoteIdentifier($col['after'])}";

        return $sql;
    }

    protected function columnType(array $col): string
    {
        return match ($col['type']) {
            'id'          => 'BIGINT UNSIGNED',
            'tinyInteger' => 'TINYINT',
            'smallInteger'=> 'SMALLINT',
            'integer'     => 'INT',
            'bigInteger'  => 'BIGINT',
            'decimal'     => 'DECIMAL(' . ($col['total'] ?? 8) . ',' . ($col['places'] ?? 2) . ')',
            'float'       => 'FLOAT',
            'double'      => 'DOUBLE',
            'boolean'     => 'TINYINT(1)',
            'char'        => 'CHAR(' . ($col['length'] ?? 10) . ')',
            'string'      => 'VARCHAR(' . ($col['length'] ?? 255) . ')',
            'text'        => 'TEXT',
            'mediumText'  => 'MEDIUMTEXT',
            'longText'    => 'LONGTEXT',
            'binary'      => 'BLOB',
            'json'        => 'JSON',
            'date'        => 'DATE',
            'time'        => 'TIME',
            'datetime'    => 'DATETIME',
            'timestamp'   => 'TIMESTAMP',
            'year'        => 'YEAR',
            'uuid'        => 'CHAR(36)',
            'ipAddress'   => 'VARCHAR(45)',
            'macAddress'  => 'VARCHAR(17)',
            default       => strtoupper($col['type']),
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
        return "RENAME TABLE {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    public function compileAddColumn(string $table, array $column): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} ADD COLUMN {$this->compileColumn($column)}";
    }

    public function compileDropColumn(string $table, string $column): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}";
    }

    public function compileRenameColumn(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    public function compileTableExists(string $table, string $database = ''): string
    {
        $db = $database ?: 'DATABASE()';
        return "SELECT COUNT(*) as count FROM information_schema.tables "
             . "WHERE table_schema = {$db} AND table_name = '{$table}' LIMIT 1";
    }

    // -------------------------------------------------------------------------
    // Quoting helpers
    // -------------------------------------------------------------------------

    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    protected function quoteValue(mixed $value): string
    {
        if (is_bool($value))   return $value ? '1' : '0';
        if (is_null($value))   return 'NULL';
        if (is_numeric($value)) return (string) $value;
        return "'" . addslashes((string) $value) . "'";
    }
}
