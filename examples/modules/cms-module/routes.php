<?php

use App\Modules\Cms\Controllers\CmsController;

$router->add('GET', '/cms', [CmsController::class, 'index']);

