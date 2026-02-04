<?php
if (function_exists('opcache_reset')) opcache_reset();
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestRunner;
use Nemesis\Core\Config;

// Boot
Config::load(__DIR__ . '/../');
$appConfig = require __DIR__ . '/../config/config.php';
\Nemesis\Core\Database::connect($appConfig['database']);

echo "--- Verifying Modern Features ---\n";

// Manual requires because composer dump-autoload is not available
require_once __DIR__ . '/../src/Broadcasting/Broadcaster.php';
require_once __DIR__ . '/../src/Tenancy/TenantManager.php';
require_once __DIR__ . '/../src/Tenancy/TenantScope.php';
require_once __DIR__ . '/../src/Payment/PaymentGateway.php';
require_once __DIR__ . '/../src/Activity/Activity.php';
require_once __DIR__ . '/../src/Activity/RecordsActivity.php';
require_once __DIR__ . '/ModernFeaturesTest.php';

// Manual run of ModernFeaturesTest
$test = new \Tests\Feature\ModernFeaturesTest();

try {
    echo "Running test_broadcasting... ";
    $test->setUp();
    $test->test_broadcasting();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_multitenancy... ";
    $test->setUp();
    $test->test_multitenancy();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_payment_gateway... ";
    $test->setUp();
    $test->test_payment_gateway();
    $test->tearDown();
    echo "PASS\n";

    echo "Running test_activity_logging... ";
    $test->setUp();
    $test->test_activity_logging();
    $test->tearDown();
    echo "PASS\n";
    
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

echo "\nAll Modern Features Verified!\n";
