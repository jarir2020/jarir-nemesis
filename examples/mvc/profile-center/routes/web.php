<?php

use App\Controllers\ProfileCenterController;

$router->add('GET', '/profile', [ProfileCenterController::class, 'index']);

