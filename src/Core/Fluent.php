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

    // Add where clause to the query
    public function whereGET($column, $operator, $value) {
        if (strpos($this->query, "WHERE") === false) {
            $this->query .= " WHERE {$column} {$operator} :{$column}";
        } else {
            $this->query .= " AND {$column} {$operator} :{$column}";
        }
    
        // Add the parameter to the binding array
        $this->params[":{$column}"] = $value;
    
        // Debugging: Check the SQL query and parameters
        //echo "SQL: {$this->query}\n"; // Print the generated query
        //print_r($this->params); // Print the parameters array
    
        return $this;
    }


    public function whereDELETE($column, $operator, $value) {
        if (empty($this->whereClause)) {
            $this->whereClause = "WHERE {$column} {$operator} :{$column}";
        } else {
            $this->whereClause .= " AND {$column} {$operator} :{$column}";
        }
    
        // Add the parameter for binding without `:`
        $this->params[$column] = $value;
    
        return $this; // Enable method chaining
    }    
    

    public function whereUpdate($column, $operator, $value) {
        $this->whereClause = "{$column} {$operator} :where_{$column}";
        $this->params["where_{$column}"] = $value;
        return $this; // Return the object for method chaining
    }
    
    

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
        return Database::view($this->query, $this->params);
    }

    // Retrieve the first result of the query
    public function first() {
        $result = $this->limit(1)->get();
        return $result ? $result[0] : null;
    }

    // Insert data into the table
    public function insert($data) {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_map(fn($item) => ":{$item}", array_keys($data)));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
        Database::create($sql, $data);
    }

    // Update data in the table
    public function update($data) {
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "{$column} = :{$column}";
            $this->params[$column] = $value; // Bind update values
        }
    
        // Append WHERE clause if it exists
        if (!empty($this->whereClause)) {
            $this->query = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->whereClause}";
        } else {
            throw new \Exception("WHERE clause is missing in update query.");
        }
    
        Database::update($this->query, $this->params);
    }
    

    // Delete records from the table
    public function delete() {
        if (empty($this->whereClause)) {
            throw new \Exception("WHERE clause is missing in delete query.");
        }
    
        $this->query = "DELETE FROM {$this->table} {$this->whereClause}";
    
        // Call the Database delete operation
        Database::delete($this->query, $this->params);
    }
    

    // Add an OR condition to the WHERE clause
    public function orWhere($column, $operator, $value) {
        $this->query .= " OR {$column} {$operator} :{$column}";
        $this->params[$column] = $value;
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
        $this->query .= " HAVING {$column} {$operator} :{$column}";
        $this->params[$column] = $value;
        return $this;
    }

    public function max($column) {
    $sql = "SELECT MAX({$column}) as max_value FROM {$this->table}";
    $result = Database::view($sql);
    return $result && isset($result[0]['max_value']) ? (int) $result[0]['max_value'] : 0;
}

    public function min($column) {
    $sql = "SELECT MIN({$column}) as min_value FROM {$this->table}";
    $result = Database::view($sql);
    return $result && isset($result[0]['min_value']) ? (int) $result[0]['min_value'] : 0;
}

}
