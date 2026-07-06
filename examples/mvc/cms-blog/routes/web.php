<?php

use App\Controllers\CmsBlogController;

$router->add('GET', '/blog', [CmsBlogController::class, 'index']);

