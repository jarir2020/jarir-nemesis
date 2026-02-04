<?php
namespace Nemesis\Core;

use PDOException;
use Nemesis\Core\Database;

class Fluent {

    protected $table;
    protected $query;
    protected $params = [];
    protected $whereClause;
    // Constructor to set the table
    public function __construct($table) {
        $this->table = $table;
        $this->query = "SELECT * FROM {$table}";
    }

    // Static method to instantiate Fluent with the table
    public static function table($table) {
        return new self($table);
    }

    // Generic where method to replace specific ones
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (empty($this->whereClause)) {
            $this->whereClause = "WHERE {$column} {$operator} :where_{$column}";
        } else {
            $this->whereClause .= " AND {$column} {$operator} :where_{$column}";
        }

        $this->params["where_{$column}"] = $value;
        return $this;
    }

    // Deprecated methods for backward compatibility
    public function whereGET($column, $operator, $value) { return $this->where($column, $operator, $value); }
    public function whereDELETE($column, $operator, $value) { return $this->where($column, $operator, $value); }
    public function whereUpdate($column, $operator, $value) { return $this->where($column, $operator, $value); }

    // Add order by clause to the query
    public function orderBy($column, $direction = 'ASC') {
        $this->query .= " ORDER BY {$column} {$direction}";
        return $this;
    }

    // Add limit clause to the query
    public function limit($limit) {
        $this->query .= " LIMIT {$limit}";
        return $this;
    }

    // Add offset clause to the query
    public function offset($offset) {
        $this->query .= " OFFSET {$offset}";
        return $this;
    }

    // Perform the select query
    public function get() {
        if (!empty($this->whereClause) && strpos($this->query, "WHERE") === false) {
            $this->query .= " " . $this->whereClause;
        }
        return Database::view($this->query, $this->params);
    }

    // Retrieve the first result of the query
    public function first() {
        $result = $this->limit(1)->get();
        return $result ? $result[0] : null;
    }

    // Find record by primary key
    public function find($id, $column = 'id') {
        return $this->where($column, '=', $id)->first();
    }

    // Get all records from the table
    public function all() {
        return $this->get();
    }

    // Count records
    public function count() {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        if (!empty($this->whereClause)) {
            $sql .= " " . $this->whereClause;
        }
        $result = Database::view($sql, $this->params);
        return (int) ($result[0]['count'] ?? 0);
    }

    // Insert data into the table and return last insert ID
    public function insert($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($item) => ":{$item}", array_keys($data)));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        Database::create($sql, $data);
        return Database::connect()->lastInsertId();
    }

    // Update data in the table
    public function update($data) {
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "{$column} = :update_{$column}";
            $this->params["update_{$column}"] = $value;
        }
    
        if (!empty($this->whereClause)) {
            $this->query = "UPDATE {$this->table} SET " . implode(', ', $set) . " {$this->whereClause}";
        } else {
            throw new \Exception("WHERE clause is missing in update query.");
        }
    
        return Database::update($this->query, $this->params);
    }
    

    // Delete records from the table
    public function delete() {
        if (empty($this->whereClause)) {
            throw new \Exception("WHERE clause is missing in delete query.");
        }
    
        $this->query = "DELETE FROM {$this->table} {$this->whereClause}";
    
        return Database::delete($this->query, $this->params);
    }
    

    // Add an OR condition to the WHERE clause
    public function orWhere($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (empty($this->whereClause)) {
            $this->whereClause = "WHERE {$column} {$operator} :or_{$column}";
        } else {
            $this->whereClause .= " OR {$column} {$operator} :or_{$column}";
        }
        $this->params["or_{$column}"] = $value;
        return $this;
    }

    // Join another table with this one (INNER JOIN)
    public function join($table, $first, $operator, $second) {
        $this->query .= " INNER JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    // Add GROUP BY clause to the query
    public function groupBy($column) {
        $this->query .= " GROUP BY {$column}";
        return $this;
    }

    // Add HAVING clause to the query
    public function having($column, $operator, $value) {
        $this->whereClause .= " HAVING {$column} {$operator} :having_{$column}";
        $this->params["having_{$column}"] = $value;
        return $this;
    }
    
    public function max($column) {
        $sql = "SELECT MAX({$column}) as max_value FROM {$this->table}";
        if (!empty($this->whereClause)) $sql .= " " . $this->whereClause;
        $result = Database::view($sql, $this->params);
        return $result && isset($result[0]['max_value']) ? (int) $result[0]['max_value'] : 0;
    }

    public function min($column) {
        $sql = "SELECT MIN({$column}) as min_value FROM {$this->table}";
        if (!empty($this->whereClause)) $sql .= " " . $this->whereClause;
        $result = Database::view($sql, $this->params);
        return $result && isset($result[0]['min_value']) ? (int) $result[0]['min_value'] : 0;
    }

}
