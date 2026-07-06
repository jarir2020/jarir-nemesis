<?php

use App\Controllers\ContentApiController;

$router->add('GET', '/api/articles', [ContentApiController::class, 'index']);
$router->add('POST', '/api/articles', [ContentApiController::class, 'store']);

