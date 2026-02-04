<?php
if (function_exists('opcache_reset')) opcache_reset();
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;

// Boot
Config::load(__DIR__ . '/../');
$appConfig = require __DIR__ . '/../config/config.php';
Database::connect($appConfig['database']);

echo "--- Verifying Perfection Features ---\n";

require_once __DIR__ . '/PerfectionTest.php';

// Manual run
$test = new \Tests\Feature\PerfectionTest();

try {
    echo "Running test_interface_binding... ";
    $test->setUp();
    $test->test_interface_binding();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_query_logging... ";
    $test->setUp();
    $test->test_query_logging();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_encryption... ";
    $test->setUp();
    $test->test_encryption();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_schema_ident... ";
    $test->setUp();
    $test->test_schema_ident();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_api_resources... ";
    $test->setUp();
    $test->test_api_resources();
    $test->tearDown();
    echo "PASS\n";
    
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

echo "\nAll Perfection Features Verified! (100/100)\n";
