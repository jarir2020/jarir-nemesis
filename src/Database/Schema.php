<?php

namespace Nemesis\Database;

use Nemesis\Core\Database;

class Schema {
    public static function create($table, $callback) {
        // Existing create logic (Conceptual, or proxy to Database::create DDL)
        // For simple string SQL:
        // Database::create($sql);
    }

    public static function table($table, $callback) {
        // Support ALTER TABLE
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        foreach ($blueprint->getCommands() as $sql) {
            Database::connect()->exec($sql);
        }
    }
}

class Blueprint {
    protected $table;
    protected $commands = [];

    public function __construct($table) {
        $this->table = $table;
    }

    public function string($column, $length = 255) {
        $this->commands[] = "ALTER TABLE {$this->table} ADD COLUMN {$column} VARCHAR({$length})";
        return $this;
    }

    public function integer($column) {
        $this->commands[] = "ALTER TABLE {$this->table} ADD COLUMN {$column} INT";
        return $this;
    }

    public function dropColumn($column) {
        $this->commands[] = "ALTER TABLE {$this->table} DROP COLUMN {$column}";
        return $this;
    }

    public function getCommands() {
        return $this->commands;
    }
}
