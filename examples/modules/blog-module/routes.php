<?php

use App\Modules\Blog\Controllers\BlogController;

$router->add('GET', '/blog', [BlogController::class, 'index']);

