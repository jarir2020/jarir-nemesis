<?php
namespace Nemesis\Core;

use PDO;
use PDOException;

class Database {
    protected static $pdo = null;
    protected static $config = null;
    protected static $queryLog = [];
    protected static $logging = false;

    public static function enableQueryLog() {
        self::$logging = true;
    }

    public static function getQueryLog() {
        return self::$queryLog;
    }

    protected static function logQuery($sql, $params = [], $time = 0) {
        if (self::$logging) {
            self::$queryLog[] = [
                'query' => $sql,
                'bindings' => $params,
                'time' => $time
            ];
        }
    }

// Connect method (only needs to be called once with configuration)
public static function connect($config = null) {
    // If the connection already exists, return it
    if (self::$pdo !== null) {
        return self::$pdo;
    }

    // If no config is provided on subsequent calls, throw an error
    if ($config === null && self::$config === null) {
        throw new \Exception("Database configuration is required.");
    }

    // If the config is provided for the first time, store it
    if ($config !== null) {
        self::$config = $config;
    }

    // Use the stored configuration for the connection
    try {
        $dsn = "mysql:host=" . self::$config['host'] . ";port=" . (self::$config['port'] ?? 3306) . ";dbname=" . self::$config['dbname'];
        self::$pdo = new PDO($dsn, self::$config['username'], self::$config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // Handle database connection errors gracefully
        if (getenv('APP_DEBUG') === 'true') {
            echo "Database connection failed: " . $e->getMessage();
        } else {
            error_log("Database connection failed: " . $e->getMessage());
            die("Internal Server Error: Database connection could not be established.");
        }
        self::$pdo = null;
    }

    return self::$pdo;
}


    public static function view($sql, $params = []) {
        $pdo = self::connect(); 

        if (self::$pdo === null) {
            throw new \Exception("No database connection.");
        }
    
        // Prepare the statement
        $start = microtime(true);
        $stmt = self::$pdo->prepare($sql); // Use self::$pdo here
    
        // Execute the query with parameters
        $stmt->execute($params);
        self::logQuery($sql, $params, microtime(true) - $start);
    
        // Return the fetched results
        return $stmt->fetchAll(PDO::FETCH_ASSOC);  // Fetch as an associative array
    }

    public static function create($sql, $params = []) {
        $pdo = self::connect();

        if (self::$pdo === null) {
            throw new \Exception("No database connection.");
        }
    
        // Prepare the statement
        $stmt = self::$pdo->prepare($sql);
    
        // Execute the query with parameters
        $stmt->execute($params);
    
        // Return the number of affected rows (should be 1 for a successful insert)
        return $stmt->rowCount();  // Return number of rows affected
    }

    public static function update($sql, $params = []) {
        $pdo = self::connect();

        if (self::$pdo === null) {
            throw new \Exception("No database connection.");
        }
    
        // Prepare the statement
        $stmt = self::$pdo->prepare($sql);
    
        // Execute the query with parameters
        $stmt->execute($params);
    
        // Return the number of affected rows (should be 1 for a successful update)
        return $stmt->rowCount();  // Return number of rows affected
    }

    public static function delete($sql, $params = []) {
        $pdo = self::connect();
        
        if (self::$pdo === null) {
            throw new \Exception("No database connection.");
        }
    
        // Prepare the statement
        $stmt = self::$pdo->prepare($sql);
    
        // Execute the query with parameters
        $stmt->execute($params);
    
        // Return the number of affected rows (should be 1 for a successful delete)
        return self::$pdo->lastInsertId();
    }

    // Transaction support
    public static function beginTransaction() {
        $pdo = self::connect();
        if (!$pdo) {
            throw new \Exception("No database connection.");
        }
        return $pdo->beginTransaction();
    }

    public static function commit() {
        $pdo = self::connect();
        if (!$pdo) {
            throw new \Exception("No database connection.");
        }
        return $pdo->commit();
    }

    public static function rollback() {
        $pdo = self::connect();
        if (!$pdo) {
            throw new \Exception("No database connection.");
        }
        return $pdo->rollBack();
    }

    public static function transaction(callable $callback) {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Exception $e) {
            self::rollback();
            throw $e;
        }
    }

    public static function table($table) {
        return Fluent::table($table);
    }
}
