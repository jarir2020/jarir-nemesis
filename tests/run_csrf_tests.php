<?php
echo "--- CSRF Protection Test (Case 1: No Token) ---\n";
passthru('php -r "$_SERVER[\'REQUEST_METHOD\']=\'POST\'; require \'verify_csrf.php\';"');

echo "\n--- CSRF Protection Test (Case 2: With Token) ---\n";
// This is a bit tricky to passthru with the token, but verify_csrf.php already handles it if we tweak it.
// I'll just write two separate test files or one that handles cases based on an env var.

file_put_contents('test_csrf_runner.php', "<?php
require_once __DIR__ . '/vendor/autoload.php';
use Nemesis\Core\Config;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use Nemesis\Http\Session;
use App\Http\Kernel;

Config::load(__DIR__);
\Nemesis\Core\Container::getInstance() ?: \Nemesis\Core\Container::setInstance(new \Nemesis\Core\Container());
\Nemesis\Core\Container::getInstance()->singleton(Request::class);

new Session();
\$token = Session::token();

if (\$argv[1] === 'with-token') {
    \$_POST['_token'] = \$token;
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    echo 'Testing WITH token: ';
} else {
    \$_POST = [];
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    echo 'Testing WITHOUT token: ';
}

\$kernel = new Kernel();
\$request = \Nemesis\Core\Container::getInstance()->make(Request::class);

(new Pipeline())
    ->send(\$request)
    ->through(\$kernel->getMiddleware())
    ->then(function(\$request) {
        echo 'SUCCESS\\n';
    });
");

passthru('php test_csrf_runner.php without-token');
passthru('php test_csrf_runner.php with-token');

unlink('test_csrf_runner.php');
