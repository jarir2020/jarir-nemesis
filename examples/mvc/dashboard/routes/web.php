<?php

use App\Controllers\DashboardController;

$router->add('GET', '/dashboard', [DashboardController::class, 'index']);

