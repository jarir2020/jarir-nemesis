<?php

use App\Controllers\AdminPanelController;

$router->add('GET', '/admin', [AdminPanelController::class, 'index']);

