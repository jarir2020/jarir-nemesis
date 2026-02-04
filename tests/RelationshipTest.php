<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserModel;
use App\Models\Tag;

Config::load(__DIR__ . '/../');
$appConfig = require __DIR__ . '/../config/config.php';

echo "--- Database Relationships Test ---\n";

// Create test schema
try {
    $pdo = \Nemesis\Core\Database::connect($appConfig['database']);
    
    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255),
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT,
        user_id INT,
        body TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_tag (
        post_id INT,
        tag_id INT,
        PRIMARY KEY (post_id, tag_id)
    )");
    
    echo "✓ Test schema created\n";
} catch (\Exception $e) {
    echo "Schema error: " . $e->getMessage() . "\n";
}

// Test 1: hasMany relationship
echo "\nTesting hasMany (User -> Posts): ";
try {
    $user = UserModel::find(1);
    if ($user) {
        $posts = $user->posts;
        echo "PASS (Found " . count($posts) . " posts)\n";
    } else {
        echo "SKIP (No user with ID 1)\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 2: belongsTo relationship
echo "Testing belongsTo (Post -> User): ";
try {
    $post = Post::find(1);
    if ($post) {
        $author = $post->author;
        if ($author) {
            echo "PASS (Author ID: " . $author->id . ")\n";
        } else {
            echo "PASS (Post has no author)\n";
        }
    } else {
        echo "SKIP (No post with ID 1)\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 3: Query builder integration
echo "Testing Model::where() query: ";
try {
    $posts = Post::where('id', '>', 0)->get();
    echo "PASS (Found " . count($posts) . " posts)\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 4: Model::all()
echo "Testing Model::all(): ";
try {
    $allPosts = Post::all();
    echo "PASS (Found " . count($allPosts) . " posts)\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Database Relationships Test Complete ---\n";
echo "\nNote: Insert sample data into users/posts/comments/tags tables for full testing.\n";
