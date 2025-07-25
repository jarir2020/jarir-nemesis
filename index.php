<?php

// Enable CORS (MUST come before any output)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require "vendor/autoload.php";

set_exception_handler(['Nemesis\Core\ErrorHandler', 'handleException']);
set_error_handler(['Nemesis\Core\ErrorHandler', 'handleError']);
use Nemesis\Core\Database;

$config = require 'config/config.php';

Database::connect($config['database']);

// Load routes from external file
$router = require "routes/route.php";

// Normalize URI by removing base folder (if any)
$uri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

$basePath = str_replace('\\', '/', dirname($scriptName));

if ($basePath !== '/' && substr($basePath, -1) === '/') {
    $basePath = rtrim($basePath, '/');
}

if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

if ($uri === '' || $uri[0] !== '/') {
    $uri = '/' . $uri;
}

$router->dispatch($uri, $_SERVER['REQUEST_METHOD']);
