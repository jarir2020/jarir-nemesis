<?php

use App\Modules\Shop\Controllers\ShopController;

$router->add('GET', '/shop', [ShopController::class, 'index']);

