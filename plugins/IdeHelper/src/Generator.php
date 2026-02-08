<?php
namespace Nemesis\Plugins\IdeHelper;

use Nemesis\Core\Database;

class Generator {
    public function generate() {
        $content = "<?php\n\n";
        $content .= "/**\n * A helper file for your IDE to provide autocomplete information.\n * @author Jarir Ahmed\n */\n\n";

        // Scan Models
        $models = glob(base_path('app/Models/*.php'));
        foreach ($models as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');
            if (class_exists($className)) {
                $content .= $this->generateModelDocs($className);
            }
        }

        file_put_contents(base_path('_ide_helper.php'), $content);
        return "IDE Helper generated successfully!";
    }

    protected function generateModelDocs($className) {
        $reflection = new \ReflectionClass($className);
        $model = new $className();
        if (!$reflection->isInstantiable()) return "";

        $model = new $className();
        $table = null;

        if (method_exists($model, 'getTable')) {
            $table = $model->getTable();
        } elseif ($reflection->hasProperty('table')) {
            $prop = $reflection->getProperty('table');
            $prop->setAccessible(true);
            $table = $prop->getValue($model);
        }

        if (!$table) {
            $table = strtolower(basename(str_replace('\\', '/', $className))) . 's';
        }

        // Get columns
        try {
            $columns = Database::view("DESCRIBE $table");
        } catch (\Exception $e) {
            return ""; // Table might not exist
        }

        $docs = "namespace App\Models {\n";
        $docs .= "    /**\n";
        
        foreach ($columns as $column) {
            $type = $this->mapType($column['Type']);
            $name = $column['Field'];
            $docs .= "     * @property $type $$name\n";
        }

        $docs .= "     * @method static \Nemesis\Database\Builder query()\n";
        $docs .= "     * @method static $className find(mixed \$id)\n";
        $docs .= "     * @method static $className create(array \$attributes)\n";
        
        $docs .= "     */\n";
        $docs .= "    class " . basename(str_replace('\\', '/', $className)) . " extends \Nemesis\Core\Model {}\n";
        $docs .= "}\n\n";

        return $docs;
    }

    protected function mapType($dbType) {
        if (strpos($dbType, 'int') !== false) return 'int';
        if (strpos($dbType, 'float') !== false) return 'float';
        if (strpos($dbType, 'double') !== false) return 'float';
        if (strpos($dbType, 'decimal') !== false) return 'float';
        return 'string';
    }
}
