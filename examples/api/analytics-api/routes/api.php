<?php

use App\Controllers\AnalyticsApiController;

$router->add('GET', '/api/metrics', [AnalyticsApiController::class, 'index']);

