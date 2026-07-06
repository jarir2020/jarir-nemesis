<?php

use App\Controllers\UserApiController;

$router->add('GET', '/api/users', [UserApiController::class, 'index']);
$router->add('GET', '/api/users/{id}', [UserApiController::class, 'show']);

