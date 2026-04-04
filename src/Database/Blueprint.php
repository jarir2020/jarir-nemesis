<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Schema Blueprint DSL | Updated: 2026-04-03

namespace Nemesis\Database;

/**
 * Fluent column-definition returned by Blueprint column methods.
 * Chainable: $table->string('name')->nullable()->default('')->unique()
 */
class ColumnDefinition
{
    public array $attrs;

    public function __construct(array $attrs)
    {
        $this->attrs = $attrs;
    }

    public function nullable(bool $value = true): static
    {
        $this->attrs['nullable'] = $value;
        return $this;
    }

    public function notNull(): static
    {
        $this->attrs['nullable'] = false;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->attrs['default'] = $value;
        return $this;
    }

    public function unsigned(): static
    {
        $this->attrs['unsigned'] = true;
        return $this;
    }

    public function unique(): static
    {
        $this->attrs['unique'] = true;
        return $this;
    }

    public function primary(): static
    {
        $this->attrs['primary'] = true;
        return $this;
    }

    public function index(): static
    {
        $this->attrs['index'] = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->attrs['autoIncrement'] = true;
        return $this;
    }

    public function comment(string $text): static
    {
        $this->attrs['comment'] = $text;
        return $this;
    }

    /** MySQL: place column after another column. */
    public function after(string $column): static
    {
        $this->attrs['after'] = $column;
        return $this;
    }

    public function useCurrent(): static
    {
        $this->attrs['default'] = 'CURRENT_TIMESTAMP';
        return $this;
    }

    public function useCurrentOnUpdate(): static
    {
        $this->attrs['onUpdate'] = 'CURRENT_TIMESTAMP';
        return $this;
    }
}

/**
 * Fluent foreign-key definition: $table->foreign('user_id')->references('id')->on('users')
 */
class ForeignKeyDefinition
{
    public array $attrs;

    public function __construct(array $attrs)
    {
        $this->attrs = $attrs;
    }

    public function references(string $column): static
    {
        $this->attrs['references'] = $column;
        return $this;
    }

    public function on(string $table): static
    {
        $this->attrs['on'] = $table;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->attrs['onDelete'] = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->attrs['onUpdate'] = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static { return $this->onDelete('CASCADE'); }
    public function nullOnDelete(): static    { return $this->onDelete('SET NULL'); }
    public function restrictOnDelete(): static{ return $this->onDelete('RESTRICT'); }
    public function cascadeOnUpdate(): static { return $this->onUpdate('CASCADE'); }
}

/**
 * Blueprint — describes a table's columns and constraints.
 * Used inside Schema::create() and Schema::table() callbacks.
 */
class Blueprint
{
    protected string $table;
    protected array  $columns  = [];
    protected array  $commands = [];   // table-level constraints / indexes

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function getTable(): string   { return $this->table; }
    public function getColumns(): array  { return $this->columns; }
    public function getCommands(): array { return $this->commands; }

    // -------------------------------------------------------------------------
    // Integer types
    // -------------------------------------------------------------------------

