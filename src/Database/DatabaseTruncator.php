<?php
declare(strict_types=1);

namespace Nemesis\Database;

use Nemesis\Core\Database;
use PDO;

class DatabaseTruncator {
    protected $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function truncate() {
        $tables = $this->getTables();
        
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        
        foreach ($tables as $table) {
            // Skip migrations table to avoid resetting the framework's state
            if ($table === 'migrations') continue;
            
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");
            echo "Truncated table: {$table}\n";
        }

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        
        return true;
    }

    protected function getTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
