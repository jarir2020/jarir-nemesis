<?php

use App\Controllers\CmsApiController;

$router->add('GET', '/api/pages', [CmsApiController::class, 'pages']);

