<?php
declare(strict_types=1);

namespace Nemesis\Database;

use Nemesis\Core\Database;
use PDO;

class DatabaseDumper {
    protected $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function dump($filename) {
        $tables = $this->getTables();
        $sql = "-- Nemesis Database Dump\n";
        $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $sql .= $this->dumpTableSchema($table);
            $sql .= $this->dumpTableData($table);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return file_put_contents($filename, $sql);
    }

    protected function getTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function dumpTableSchema($table) {
        $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $createTableSql = $row['Create Table'];

        $sql = "-- Table structure for table `{$table}`\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createTableSql . ";\n\n";

        return $sql;
    }

    protected function dumpTableData($table) {
        $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return "";
        }

        $sql = "-- Dumping data for table `{$table}`\n";
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $escapedColumns = array_map(fn($col) => "`$col`", $columns);
            $values = array_values($row);
            $escapedValues = array_map(function($val) {
                if ($val === null) return "NULL";
                return $this->pdo->quote($val);
            }, $values);

            $sql .= "INSERT INTO `{$table}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
        }
        $sql .= "\n";

        return $sql;
    }
}
