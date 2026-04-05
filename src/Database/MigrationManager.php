<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Updated: 2026-04-06 — driver-aware migrations table (SQLite + MySQL)

namespace Nemesis\Database;

use Nemesis\Core\Database;
use PDO;

class MigrationManager {
    protected $path;

    public function __construct($path) {
        $this->path = $path;
        $this->createMigrationsTable();
    }

    protected function createMigrationsTable() {
        $driver = Database::getDriverName();

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                migration  VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB";
        }

        Database::connect()->exec($sql);
    }

    public function migrate() {
        $appliedMigrations = $this->getAppliedMigrations();
        $files = scandir($this->path);
        $toApply = array_diff($files, $appliedMigrations, ['.', '..']);

        if (empty($toApply)) {
            echo "No new migrations to apply.\n";
            return;
        }

        foreach ($toApply as $file) {
            require_once $this->path . '/' . $file;
            $className = pathinfo($file, PATHINFO_FILENAME);
            $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $className);
            $className = str_replace('_', '', ucwords($className, '_'));
            
            echo "Migrating: $file (Class: $className)\n";
            $instance = new $className();
            $instance->up();
            
            $this->logMigration($file);
            echo "Migrated: $file\n";
        }
    }

    public function rollback() {
        $lastMigration = $this->getLastMigration();
        if (!$lastMigration) {
            echo "No migrations to rollback.\n";
            return;
        }

        require_once $this->path . '/' . $lastMigration;
        $className = pathinfo($lastMigration, PATHINFO_FILENAME);
        $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $className);

        echo "Rolling back: $lastMigration\n";
        $instance = new $className();
        $instance->down();

        $this->removeMigration($lastMigration);
        echo "Rolled back: $lastMigration\n";
    }

    protected function getAppliedMigrations() {
        $stmt = Database::connect()->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function logMigration($migration) {
        $stmt = Database::connect()->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
        $stmt->execute(['migration' => $migration]);
    }

    protected function getLastMigration() {
        $stmt = Database::connect()->query("SELECT migration FROM migrations ORDER BY id DESC LIMIT 1");
        return $stmt->fetchColumn();
    }

    protected function removeMigration($migration) {
        $stmt = Database::connect()->prepare("DELETE FROM migrations WHERE migration = :migration");
        $stmt->execute(['migration' => $migration]);
    }
}
