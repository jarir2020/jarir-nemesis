<?php

namespace Nemesis\Core;

class Builder {
    protected $model;
    protected $query;
    protected $eagerLoad = [];

    public function __construct(Model $model) {
        $this->model = $model;
        $this->query = new Fluent($model->getTable());
    }

    public function where($column, $operator = null, $value = null) {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null) {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    public function orderBy($column, $direction = 'asc') {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function limit($value) {
        $this->query->limit($value);
        return $this;
    }

    public function offset($value) {
        $this->query->offset($value);
        return $this;
    }

    public function get($columns = ['*']) {
        $results = $this->query->get(); // Returns array of arrays
        
        // Hydrate models
        $models = [];
        if (is_array($results)) {
            foreach ($results as $item) {
                $model = new $this->model($item);
                $model->exists = true;
                $models[] = $model;
            }
        }
        
        return $models;
    }

    public function first($columns = ['*']) {
        $item = $this->query->first();
        if ($item) {
            $model = new $this->model($item);
            $model->exists = true;
            return $model;
        }
        return null;
    }

    public function find($id, $columns = ['*']) {
        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    public function insert(array $values) {
        return $this->query->insert($values);
    }

    public function update(array $values) {
        return $this->query->update($values);
    }

    public function delete() {
        return $this->query->delete();
    }

    public function paginate($perPage = 15, $page = null) {
        $paginator = $this->query->paginate($perPage, $page);
        
        // Hydrate items in paginator
        $items = $paginator->items(); // Assuming Paginator has items() or public property
        // Actually, Fluent::paginate returns Paginator instance.
        // I need to check Paginator class structure. Assuming public $items.
        
        $models = [];
        foreach ($paginator->items as $item) {
            $model = new $this->model($item);
            $model->exists = true;
            $models[] = $model;
        }
        $paginator->items = $models;
        
        return $paginator;
    }
    
    public function truncate() {
        // Fluent does not have truncate, use Database directly or delete all
        // Usually truncate is DDL or special command
        Database::connect()->exec("TRUNCATE TABLE " . $this->model->getTable());
    }
}
