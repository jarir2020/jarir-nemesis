<?php

use Nemesis\Database\Migration;
use Nemesis\Core\Database;

class create_jobs_table extends Migration {
    public function up() {
        Database::connect()->exec("CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            reserved_at INT UNSIGNED NULL,
            available_at INT UNSIGNED NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            INDEX (reserved_at),
            INDEX (available_at)
        ) ENGINE=INNODB;");
    }

    public function down() {
        Database::connect()->exec("DROP TABLE IF EXISTS jobs;");
    }
}
