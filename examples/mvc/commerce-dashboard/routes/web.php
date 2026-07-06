<?php

use App\Controllers\CommerceDashboardController;

$router->add('GET', '/commerce-dashboard', [CommerceDashboardController::class, 'index']);

