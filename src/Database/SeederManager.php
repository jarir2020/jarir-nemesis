<?php
declare(strict_types=1);
namespace Nemesis\Database;

class SeederManager {
    protected $path;

    public function __construct($path) {
        $this->path = $path;
    }

    public function seed($name = null) {
        if ($name) {
            $this->runSeeder($name);
            return;
        }

        $files = scandir($this->path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $this->runSeeder($file);
        }
    }

    protected function runSeeder($file) {
        if (strpos($file, '.php') === false) {
            $file .= '.php';
        }

        $filePath = $this->path . '/' . $file;
        if (!file_exists($filePath)) {
            echo "Seeder file $file not found.\n";
            return;
        }

        require_once $filePath;
        $className = pathinfo($file, PATHINFO_FILENAME);
        
        echo "Seeding: $className\n";
        $instance = new $className();
        $instance->run();
        echo "Seeded: $className\n";
    }
}
