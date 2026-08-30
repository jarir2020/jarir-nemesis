<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Builder returns Collection | Updated: 2026-04-03

namespace Nemesis\Core;

use Nemesis\Support\Collection;

class Builder
{
    protected Model  $model;
    protected Fluent $query;
    protected array  $eagerLoad = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->query = new Fluent($model->getTable(), $model->getConnectionName());
    }

    public function __clone()
    {
        $this->query = clone $this->query;
        $this->eagerLoad = array_values($this->eagerLoad);
    }

    protected function cloneQuery(): Fluent
    {
        return clone $this->query;
    }

    // -------------------------------------------------------------------------
    // WHERE
    // -------------------------------------------------------------------------

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->query->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->query->whereNotIn($column, $values);
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->query->whereBetween($column, $min, $max);
        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        $this->query->whereNotBetween($column, $min, $max);
        return $this;
    }

    public function whereLike(string $column, mixed $value): static
    {
        $this->query->whereLike($column, $value);
        return $this;
    }

    public function whereNotLike(string $column, mixed $value): static
    {
        $this->query->whereNotLike($column, $value);
        return $this;
    }

    public function orWhereLike(string $column, mixed $value): static
    {
        $this->query->orWhereLike($column, $value);
        return $this;
    }

    public function orWhereNotLike(string $column, mixed $value): static
    {
        $this->query->orWhereNotLike($column, $value);
        return $this;
    }

    // -------------------------------------------------------------------------
    // ORDER / LIMIT
    // -------------------------------------------------------------------------

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        $this->query->latest($column);
        return $this;
    }

    public function oldest(string $column = 'created_at'): static
    {
        $this->query->oldest($column);
        return $this;
    }

    public function limit(int $value): static
    {
        $this->query->limit($value);
        return $this;
    }

    public function offset(int $value): static
    {
        $this->query->offset($value);
        return $this;
    }

    public function select(array|string $columns): static
    {
        $this->query->select($columns);
        return $this;
    }

    /**
     * Apply a case-insensitive LIKE search across multiple columns.
     */
    public function search(array|string $columns, ?string $term = null, string $mode = 'contains'): static
    {
        $columns = array_values(array_filter((array) $columns, fn($column) => is_string($column) && $column !== ''));
        $term    = trim((string) $term);

        if ($columns === [] || $term === '') {
            return $this;
        }

        $mode = strtolower($mode);
        $pattern = match ($mode) {
            'starts', 'prefix' => $term . '%',
            'ends', 'suffix' => '%' . $term,
            'exact' => $term,
            default => '%' . $term . '%',
        };

        $this->query->whereNested(function (Fluent $query) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $query->where($column, 'LIKE', $pattern);
                } else {
                    $query->orWhere($column, 'LIKE', $pattern);
                }
            }
        });

        return $this;
    }

    /**
     * Conditionally apply query mutations when the value is truthy.
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Conditionally apply query mutations when the value is falsy.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Apply structured filters. Supported forms:
     *   ['status' => 'published']
     *   ['price' => ['operator' => '>=', 'value' => 50]]
     *   ['category_id' => ['in' => [1, 2]]]
     */
    public function filter(array $filters): static
    {
        foreach ($filters as $column => $filter) {
            if ($filter === null || $filter === '') {
                continue;
            }

            if (is_callable($filter)) {
                $filter($this, $column);
                continue;
            }

            if (is_array($filter)) {
                if (array_is_list($filter)) {
                    $operator = strtolower((string) ($filter[0] ?? '='));
                    $value    = $filter[1] ?? null;
                    $extra    = $filter[2] ?? null;
                } else {
                    if (array_key_exists('in', $filter)) {
                        $operator = 'in';
                        $value    = $filter['in'];
                        $extra    = null;
                    } elseif (array_key_exists('not_in', $filter)) {
                        $operator = 'not_in';
                        $value    = $filter['not_in'];
                        $extra    = null;
                    } elseif (array_key_exists('between', $filter)) {
                        $operator = 'between';
                        $value = $filter['between'];
                        $extra = null;
                    } elseif (array_key_exists('not_between', $filter)) {
                        $operator = 'not_between';
                        $value = $filter['not_between'];
                        $extra = null;
                    } elseif (array_key_exists('null', $filter) && $filter['null']) {
                        $operator = 'null';
                        $value = null;
                        $extra = null;
                    } elseif (array_key_exists('not_null', $filter) && $filter['not_null']) {
                        $operator = 'not_null';
                        $value = null;
                        $extra = null;
                    } elseif (array_key_exists('values', $filter)) {
                        $operator = strtolower((string) ($filter['operator'] ?? $filter['op'] ?? 'in'));
                        $value = $filter['values'];
                        $extra = null;
                    } else {
                        $operator = strtolower((string) ($filter['operator'] ?? $filter['op'] ?? '='));
                        $value    = $filter['value'] ?? null;
                        $extra    = $filter['extra'] ?? ($filter['second'] ?? null);
                    }
                }

                switch ($operator) {
                    case 'in':
                        $this->whereIn((string) $column, (array) $value);
                        break;
                    case 'not_in':
                    case 'not in':
                        $this->whereNotIn((string) $column, (array) $value);
                        break;
                    case 'between':
                        if (is_array($value)) {
                            $bounds = array_values($value);
                            $this->whereBetween((string) $column, $bounds[0] ?? null, $bounds[1] ?? null);
                        } else {
                            $this->whereBetween((string) $column, $value, $extra);
                        }
                        break;
                    case 'not_between':
                    case 'not between':
                        if (is_array($value)) {
                            $bounds = array_values($value);
                            $this->whereNotBetween((string) $column, $bounds[0] ?? null, $bounds[1] ?? null);
                        } else {
                            $this->whereNotBetween((string) $column, $value, $extra);
                        }
                        break;
                    case 'null':
                        $this->whereNull((string) $column);
                        break;
                    case 'not_null':
                    case 'not null':
                        $this->whereNotNull((string) $column);
                        break;
                    default:
                        $this->where((string) $column, $operator, $value);
                        break;
                }

                continue;
            }

            $this->where((string) $column, '=', $filter);
        }

        return $this;
    }

    /**
     * Sort by one or more columns.
     */
    public function sort(array|string $sort, string $direction = 'asc'): static
    {
        if (is_string($sort)) {
            $sort = [$sort => $direction];
        }

        foreach ($sort as $column => $dir) {
            if (is_int($column)) {
                $this->orderBy((string) $dir, $direction);
                continue;
            }

            $this->orderBy((string) $column, (string) $dir);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // READ — return Collection of hydrated Models
    // -------------------------------------------------------------------------

    /**
     * Execute query and return a Collection of hydrated Model instances.
     * Phase 2: changed from plain array to Collection — 2026-04-03
     * v7.1.1 (Gap 4): now applies eager-loaded relations via eagerLoadRelations()
     */
    public function get(array $columns = ['*']): Collection
    {
        $rawResults = $this->cloneQuery()->get(); // Collection of raw arrays from Fluent
        $models     = $this->hydrate($rawResults);
        $this->eagerLoadRelations($models);
        return new Collection($models);
    }

    /** Return the first hydrated Model, or null. */
    public function first(array $columns = ['*']): ?Model
    {
        $item = $this->cloneQuery()->first();
        if ($item !== null && is_array($item)) {
            $model         = new $this->model($item);
            $model->exists = true;
            $this->eagerLoadRelations([$model]);
            return $model;
        }
        return null;
    }

    /**
     * Hydrate raw array rows into Model instances.
     */
    protected function hydrate(iterable $rawResults): array
    {
        $models = [];
        foreach ($rawResults as $item) {
            if (is_array($item)) {
                $model         = new $this->model($item);
                $model->exists = true;
                $models[]      = $model;
            }
        }
        return $models;
    }

    /**
     * v7.1.1 (Gap 4): Eager-load each relation named in $this->eagerLoad
     * using batched queries. Replaces the previous N+1 lazy loading.
     *
     * Supports hasOne, hasMany, belongsTo, belongsToMany.
     * For each relation, runs at most ONE additional query no matter how
     * many parent models were hydrated.
     */
    protected function eagerLoadRelations(array $models): void
    {
        if ($models === [] || $this->eagerLoad === []) {
            return;
        }

        foreach ($this->eagerLoad as $relationName) {
            $method = $relationName;
            // Allow dot-notation (e.g. "comments.author") — we eagerly load
            // each segment independently. Only the first level is required
            // for N+1 elimination; nested eager-loading is a future enhancement.
            $segments = explode('.', $method);
            $this->eagerLoadSingle($models, $segments[0]);
        }
    }

    protected function eagerLoadSingle(array $models, string $relationName): void
    {
        if ($models === [] || $relationName === '') {
            return;
        }

        $first = $models[0];
        if (!method_exists($first, $relationName)) {
            return;
        }

        $relation = $first->$relationName();
        $relationType = $this->detectRelationType($relation);

        switch ($relationType) {
            case 'HasMany':
                $this->eagerLoadHasMany($models, $relation, $relationName);
                break;
            case 'HasOne':
                $this->eagerLoadHasOne($models, $relation, $relationName);
                break;
            case 'BelongsTo':
                $this->eagerLoadBelongsTo($models, $relation, $relationName);
                break;
            case 'BelongsToMany':
                $this->eagerLoadBelongsToMany($models, $relation, $relationName);
                break;
            default:
                // Unknown relation type — fall back to lazy loading per model.
                foreach ($models as $model) {
                    $model->getRelationValue($relationName);
                }
        }
    }

    protected function detectRelationType(object $relation): string
    {
        $class = get_class($relation);
        $base  = basename(str_replace('\\', '/', $class));
        return $base;
    }

    protected function eagerLoadHasMany(array $models, HasMany $relation, string $relationName): void
    {
        $reflection = new \ReflectionClass($relation);
        $foreignKey = $reflection->getProperty('foreignKey')->getValue($relation);
        $localKey   = $reflection->getProperty('localKey')->getValue($relation);
        $related    = $reflection->getProperty('related')->getValue($relation);

        $parentIds = array_values(array_unique(array_filter(array_map(
            fn(Model $m) => $m->{$localKey} ?? null,
            $models
        ))));

        if ($parentIds === []) {
            return;
        }

        // Use the related model class so subclasses with their own
        // global scopes / table overrides are honoured.
        $relatedRows = $related::whereIn($foreignKey, $parentIds)->get();

        $byParent = [];
        foreach ($relatedRows as $row) {
            $byParent[$row->{$foreignKey}][] = $row;
        }

        foreach ($models as $model) {
            $key = $model->{$localKey} ?? null;
            $model->setRelation($relationName, $byParent[$key] ?? []);
        }
    }

    protected function eagerLoadHasOne(array $models, HasOne $relation, string $relationName): void
    {
        $reflection = new \ReflectionClass($relation);
        $foreignKey = $reflection->getProperty('foreignKey')->getValue($relation);
        $localKey   = $reflection->getProperty('localKey')->getValue($relation);
        $related    = $reflection->getProperty('related')->getValue($relation);

        $parentIds = array_values(array_unique(array_filter(array_map(
            fn(Model $m) => $m->{$localKey} ?? null,
            $models
        ))));

        if ($parentIds === []) {
            return;
        }

        $relatedRows = $related::whereIn($foreignKey, $parentIds)->get();

        $byParent = [];
        foreach ($relatedRows as $row) {
            $byParent[$row->{$foreignKey}] = $row;
        }

        foreach ($models as $model) {
            $key = $model->{$localKey} ?? null;
            $model->setRelation($relationName, $byParent[$key] ?? null);
        }
    }

    protected function eagerLoadBelongsTo(array $models, BelongsTo $relation, string $relationName): void
    {
        $reflection = new \ReflectionClass($relation);
        $foreignKey = $reflection->getProperty('foreignKey')->getValue($relation);
        $ownerKey   = $reflection->getProperty('ownerKey')->getValue($relation);
        $related    = $reflection->getProperty('related')->getValue($relation);

        $childKeys = array_values(array_unique(array_filter(array_map(
            fn(Model $m) => $m->{$foreignKey} ?? null,
            $models
        ))));

        if ($childKeys === []) {
            return;
        }

        $relatedRows = $related::whereIn($ownerKey, $childKeys)->get();

        $byOwner = [];
        foreach ($relatedRows as $row) {
            $byOwner[$row->{$ownerKey}] = $row;
        }

        foreach ($models as $model) {
            $key = $model->{$foreignKey} ?? null;
            $model->setRelation($relationName, $byOwner[$key] ?? null);
        }
    }

    protected function eagerLoadBelongsToMany(array $models, BelongsToMany $relation, string $relationName): void
    {
        $reflection = new \ReflectionClass($relation);
        $table           = $reflection->getProperty('table')->getValue($relation);
        $foreignPivotKey = $reflection->getProperty('foreignPivotKey')->getValue($relation);
        $relatedPivotKey = $reflection->getProperty('relatedPivotKey')->getValue($relation);
        $related         = $reflection->getProperty('related')->getValue($relation);

        $relatedTable = $related->getTable();

        $parentIds = array_values(array_unique(array_filter(array_map(
            fn(Model $m) => $m->getKey(),
            $models
        ))));

        if ($parentIds === []) {
            return;
        }

        $placeholders = [];
        $params       = [];
        foreach ($parentIds as $i => $id) {
            $key = ":p{$i}";
            $placeholders[] = $key;
            $params[$key]   = $id;
        }
        $inList = implode(',', $placeholders);

        $sql = "SELECT {$relatedTable}.*, {$table}.{$foreignPivotKey} AS __pivot_{$foreignPivotKey}
                FROM {$relatedTable}
                INNER JOIN {$table} ON {$relatedTable}.id = {$table}.{$relatedPivotKey}
                WHERE {$table}.{$foreignPivotKey} IN ({$inList})";

        $rows = Database::view($sql, $params);
        if (!$rows) {
            foreach ($models as $model) {
                $model->setRelation($relationName, []);
            }
            return;
        }

        $class = get_class($related);
        $byParent = [];
        foreach ($rows as $row) {
            $instance = new $class($row);
            $pivotKey = "__pivot_{$foreignPivotKey}";
            $byParent[$row->{$pivotKey}][] = $instance;
        }

        foreach ($models as $model) {
            $key = $model->getKey();
            $model->setRelation($relationName, $byParent[$key] ?? []);
        }
    }

    /** Find by primary key — returns Model or null. */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        $query = clone $this;
        return $query->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Find by primary key or throw NotFoundException.
     *
     * @throws \Nemesis\Exceptions\NotFoundException
     */
    public function findOrFail(mixed $id): Model
    {
        $model = $this->find($id);
        if ($model === null) {
            $class = get_class($this->model);
            throw new \Nemesis\Exceptions\NotFoundException("{$class} with id [{$id}] not found.");
        }
        return $model;
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return $this->cloneQuery()->count();
    }

    public function max(string $column): mixed
    {
        return $this->cloneQuery()->max($column);
    }

    public function min(string $column): mixed
    {
        return $this->cloneQuery()->min($column);
    }

    public function sum(string $column): mixed
    {
        return $this->cloneQuery()->sum($column);
    }

    public function avg(string $column): mixed
    {
        return $this->cloneQuery()->avg($column);
    }

    // -------------------------------------------------------------------------
    // WRITE
    // -------------------------------------------------------------------------

    public function insert(array $values): int|string
    {
        return $this->query->insert($values);
    }

    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    public function truncate(): void
    {
        // Use raw exec — TRUNCATE is DDL, not parameterised
        Database::connection($this->model->getConnectionName())->exec('TRUNCATE TABLE ' . $this->model->getTable());
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    /**
     * Paginate query results. Returns Paginator with Collection of Models.
     * Phase 2: fixed protected-property access bug — 2026-04-03
     */
    public function paginate(int $perPage = 15, int $page = null): Paginator
    {
        if ($page === null) {
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        }
        $total  = $this->count();
        $offset = ($page - 1) * $perPage;

        $rawItems = $this->cloneQuery()->limit($perPage)->offset($offset)->get(); // Collection of arrays
        $models   = $this->hydrate($rawItems);
        $this->eagerLoadRelations($models);
        return new Paginator(new Collection($models), $total, $perPage, $page);
    }

    /**
     * Paginate using the current request query string.
     */
    public function paginateFromRequest(int $perPage = 15, string $pageKey = 'page'): Paginator
    {
        $page = isset($_GET[$pageKey]) ? max(1, (int) $_GET[$pageKey]) : 1;
        return $this->paginate($perPage, $page);
    }

    // -------------------------------------------------------------------------
    // Eager loading (future)
    // -------------------------------------------------------------------------

    public function with(array|string $relations): static
    {
        $this->eagerLoad = array_merge(
            $this->eagerLoad,
            is_array($relations) ? $relations : [$relations]
        );
        return $this;
    }
}
