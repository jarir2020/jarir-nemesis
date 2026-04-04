<?php
declare(strict_types=1);

namespace Nemesis\Database;

use Nemesis\Core\Database;
use Nemesis\Core\Config;
use PDO;
use PDOException;

class DatabaseRestorer {
    protected $pdo;

    public function restore($filename) {
        if (!file_exists($filename)) {
            throw new \Exception("SQL file not found: {$filename}");
        }

        $this->ensureDatabaseExists();

        $sql = file_get_contents($filename);

        try {
            $pdo = Database::connect();
            // We use exec for the whole file. 
            // Note: This works well for dumps. For very large files, it might need line-by-line execution.
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Database restoration failed: " . $e->getMessage());
        }
    }

    protected function ensureDatabaseExists() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $port = getenv('DB_PORT') ?: 3306;
        $dbname = getenv('DB_NAME') ?: 'nemesis';

        try {
            // Try regular connection first
            $pdo = Database::connect();
            if (!$pdo) {
                throw new PDOException("Connection failed");
            }
        } catch (PDOException $e) {
            // If it fails, try connecting without dbname to create it
            $dsn = "mysql:host={$host};port={$port}";
            $tmpPdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}`");
            echo "Database `{$dbname}` created or already exists.\n";
            
            // Re-initialize the framework's database connection
            // We need to pass the config again if it was never initialized
            Database::connect([
                'host' => $host,
                'username' => $user,
                'password' => $pass,
                'dbname' => $dbname,
                'port' => $port
            ]);
        }
    }
}
