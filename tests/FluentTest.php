<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Core\Fluent;

Config::load(__DIR__ . '/../');
$config = require __DIR__ . '/../config/config.php';
Database::connect($config['database']);

echo "--- Fluent ORM Test ---\n";

// Use a temporary table for testing
$db = Database::connect();
$db->exec("CREATE TEMPORARY TABLE test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))");

class TestUser extends Fluent {
    public function __construct() {
        parent::__construct('test_users');
    }
}

$user = new TestUser();

// 1. Create
echo "Testing Create: ";
$id = $user->insert(['name' => 'John Doe', 'email' => 'john@example.com']);
echo ($id > 0 ? "PASS (ID: $id)" : "FAIL") . "\n";

// 2. Find
echo "Testing Find: ";
$found = $user->find($id);
echo ($found['name'] === 'John Doe' ? "PASS" : "FAIL") . "\n";

// 3. Update
echo "Testing Update: ";
$user->where('id', '=', $id)->update(['name' => 'Jane Doe']);
$updated = $user->find($id);
echo ($updated['name'] === 'Jane Doe' ? "PASS" : "FAIL") . "\n";

// 4. Delete
echo "Testing Delete: ";
$user->where('id', '=', $id)->delete();
$deleted = $user->find($id);
echo ($deleted === null ? "PASS" : "FAIL") . "\n";

echo "\n--- Fluent ORM Test Complete ---\n";
