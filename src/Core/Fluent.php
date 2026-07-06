<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — ORM/Fluent | Updated: 2026-04-03

namespace Nemesis\Core;

use Nemesis\Support\Collection;

class Fluent
{
    protected string $table;
    protected ?string $connection = null;
    protected string $baseQuery;          // FROM clause (set once in constructor)
    protected array  $params        = [];
    protected array  $whereParts    = []; // Individual WHERE conditions
    protected array  $selectColumns = []; // Empty = SELECT *
    protected array  $orderParts    = [];
    protected ?int   $limitValue    = null;
    protected ?int   $offsetValue   = null;
    protected array  $joinParts     = [];
    protected array  $groupByParts  = [];
    protected string $havingClause  = '';

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct(string $table, ?string $connection = null)
    {
        $this->table     = '`' . str_replace('`', '``', $table) . '`';
        $this->connection = $connection !== null && $connection !== '' ? strtolower(trim($connection)) : null;
        $this->baseQuery = "SELECT * FROM {$this->table}";
    }

    public static function table(string $table, ?string $connection = null): static
    {
        return new static($table, $connection);
    }

    public function getTable(): string
    {
        return str_replace('`', '', $this->table);
    }

    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    public function setConnection(?string $connection): static
    {
        $this->connection = $connection !== null && $connection !== '' ? strtolower(trim($connection)) : null;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Column selection
    // -------------------------------------------------------------------------

    public function select(array|string $columns): static
    {
        $this->selectColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    // -------------------------------------------------------------------------
    // WHERE conditions
    // -------------------------------------------------------------------------

    public function where(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }
        $paramKey = 'w_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->whereParts[] = "{$column} {$operator} :{$paramKey}";
        $this->params[$paramKey] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }
        $paramKey = 'or_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->whereParts[] = "OR {$column} {$operator} :{$paramKey}";
        $this->params[$paramKey] = $value;
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->whereParts[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->whereParts[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            // Impossible condition — return zero rows
            $this->whereParts[] = "1 = 0";
            return $this;
        }
        $placeholders = [];
        foreach ($values as $i => $val) {
            $paramKey = 'win_' . str_replace('.', '_', $column) . '_' . $i . '_' . count($this->params);
            $placeholders[]          = ":{$paramKey}";
            $this->params[$paramKey] = $val;
        }
        $this->whereParts[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        if (empty($values)) {
            return $this;
        }
        $placeholders = [];
        foreach ($values as $i => $val) {
            $paramKey = 'wnin_' . str_replace('.', '_', $column) . '_' . $i . '_' . count($this->params);
            $placeholders[]          = ":{$paramKey}";
            $this->params[$paramKey] = $val;
        }
        $this->whereParts[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $minKey = 'wb_min_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $maxKey = 'wb_max_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->whereParts[]  = "{$column} BETWEEN :{$minKey} AND :{$maxKey}";
        $this->params[$minKey] = $min;
        $this->params[$maxKey] = $max;
        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        $minKey = 'wnb_min_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $maxKey = 'wnb_max_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->whereParts[]    = "{$column} NOT BETWEEN :{$minKey} AND :{$maxKey}";
        $this->params[$minKey] = $min;
        $this->params[$maxKey] = $max;
        return $this;
    }

    public function whereLike(string $column, mixed $value, string $boolean = 'AND'): static
    {
        $paramKey = 'wl_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $clause = "{$column} LIKE :{$paramKey}";
        if (strtoupper($boolean) === 'OR') {
            $clause = 'OR ' . $clause;
        }

        $this->whereParts[] = $clause;
        $this->params[$paramKey] = $value;
        return $this;
    }

    public function whereNotLike(string $column, mixed $value, string $boolean = 'AND'): static
    {
        $paramKey = 'wnl_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $clause = "{$column} NOT LIKE :{$paramKey}";
        if (strtoupper($boolean) === 'OR') {
            $clause = 'OR ' . $clause;
        }

        $this->whereParts[] = $clause;
        $this->params[$paramKey] = $value;
        return $this;
    }

    public function orWhereLike(string $column, mixed $value): static
    {
        return $this->whereLike($column, $value, 'OR');
    }

    public function orWhereNotLike(string $column, mixed $value): static
    {
        return $this->whereNotLike($column, $value, 'OR');
    }

    /**
     * Group nested WHERE conditions with parentheses.
     */
    public function whereNested(callable $callback, string $boolean = 'AND'): static
    {
        $nested = clone $this;
        $nested->whereParts = [];
        $nested->params = $this->params;
        $callback($nested);

        if (empty($nested->whereParts)) {
            return $this;
        }

        $group = '(' . $nested->compileWhere() . ')';
        if (!empty($this->whereParts)) {
            $group = strtoupper($boolean) === 'OR' ? 'OR ' . $group : 'AND ' . $group;
        }

        $this->whereParts[] = $group;
        $this->params = $nested->params;
        return $this;
    }

    // Backward-compat aliases removed in 3.x but kept as no-ops for safety
    public function whereGET(string $column, mixed $operator, mixed $value): static    { return $this->where($column, $operator, $value); }
    public function whereDELETE(string $column, mixed $operator, mixed $value): static { return $this->where($column, $operator, $value); }
    public function whereUpdate(string $column, mixed $operator, mixed $value): static { return $this->where($column, $operator, $value); }

    // -------------------------------------------------------------------------
    // JOINs
    // -------------------------------------------------------------------------

    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->joinParts[] = "INNER JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joinParts[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joinParts[] = "RIGHT JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    // -------------------------------------------------------------------------
    // ORDER BY / GROUP BY / HAVING
    // -------------------------------------------------------------------------

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction          = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderParts[] = "{$column} {$direction}";
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function groupBy(string $column): static
    {
        $this->groupByParts[] = $column;
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $paramKey = 'hav_' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->havingClause    = "HAVING {$column} {$operator} :{$paramKey}";
        $this->params[$paramKey] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // LIMIT / OFFSET
    // -------------------------------------------------------------------------

    public function limit(int $limit): static
    {
        $this->limitValue = max(0, $limit); // R7 fix: typed int — 2026-04-02
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = max(0, $offset); // R7 fix: typed int — 2026-04-02
        return $this;
    }

    // -------------------------------------------------------------------------
    // SQL builder
    // -------------------------------------------------------------------------

    protected function buildSelectSql(): string
    {
        $cols = empty($this->selectColumns) ? '*' : implode(', ', $this->selectColumns);
        $sql  = "SELECT {$cols} FROM {$this->table}";

        if (!empty($this->joinParts)) {
            $sql .= ' ' . implode(' ', $this->joinParts);
        }
        if (!empty($this->whereParts)) {
            $sql .= ' WHERE ' . $this->compileWhere();
        }
        if (!empty($this->groupByParts)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupByParts);
        }
        if ($this->havingClause !== '') {
            $sql .= ' ' . $this->havingClause;
        }
        if (!empty($this->orderParts)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderParts);
        }
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }
        return $sql;
    }

    /**
     * Compile WHERE parts: strip leading "OR" from first condition,
     * join remaining parts with a space (they carry AND/OR prefix).
     */
    protected function compileWhere(): string
    {
        $parts = $this->whereParts;
        // First part must not start with OR
        if (!empty($parts)) {
            $parts[0] = preg_replace('/^OR\s+/i', '', $parts[0]);
        }
        // Parts that don't start with OR get AND prepended (except first)
        for ($i = 1; $i < count($parts); $i++) {
            if (!preg_match('/^(OR|AND)\s/i', $parts[$i])) {
                $parts[$i] = 'AND ' . $parts[$i];
            }
        }
        return implode(' ', $parts);
    }

    // -------------------------------------------------------------------------
    // READ operations  — all return Collection
    // -------------------------------------------------------------------------

    /**
     * Execute SELECT and return a Collection of raw associative arrays.
     * Phase 2: changed from plain array to Collection — 2026-04-03
     */
    public function get(): Collection
    {
        $rows = Database::view($this->buildSelectSql(), $this->params, $this->connection);
        return new Collection($rows ?: []);
    }

    /** Return the first row as a plain array, or null. */
    public function first(): mixed
    {
        $result = $this->limit(1)->get();
        return $result->first();
    }

    /** Find a row by primary key (returns plain array or null). */
    public function find(mixed $id, string $column = 'id'): mixed
    {
        return $this->where($column, '=', $id)->first();
    }

    /** Alias of get() — returns Collection. */
    public function all(): Collection
    {
        return $this->get();
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    public function count(): int
    {
        $cols = empty($this->selectColumns) ? '*' : implode(', ', $this->selectColumns);
        $sql  = "SELECT COUNT({$cols}) as aggregate FROM {$this->table}";
        if (!empty($this->joinParts))    $sql .= ' ' . implode(' ', $this->joinParts);
        if (!empty($this->whereParts))   $sql .= ' WHERE ' . $this->compileWhere();
        if (!empty($this->groupByParts)) $sql .= ' GROUP BY ' . implode(', ', $this->groupByParts);
        $result = Database::view($sql, $this->params, $this->connection);
        return (int) ($result[0]['aggregate'] ?? 0);
    }

    public function max(string $column): mixed
    {
        $sql = "SELECT MAX({$column}) as aggregate FROM {$this->table}";
        if (!empty($this->whereParts)) $sql .= ' WHERE ' . $this->compileWhere();
        $result = Database::view($sql, $this->params, $this->connection);
        return $result[0]['aggregate'] ?? null;
    }

    public function min(string $column): mixed
    {
        $sql = "SELECT MIN({$column}) as aggregate FROM {$this->table}";
        if (!empty($this->whereParts)) $sql .= ' WHERE ' . $this->compileWhere();
        $result = Database::view($sql, $this->params, $this->connection);
        return $result[0]['aggregate'] ?? null;
    }

    public function sum(string $column): mixed
    {
        $sql = "SELECT SUM({$column}) as aggregate FROM {$this->table}";
        if (!empty($this->whereParts)) $sql .= ' WHERE ' . $this->compileWhere();
        $result = Database::view($sql, $this->params, $this->connection);
        return $result[0]['aggregate'] ?? 0;
    }

    public function avg(string $column): mixed
    {
        $sql = "SELECT AVG({$column}) as aggregate FROM {$this->table}";
        if (!empty($this->whereParts)) $sql .= ' WHERE ' . $this->compileWhere();
        $result = Database::view($sql, $this->params, $this->connection);
        return $result[0]['aggregate'] ?? 0;
    }

    // -------------------------------------------------------------------------
    // WRITE operations
    // -------------------------------------------------------------------------

    public function insert(array $data): int|string
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        $sql          = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        Database::create($sql, $data, $this->connection);
        return Database::connection($this->connection)->lastInsertId();
    }

    public function update(array $data): int
    {
        if (empty($this->whereParts)) {
            throw new \RuntimeException("WHERE clause is required for update queries.");
        }
        $set = [];
        foreach ($data as $column => $value) {
            $paramKey = 'upd_' . $column . '_' . count($this->params);
            $set[]    = "{$column} = :{$paramKey}";
            $this->params[$paramKey] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . ' WHERE ' . $this->compileWhere();
        return Database::update($sql, $this->params, $this->connection);
    }

    public function delete(): int
    {
        if (empty($this->whereParts)) {
            throw new \RuntimeException("WHERE clause is required for delete queries.");
        }
        $sql = "DELETE FROM {$this->table} WHERE " . $this->compileWhere();
        return Database::delete($sql, $this->params, $this->connection);
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    public function paginate(int $perPage = 15, int $page = null): Paginator
    {
        if ($page === null) {
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        }
        $total  = (clone $this)->count();
        $offset = ($page - 1) * $perPage;
        $items  = (clone $this)->limit($perPage)->offset($offset)->get(); // Collection
        return new Paginator($items, $total, $perPage, $page);
    }
}
