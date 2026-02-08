<?php

// Routes for module: Blog

$router->add('GET', '/blog', [\App\Modules\Blog\Controllers\BlogController::class, 'index']);

\Nemesis\Core\View::addNamespace('blog', base_path('app/Modules/Blog/Views'));
