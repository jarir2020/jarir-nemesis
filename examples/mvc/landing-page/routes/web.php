<?php

use App\Controllers\LandingPageController;

$router->add('GET', '/', [LandingPageController::class, 'index']);

