<?php
require_once __DIR__ . '/vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;

echo "--- Database Test Setup ---\n\n";

// Load config
Config::load(__DIR__);
$appConfig = require __DIR__ . '/config/config.php';

// Test connection
echo "Testing database connection... ";
try {
    $pdo = Database::connect($appConfig['database']);
    echo "✓ Connected\n\n";
} catch (\Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    echo "\nPlease configure your .env file with valid database credentials.\n";
    exit(1);
}

// Load and execute schema
echo "Creating test tables... ";
try {
    $schema = file_get_contents(__DIR__ . '/database/test_schema.sql');
    $pdo->exec($schema);
    echo "✓ Tables created\n";
} catch (\Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Load and execute seed data
echo "Inserting sample data... ";
try {
    // Clear existing data first
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE post_tag");
    $pdo->exec("TRUNCATE TABLE comments");
    $pdo->exec("TRUNCATE TABLE posts");
    $pdo->exec("TRUNCATE TABLE tags");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insert new data
    $data = file_get_contents(__DIR__ . '/database/test_data.sql');
    $pdo->exec($data);
    echo "✓ Data inserted\n\n";
} catch (\Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify data
echo "Verifying data:\n";
$users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$tags = $pdo->query("SELECT COUNT(*) FROM tags")->fetchColumn();

echo "  Users: $users\n";
echo "  Posts: $posts\n";
echo "  Comments: $comments\n";
echo "  Tags: $tags\n";

echo "\n✓ Database setup complete!\n";
echo "\nYou can now run:\n";
echo "  php tests/RelationshipTest.php\n";
echo "  php tests/PaginationTest.php\n";
echo "  php tests/DatabaseEnhancementsTest.php\n";
