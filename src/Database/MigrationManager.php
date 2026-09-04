<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Updated: 2026-04-06 — driver-aware migrations table (SQLite + MySQL)

namespace Nemesis\Database;

use Nemesis\Core\Database;
use PDO;

class MigrationManager {
    protected $path;

    public function __construct($path) {
        $this->path = rtrim((string) $path, '/\\');
        if (!is_dir($this->path)) {
            throw new \InvalidArgumentException("Migration directory not found: {$this->path}");
        }
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
        $files = $this->migrationFiles();
        $toApply = array_values(array_diff($files, $appliedMigrations));

        if (empty($toApply)) {
            echo "No new migrations to apply.\n";
            return;
        }

        foreach ($toApply as $file) {
            require_once $this->path . '/' . $file;
            $className = $this->resolveMigrationClass($file);
            
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
        $className = $this->resolveMigrationClass($lastMigration);

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

    /** @return list<array{migration: string, status: string}> */
    public function status(): array
    {
        $applied = $this->getAppliedMigrations();
        return array_map(
            fn(string $file): array => [
                'migration' => $file,
                'status' => in_array($file, $applied, true) ? 'Ran' : 'Pending',
            ],
            $this->migrationFiles()
        );
    }

    /** @return list<string> */
    protected function migrationFiles(): array
    {
        $files = scandir($this->path);
        if ($files === false) {
            throw new \RuntimeException("Unable to read migration directory: {$this->path}");
        }

        return array_values(array_filter($files, fn(string $file): bool =>
            str_ends_with($file, '.php') && is_file($this->path . '/' . $file)
        ));
    }

    protected function resolveMigrationClass(string $file): string
    {
        $base = pathinfo($file, PATHINFO_FILENAME);
        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $base) ?: $base;
        $pascal = str_replace('_', '', ucwords($withoutTimestamp, '_'));
        $candidates = array_unique([$base, $withoutTimestamp, $pascal]);

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Migration::class)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Migration class not found for file: {$file}");
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
