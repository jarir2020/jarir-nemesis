<?php
namespace Nemesis\Core;

use PDO;
use PDOException;

class Database {
    protected static $pdo = null;
    protected static $config = null;

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
        echo "Database connection failed: " . $e->getMessage();
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
        $stmt = self::$pdo->prepare($sql); // Use self::$pdo here
    
        // Execute the query with parameters
        $stmt->execute($params);
    
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
        return $stmt->rowCount();  // Return number of rows affected
    }
}
