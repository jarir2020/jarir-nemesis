<?php

use Nemesis\Database\Seeder;
use Nemesis\Core\Database;

class UserSeeder extends Seeder {
    public function run() {
        Database::connect()->exec("INSERT INTO users (username, email, password) VALUES ('jarir_test', 'test@nemesis.com', 'password123')");
        Database::connect()->exec("INSERT INTO users (username, email, password) VALUES ('nemesis_dev', 'dev@nemesis.com', 'secret')");
    }
}
