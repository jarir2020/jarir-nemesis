<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestRunner;
use Nemesis\Core\Config;

// Boot Framework
$projectRoot = dirname(__DIR__);
Config::load($projectRoot);
$appConfig = require $projectRoot . '/config/config.php';
\Nemesis\Core\Database::connect($appConfig['database']);


// The repository also contains legacy procedural smoke scripts in tests/.
// Keep the automated command focused on the class-based unit suite so those
// scripts cannot call exit() before the runner reports its result.
$suite = 'unit';
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (str_starts_with($argument, '--suite=')) {
        $suite = substr($argument, 8);
    }
}

if ($suite !== 'unit') {
    fwrite(STDERR, "Unsupported suite '{$suite}'. Available suite: unit\n");
    exit(2);
}

$runner = new TestRunner(__DIR__ . '/unit');
$runner->run();
