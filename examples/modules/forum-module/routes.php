<?php

use App\Modules\Forum\Controllers\ForumController;

$router->add('GET', '/forum', [ForumController::class, 'index']);

