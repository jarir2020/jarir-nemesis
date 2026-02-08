<?php
require_once __DIR__ . '/vendor/autoload.php';

use Nemesis\Testing\TestRunner;
use Nemesis\Core\Config;

// Boot Framework
Config::load(__DIR__);
$appConfig = require __DIR__ . '/config/config.php';
\Nemesis\Core\Database::connect($appConfig['database']);


// Run Tests
$runner = new TestRunner(__DIR__ . '/tests');
$runner->run();
