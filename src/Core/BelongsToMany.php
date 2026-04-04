<?php
declare(strict_types=1);

namespace Nemesis\Core;

use Nemesis\Core\Database;

class BelongsToMany {
    protected $related;
    protected $parent;
    protected $table;
    protected $foreignPivotKey;
    protected $relatedPivotKey;

    public function __construct($related, $parent, $table, $foreignPivotKey, $relatedPivotKey) {
        $this->related = $related;
        $this->parent = $parent;
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function get() {
        $relatedTable = $this->related->getTable();
        $sql = "SELECT {$relatedTable}.* FROM {$relatedTable} 
                INNER JOIN {$this->table} ON {$relatedTable}.id = {$this->table}.{$this->relatedPivotKey}
                WHERE {$this->table}.{$this->foreignPivotKey} = :parent_id";
        
        $results = Database::view($sql, ['parent_id' => $this->parent->getKey()]);
        
        if (!$results) return [];
        
        $class = get_class($this->related);
        return array_map(function($row) use ($class) {
            return new $class($row);
        }, $results);
    }

    public function attach($id) {
        Fluent::table($this->table)->insert([
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->relatedPivotKey => $id
        ]);
    }

    public function detach($id = null) {
        $query = Fluent::table($this->table)->where($this->foreignPivotKey, '=', $this->parent->getKey());
        if ($id !== null) {
            $query->where($this->relatedPivotKey, '=', $id);
        }
        $query->delete();
    }
}
