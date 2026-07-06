<?php

use App\Controllers\EcommerceController;

$router->add('GET', '/shop', [EcommerceController::class, 'index']);

