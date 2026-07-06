<?php

use App\Controllers\BillingApiController;

$router->add('GET', '/api/billing', [BillingApiController::class, 'index']);

