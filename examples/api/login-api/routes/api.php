<?php

use App\Controllers\LoginApiController;

$router->add('POST', '/api/login', [LoginApiController::class, 'login']);

