<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\Pipeline;
use Nemesis\Http\Request;

echo "--- Middleware Pipeline Test ---\n";

$request = new Request();
$pipeline = new Pipeline();

$middleware = [
    function($request, $next) {
        $request->test = 'A';
        return $next($request);
    },
    function($request, $next) {
        $request->test .= 'B';
        return $next($request);
    }
];

$result = $pipeline->send($request)
    ->through($middleware)
    ->then(function($request) {
        return $request->test . 'C';
    });

echo "Testing Pipeline Order: ";
echo ($result === 'ABC' ? "PASS" : "FAIL ($result)") . "\n";

echo "\n--- Middleware Pipeline Test Complete ---\n";
