<?php
declare(strict_types=1);
namespace Nemesis\Core\Cache;

use Nemesis\Core\Database;
use PDO;

class DatabaseCacheDriver implements CacheDriver {
    protected $table = 'cache';

    public function __construct() {
        $this->ensureTableExists();
    }

    protected function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` LONGTEXT,
            `expires_at` INT
        ) ENGINE=INNODB;";
        Database::connect()->exec($sql);
    }

    public function get($key, $default = null) {
        $stmt = Database::connect()->prepare("SELECT `value`, `expires_at` FROM {$this->table} WHERE `key` = :key");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return $default;

        if (time() > $row['expires_at']) {
            $this->forget($key);
            return $default;
        }

        return unserialize($row['value']);
    }

    public function set($key, $value, $seconds = 3600) {
        $sql = "REPLACE INTO {$this->table} (`key`, `value`, `expires_at`) VALUES (:key, :value, :expires_at)";
        $stmt = Database::connect()->prepare($sql);
        return $stmt->execute([
            'key' => $key,
            'value' => serialize($value),
            'expires_at' => time() + $seconds
        ]);
    }

    public function forget($key) {
        $stmt = Database::connect()->prepare("DELETE FROM {$this->table} WHERE `key` = :key");
        $stmt->execute(['key' => $key]);
    }

    public function clear() {
        Database::connect()->exec("DELETE FROM {$this->table}");
    }
}
