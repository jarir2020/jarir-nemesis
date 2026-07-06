<?php

use App\Controllers\BlogController;

$router->add('GET', '/blog', [BlogController::class, 'index']);
$router->add('GET', '/blog/{slug}', [BlogController::class, 'show']);

