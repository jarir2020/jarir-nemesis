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
        $this->query = new Fluent($model->getTable());
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

    // -------------------------------------------------------------------------
    // READ — return Collection of hydrated Models
    // -------------------------------------------------------------------------

    /**
     * Execute query and return a Collection of hydrated Model instances.
     * Phase 2: changed from plain array to Collection — 2026-04-03
     */
    public function get(array $columns = ['*']): Collection
    {
        $rawResults = $this->query->get(); // Collection of raw arrays from Fluent
        $models     = [];
        foreach ($rawResults as $item) {
            if (is_array($item)) {
                $model         = new $this->model($item);
                $model->exists = true;
                $models[]      = $model;
            }
        }
        return new Collection($models);
    }

    /** Return the first hydrated Model, or null. */
    public function first(array $columns = ['*']): ?Model
    {
        $item = $this->query->first();
        if ($item !== null && is_array($item)) {
            $model         = new $this->model($item);
            $model->exists = true;
            return $model;
        }
        return null;
    }

    /** Find by primary key — returns Model or null. */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
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
        return $this->query->count();
    }

    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    public function sum(string $column): mixed
    {
        return $this->query->sum($column);
    }

    public function avg(string $column): mixed
    {
        return $this->query->avg($column);
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
        Database::connect()->exec('TRUNCATE TABLE ' . $this->model->getTable());
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

        $rawItems = $this->query->limit($perPage)->offset($offset)->get(); // Collection of arrays
        $models   = [];
        foreach ($rawItems as $item) {
            if (is_array($item)) {
                $model         = new $this->model($item);
                $model->exists = true;
                $models[]      = $model;
            }
        }
        return new Paginator(new Collection($models), $total, $perPage, $page);
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
