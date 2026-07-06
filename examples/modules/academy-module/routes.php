<?php

use App\Modules\Academy\Controllers\AcademyController;

$router->add('GET', '/academy', [AcademyController::class, 'index']);

