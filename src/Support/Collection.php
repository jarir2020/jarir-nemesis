<?php

namespace Nemesis\Support;

use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;

class Collection implements ArrayAccess, IteratorAggregate, JsonSerializable {
    protected $items = [];

    public function __construct($items = []) {
        $this->items = is_array($items) ? $items : [$items];
    }

    public static function make($items = []) {
        return new static($items);
    }

    public function all() {
        return $this->items;
    }

    public function map(callable $callback) {
        return new static(array_map($callback, $this->items, array_keys($this->items)));
    }

    public function filter(callable $callback = null) {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }
        return new static(array_filter($this->items));
    }

    public function each(callable $callback) {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) break;
        }
        return $this;
    }

    public function push($value) {
        $this->items[] = $value;
        return $this;
    }

    public function put($key, $value) {
        $this->items[$key] = $value;
        return $this;
    }

    public function get($key, $default = null) {
        if (array_key_exists($key, $this->items)) return $this->items[$key];
        return ($default instanceof \Closure) ? $default() : $default;
    }

    public function first(callable $callback = null) {
        if (is_null($callback)) return count($this->items) > 0 ? reset($this->items) : null;
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) return $value;
        }
        return null;
    }

    public function last() {
        return count($this->items) > 0 ? end($this->items) : null;
    }

    public function count() {
        return count($this->items);
    }

    public function toJson() {
        return json_encode($this->items);
    }

    public function toArray() {
        return $this->items;
    }

    public function jsonSerialize(): mixed {
        return $this->items;
    }

    public function getIterator(): \Traversable {
        return new ArrayIterator($this->items);
    }

    public function offsetExists($offset): bool {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value): void {
        if (is_null($offset)) $this->items[] = $value;
        else $this->items[$offset] = $value;
    }

    public function offsetUnset($offset): void {
        unset($this->items[$offset]);
    }
    
    public function __toString() {
        return $this->toJson();
    }
}
