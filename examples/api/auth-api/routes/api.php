<?php

use App\Controllers\AuthApiController;

$router->add('POST', '/api/login', [AuthApiController::class, 'login']);
$router->add('POST', '/api/logout', [AuthApiController::class, 'logout']);

