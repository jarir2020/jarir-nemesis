<?php
require_once __DIR__ . '/vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use App\Http\Kernel;

Config::load(__DIR__);
ini_set('display_errors', 1);
error_reporting(E_ALL);

\Nemesis\Core\Cache::clear();
echo "Cache cleared.\n";

$container = \Nemesis\Core\Container::getInstance() ?: \Nemesis\Core\Container::setInstance(new \Nemesis\Core\Container());
$container->singleton(Request::class);

file_put_contents('verify_throttle_hit.php', "<?php
require_once __DIR__ . '/vendor/autoload.php';
use Nemesis\Core\Config;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use App\Http\Kernel;

Config::load(__DIR__);
\Nemesis\Core\Container::getInstance() ?: \Nemesis\Core\Container::setInstance(new \Nemesis\Core\Container());
\Nemesis\Core\Container::getInstance()->singleton(Request::class);

\$_SERVER['REQUEST_URI'] = '/throttle-test';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$router = require __DIR__ . '/routes/route.php';
\$request = \Nemesis\Core\Container::getInstance()->make(Request::class);
\$kernel = new Kernel();

(new Pipeline())
    ->send(\$request)
    ->through(\$kernel->getMiddleware())
    ->then(function(\$request) use (\$router) {
        return \$router->dispatch('/throttle-test', 'GET');
    });
");

for ($i = 1; $i <= 3; $i++) {
    echo "Request $i: ";
    passthru('php verify_throttle_hit.php');
    echo "\n";
}

unlink('verify_throttle_hit.php');
echo "\n--- Rate Limiting Test Complete ---\n";
