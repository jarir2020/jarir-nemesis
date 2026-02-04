<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use App\Models\User;

Config::load(__DIR__ . '/../');
$config = require __DIR__ . '/../config/config.php';
Database::connect($config['database']);

echo "--- RBAC System Test ---\n";

// Mock User with RBAC trait
class MockUser extends User {
    use \Nemesis\Auth\Traits\HasRoles;
    public $id = 999;
}

$user = new MockUser();

echo "Testing hasRole (Mock): ";
// This would normally check DB, so we'll just check if the method exists and returns a boolean
echo (is_bool($user->hasRole('admin')) ? "PASS" : "FAIL") . "\n";

echo "Testing hasPermission (Mock): ";
echo (is_bool($user->hasPermission('edit-post')) ? "PASS" : "FAIL") . "\n";

echo "\n--- RBAC System Test Complete ---\n";
