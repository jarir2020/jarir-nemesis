<?php
declare(strict_types=1);

namespace Nemesis\Database\Factories;

use Nemesis\Core\Database;

abstract class Factory {
    protected $model;
    protected $count = null;
    protected $states = [];

    abstract public function definition();

    public function count($count) {
        $this->count = $count;
        return $this;
    }

    public function state(array $state) {
        $this->states = array_merge($this->states, $state);
        return $this;
    }

    public function make($attributes = []) {
        $data = array_merge($this->definition(), $this->states, $attributes);
        
        // Return simple object if no model is set (or just array)
        if (!$this->model) {
            return (object) $data;
        }

        return new $this->model($data);
    }

    public function create($attributes = []) {
        $instance = $this->make($attributes);
        
        // Persist to DB
        // Assuming Model has a save() method or we use Fluent directly
        // Since our models use Fluent internally, we can use the Model's mechanisms if available
        // But our Base Model doesn't implemented full save() yet in all cases, so fallback to direct Insert
        
        $table = $instance->getTable();
        $id = Database::table($table)->insert($instance->toArray());
        
        // Reload fresh from DB
        return $this->model::find($id);
    }

    // Static helper to start a factory
    public static function new() {
        return new static();
    }
}
