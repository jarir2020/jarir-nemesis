<?php
require "../vendor/autoload.php";

set_exception_handler(['Nemesis\Core\ErrorHandler', 'handleException']);
set_error_handler(['Nemesis\Core\ErrorHandler', 'handleError']);
use Nemesis\Core\Database;

$config = require '../config/config.php';

Database::connect($config['database']);

// Load routes from external file
$router = require "../routes/route.php";

// Dispatch the request
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