    /** BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY (surrogate PK). */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn([
            'name'          => $name,
            'type'          => 'id',
            'autoIncrement' => true,
            'unsigned'      => true,
            'nullable'      => false,
            'primary'       => true,
        ]);
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'tinyInteger']);
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'smallInteger']);
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'integer']);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'bigInteger']);
    }

    public function unsignedInteger(string $name): ColumnDefinition
    {
        return $this->integer($name)->unsigned();
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    // -------------------------------------------------------------------------
    // Decimal / float types
    // -------------------------------------------------------------------------

    public function decimal(string $name, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'decimal', 'total' => $total, 'places' => $places]);
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'float']);
    }

    public function double(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'double']);
    }

    // -------------------------------------------------------------------------
    // Boolean
    // -------------------------------------------------------------------------

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'boolean']);
    }

    // -------------------------------------------------------------------------
    // String / text types
    // -------------------------------------------------------------------------

    public function char(string $name, int $length = 10): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'char', 'length' => $length]);
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'string', 'length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'text']);
    }

    public function mediumText(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'mediumText']);
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'longText']);
    }

    // -------------------------------------------------------------------------
    // JSON / binary
    // -------------------------------------------------------------------------

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'json']);
    }

    public function jsonb(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'json']); // Postgres will map to JSONB
    }

    public function binary(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'binary']);
    }

    // -------------------------------------------------------------------------
    // Date / time types
    // -------------------------------------------------------------------------

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'date']);
    }

    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'time']);
    }

    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'datetime']);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'timestamp']);
    }

    public function year(string $name): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'year']);
    }

    // -------------------------------------------------------------------------
    // Convenience composites
    // -------------------------------------------------------------------------

    /** Adds created_at and updated_at TIMESTAMP columns. */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable()->default(null);
        $this->timestamp('updated_at')->nullable()->default(null);
    }

    /** Adds deleted_at TIMESTAMP NULL for soft deletes. */
    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable()->default(null);
    }

    // -------------------------------------------------------------------------
    // Special types
    // -------------------------------------------------------------------------

    public function uuid(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'uuid']);
    }

    public function ipAddress(string $name = 'ip_address'): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'ipAddress']);
    }

    public function macAddress(string $name = 'mac_address'): ColumnDefinition
    {
        return $this->addColumn(['name' => $name, 'type' => 'macAddress']);
    }

    // -------------------------------------------------------------------------
    // Table-level constraints
    // -------------------------------------------------------------------------

    public function primary(string|array $columns, string $name = ''): void
    {
        $this->commands[] = [
            'type'    => 'primary',
            'columns' => (array) $columns,
            'name'    => $name ?: ('pk_' . $this->table),
        ];
    }

    public function unique(string|array $columns, string $name = ''): void
    {
        $cols = (array) $columns;
        $this->commands[] = [
            'type'    => 'unique',
            'columns' => $cols,
            'name'    => $name ?: ('unique_' . $this->table . '_' . implode('_', $cols)),
        ];
    }

    public function index(string|array $columns, string $name = ''): void
    {
        $cols = (array) $columns;
        $this->commands[] = [
            'type'    => 'index',
            'columns' => $cols,
            'name'    => $name ?: ('idx_' . $this->table . '_' . implode('_', $cols)),
        ];
    }

    public function foreign(string $column, string $name = ''): ForeignKeyDefinition
    {
        $def = new ForeignKeyDefinition([
            'type'       => 'foreign',
            'column'     => $column,
            'name'       => $name ?: ('fk_' . $this->table . '_' . $column),
            'references' => 'id',
            'on'         => '',
            'onDelete'   => 'RESTRICT',
            'onUpdate'   => 'RESTRICT',
        ]);
        $this->commands[] = &$def->attrs;  // reference so chained calls update stored data
        return $def;
    }

    // -------------------------------------------------------------------------
    // Drop / rename helpers (used in Schema::table() for ALTER)
    // -------------------------------------------------------------------------

    public function dropColumn(string $name): void
    {
        $this->commands[] = ['type' => 'dropColumn', 'name' => $name];
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];
    }

    public function dropIndex(string $name): void
    {
        $this->commands[] = ['type' => 'dropIndex', 'name' => $name];
    }

    public function dropForeign(string $name): void
    {
        $this->commands[] = ['type' => 'dropForeign', 'name' => $name];
    }

    public function dropPrimary(): void
    {
        $this->commands[] = ['type' => 'dropPrimary'];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    protected function addColumn(array $attrs): ColumnDefinition
    {
        $attrs += [
            'nullable'      => false,
            'autoIncrement' => false,
            'unsigned'      => false,
            'primary'       => false,
            'unique'        => false,
            'index'         => false,
        ];
        $def = new ColumnDefinition($attrs);
        $this->columns[] = &$def->attrs;   // reference — chained modifiers update stored data
        return $def;
    }
}
