<?php
require_once __DIR__ . '/vendor/autoload.php';
\Nemesis\Core\Config::load(__DIR__);
$config = require __DIR__ . '/config/config.php';

echo "Database Config:\n";
print_r($config['database']);

echo "\nAttempting connection...\n";
try {
    $dsn = "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['dbname']}";
    $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
    echo "✓ Success!\n";
} catch (PDOException $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
