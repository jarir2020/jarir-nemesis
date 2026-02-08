<?php

use Nemesis\Database\Migration;
use Nemesis\Core\Database;

class CreateAuditsTable extends Migration {
    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            event VARCHAR(50),
            auditable_type VARCHAR(100),
            auditable_id INT,
            old_values TEXT,
            new_values TEXT,
            url VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            tags VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=INNODB;";

        Database::connect()->exec($sql);
    }

    public function down() {
        Database::connect()->exec("DROP TABLE IF EXISTS audits");
    }
}
