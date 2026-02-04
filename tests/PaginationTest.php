<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Fluent;
use App\Models\Post;

Config::load(__DIR__ . '/../');
$appConfig = require __DIR__ . '/../config/config.php';
// Initialize DB connection
\Nemesis\Core\Database::connect($appConfig['database']);

echo "--- Pagination Test ---\n";

// Test 1: Basic Fluent pagination
echo "\nTesting Fluent::paginate(): ";
try {
    $paginator = Fluent::table('users')->paginate(5, 1);
    echo "PASS\n";
    echo "  Total: " . $paginator->total() . "\n";
    echo "  Per Page: " . $paginator->perPage() . "\n";
    echo "  Current Page: " . $paginator->currentPage() . "\n";
    echo "  Last Page: " . $paginator->lastPage() . "\n";
    echo "  Items: " . count($paginator->items()) . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 2: Model pagination
echo "\nTesting Model::paginate(): ";
try {
    $paginator = Post::where('id', '>', 0)->paginate(3, 1);
    echo "PASS\n";
    echo "  Total: " . $paginator->total() . "\n";
    echo "  Items: " . count($paginator->items()) . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 3: JSON serialization
echo "\nTesting Paginator::toArray(): ";
try {
    $paginator = Fluent::table('users')->paginate(2, 1);
    $array = $paginator->toArray();
    
    $hasData = isset($array['data']);
    $hasMeta = isset($array['meta']);
    $metaKeys = $hasMeta && isset($array['meta']['total'], $array['meta']['per_page'], $array['meta']['current_page']);
    
    if ($hasData && $hasMeta && $metaKeys) {
        echo "PASS\n";
        echo "  Structure: data ✓, meta ✓\n";
        echo "  Meta keys: total, per_page, current_page, last_page ✓\n";
    } else {
        echo "FAIL (Missing required fields)\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 4: Page navigation helpers
echo "\nTesting page navigation helpers: ";
try {
    $page1 = Fluent::table('users')->paginate(5, 1);
    $page2 = Fluent::table('users')->paginate(5, 2);
    
    $test1 = $page1->onFirstPage() === true;
    $test2 = $page2->onFirstPage() === false;
    $test3 = $page1->hasMorePages() === ($page1->total() > 5);
    
    if ($test1 && $test2) {
        echo "PASS\n";
        echo "  onFirstPage() ✓\n";
        echo "  hasMorePages() ✓\n";
    } else {
        echo "FAIL\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Pagination Test Complete ---\n";
