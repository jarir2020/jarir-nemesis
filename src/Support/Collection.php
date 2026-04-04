<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — ORM Collection | Updated: 2026-04-03

namespace Nemesis\Support;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items = [];

    public function __construct(mixed $items = [])
    {
        $this->items = $this->asArray($items);
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(mixed $items = []): static
    {
        return new static($items);
    }

    // -------------------------------------------------------------------------
    // Basics
    // -------------------------------------------------------------------------

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    // -------------------------------------------------------------------------
    // Access
    // -------------------------------------------------------------------------

    public function get(int|string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }
        return ($default instanceof \Closure) ? $default() : $default;
    }

    public function first(callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return count($this->items) > 0 ? reset($this->items) : null;
        }
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) return $value;
        }
        return null;
    }

    public function last(callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return count($this->items) > 0 ? end($this->items) : null;
        }
        $found = null;
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) $found = $value;
        }
        return $found;
    }

    public function nth(int $step, int $offset = 0): static
    {
        $result = [];
        $position = 0;
        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $result[] = $item;
            }
            $position++;
        }
        return new static($result);
    }

    // -------------------------------------------------------------------------
    // Contains / search
    // -------------------------------------------------------------------------

    public function contains(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 2) {
            // key-value pair check
            foreach ($this->items as $item) {
                $v = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
                if ($v == $value) return true;
            }
            return false;
        }
        if (is_callable($key)) {
            foreach ($this->items as $k => $item) {
                if ($key($item, $k)) return true;
            }
            return false;
        }
        return in_array($key, $this->items);
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    // -------------------------------------------------------------------------
    // Mutation (returns new instance — immutable pattern)
    // -------------------------------------------------------------------------

    public function push(mixed $value): static
    {
        $items = $this->items;
        $items[] = $value;
        return new static($items);
    }

    public function prepend(mixed $value, mixed $key = null): static
    {
        $items = $this->items;
        if ($key !== null) {
            $items = [$key => $value] + $items;
        } else {
            array_unshift($items, $value);
        }
        return new static($items);
    }

    public function put(int|string $key, mixed $value): static
    {
        $items = $this->items;
        $items[$key] = $value;
        return new static($items);
    }

    public function forget(int|string $key): static
    {
        $items = $this->items;
        unset($items[$key]);
        return new static($items);
    }

    public function merge(mixed $items): static
    {
        return new static(array_merge($this->items, $this->asArray($items)));
    }

    public function concat(mixed $items): static
    {
        $result = $this->items;
        foreach ($this->asArray($items) as $item) {
            $result[] = $item;
        }
        return new static($result);
    }

    // -------------------------------------------------------------------------
    // Slicing
    // -------------------------------------------------------------------------

    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(array_slice($this->items, $limit));
        }
        return new static(array_slice($this->items, 0, $limit));
    }

    public function skip(int $count): static
    {
        return new static(array_slice($this->items, $count));
    }

    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static([]);
        }
        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }
        return new static($chunks);
    }

    public function slice(int $offset, int $length = null, bool $preserveKeys = false): static
    {
        return new static(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    // -------------------------------------------------------------------------
    // Transformation
    // -------------------------------------------------------------------------

    public function map(callable $callback): static
    {
        $keys   = array_keys($this->items);
        $values = array_map($callback, $this->items, $keys);
        return new static(array_combine($keys, $values));
    }

    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->flatten();
    }

    public function filter(callable $callback = null): static
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }
        return new static(array_filter($this->items));
    }

    /** Filter by key-value pair (like Eloquent Collection::where) */
    public function where(string $key, mixed $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->filter(function ($item) use ($key, $operator, $value) {
            $actual = is_array($item)  ? ($item[$key]  ?? null)
                    : (is_object($item) ? ($item->$key ?? null)
                    : null);
            return match ($operator) {
                '=', '=='  => $actual == $value,
                '==='      => $actual === $value,
                '!='       => $actual != $value,
                '!=='      => $actual !== $value,
                '>'        => $actual > $value,
                '>='       => $actual >= $value,
                '<'        => $actual < $value,
                '<='       => $actual <= $value,
                default    => $actual == $value,
            };
        });
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) break;
        }
        return $this;
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static($this->flattenArray($this->items, $depth));
    }

    public function collapse(): static
    {
        $results = [];
        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }
        return new static($results);
    }

    // -------------------------------------------------------------------------
    // Extraction
    // -------------------------------------------------------------------------

    /**
     * Pluck values of a given key, optionally keyed by another key.
     */
    public function pluck(string $value, string $key = null): static
    {
        $results = [];
        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$value] ?? null) : (is_object($item) ? ($item->$value ?? null) : null);
            if ($key !== null) {
                $itemKey = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
                $results[$itemKey] = $itemValue;
            } else {
                $results[] = $itemValue;
            }
        }
        return new static($results);
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    // -------------------------------------------------------------------------
    // Grouping
    // -------------------------------------------------------------------------

    public function groupBy(string|callable $groupKey): static
    {
        $results = [];
        foreach ($this->items as $key => $item) {
            $groupValue = is_callable($groupKey)
                ? $groupKey($item, $key)
                : (is_array($item) ? ($item[$groupKey] ?? null) : (is_object($item) ? ($item->$groupKey ?? null) : null));
            $results[$groupValue][] = $item;
        }
        return new static(array_map(fn($g) => new static($g), $results));
    }

    public function keyBy(string|callable $keyBy): static
    {
        $results = [];
        foreach ($this->items as $item) {
            $key = is_callable($keyBy)
                ? $keyBy($item)
                : (is_array($item) ? ($item[$keyBy] ?? null) : (is_object($item) ? ($item->$keyBy ?? null) : null));
            $results[$key] = $item;
        }
        return new static($results);
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $aVal = is_callable($key) ? $key($a) : (is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null));
            $bVal = is_callable($key) ? $key($b) : (is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null));
            $result = $aVal <=> $bVal;
            return $descending ? -$result : $result;
        });
        return new static($items);
    }

    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, true);
    }

    public function sort(callable $callback = null): static
    {
        $items = $this->items;
        $callback ? usort($items, $callback) : sort($items);
        return new static($items);
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    // -------------------------------------------------------------------------
    // Uniqueness
    // -------------------------------------------------------------------------

    public function unique(string|callable $key = null): static
    {
        if ($key === null) {
            return new static(array_unique($this->items));
        }
        $seen  = [];
        $result = [];
        foreach ($this->items as $item) {
            $id = is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null));
            if (!in_array($id, $seen, true)) {
                $seen[]   = $id;
                $result[] = $item;
            }
        }
        return new static($result);
    }

    public function duplicates(string|callable $key = null): static
    {
        $seen  = [];
        $dupes = [];
        foreach ($this->items as $item) {
            $id = $key === null ? serialize($item)
               : (is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null)));
            if (in_array($id, $seen, true)) {
                $dupes[] = $item;
            }
            $seen[] = $id;
        }
        return new static($dupes);
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    public function sum(string|callable $key = null): int|float
    {
        if ($key === null) {
            return array_sum($this->items);
        }
        $total = 0;
        foreach ($this->items as $item) {
            $total += is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? 0) : (is_object($item) ? ($item->$key ?? 0) : 0));
        }
        return $total;
    }

    public function avg(string|callable $key = null): float
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($key) / $count : 0.0;
    }

    public function min(string|callable $key = null): mixed
    {
        if ($key === null) {
            return min($this->items);
        }
        return $this->pluck($key)->min();
    }

    public function max(string|callable $key = null): mixed
    {
        if ($key === null) {
            return max($this->items);
        }
        return $this->pluck($key)->max();
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags);
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof self) return $item->toArray();
            if (is_object($item) && method_exists($item, 'toArray')) return $item->toArray();
            return $item;
        }, $this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    // -------------------------------------------------------------------------
    // Array/Iterator interfaces
    // -------------------------------------------------------------------------

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) $this->items[] = $value;
        else $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function asArray(mixed $items): array
    {
        if (is_array($items)) return $items;
        if ($items instanceof self) return $items->all();
        if ($items instanceof \Traversable) return iterator_to_array($items);
        return [$items];
    }

    private function flattenArray(array $array, int $depth): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $result = array_merge($result, $this->flattenArray($item, $depth - 1));
            } elseif ($item instanceof self && $depth > 0) {
                $result = array_merge($result, $this->flattenArray($item->all(), $depth - 1));
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }
}
