<?php

require_once __DIR__ . '/../vendor/autoload.php';
\Nemesis\Core\Config::load(__DIR__ . '/../');

// Bootstrap Plugins
$pluginManager = \Nemesis\Core\PluginManager::getInstance();
$pluginManager->discover();

use Nemesis\Core\Model;
use Nemesis\Core\Database;
use Nemesis\Plugins\Audit\Traits\AuditTrait;
use Nemesis\Plugins\Audit\Models\Audit;

// Connect to Database
$config = \Nemesis\Core\Config::get('database');
if (!$config) {
    $configData = require __DIR__ . '/../config/config.php';
    $config = $configData['database'];
}
Database::connect($config);

// 1. Create a Test Model Table
Database::connect()->exec("CREATE TABLE IF NOT EXISTS test_auditable_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
)");

// 2. Define Test Model
class TestAuditableModel extends Model {
    use AuditTrait;
    protected $table = 'test_auditable_models';
    protected $fillable = ['name'];
}

echo "Testing Audit Plugin...\n";

// Emulate Session for User ID
$_SESSION['user_id'] = 1;

try {
    // 3. Test Create
    echo "Creating model... ";
    $model = new TestAuditableModel();
    $model->name = 'Original Name';
    $model->save();
    $id = $model->id;
    echo "Done (ID: $id)\n";

    // Verify Audit
    $audit = Audit::where('auditable_id', '=', $id)->where('event', '=', 'created')->first();
    if ($audit && $audit['auditable_type'] === TestAuditableModel::class) {
        echo "  [PASS] Created audit log found.\n";
    } else {
        echo "  [FAIL] Created audit log NOT found.\n";
    }

    // 4. Test Update
    echo "Updating model... ";
    $model = TestAuditableModel::find($id);
    $model->name = 'Updated Name';
    $model->save();
    echo "Done\n";

    // Verify Audit
    $audit = Audit::where('auditable_id', '=', $id)->where('event', '=', 'updated')->first();
    if ($audit) {
        echo "  [PASS] Updated audit log found.\n";
        $changes = json_decode($audit['new_values'], true);
        if (isset($changes['name']) && $changes['name'] === 'Updated Name') {
            echo "  [PASS] Changes recorded correctly.\n";
        } else {
            echo "  [FAIL] Changes NOT recorded correctly.\n";
        }
    } else {
        echo "  [FAIL] Updated audit log NOT found.\n";
    }

    // 5. Test Delete
    echo "Deleting model... ";
    $model->delete();
    echo "Done\n";

    // Verify Audit
    $audit = Audit::where('auditable_id', '=', $id)->where('event', '=', 'deleted')->first();
    if ($audit) {
        echo "  [PASS] Deleted audit log found.\n";
    } else {
        echo "  [FAIL] Deleted audit log NOT found.\n";
    }

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

// Cleanup
Database::connect()->exec("DROP TABLE test_auditable_models");
// Optionally truncate audits? No, keep history.
