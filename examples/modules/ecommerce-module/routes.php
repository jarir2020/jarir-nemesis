<?php

use App\Modules\Ecommerce\Controllers\EcommerceController;

$router->add('GET', '/store', [EcommerceController::class, 'index']);

