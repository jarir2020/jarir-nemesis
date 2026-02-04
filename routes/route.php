<?php
use Nemesis\Router\Router;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use Nemesis\Helpers\Helpers;
use App\Controllers\EmailController;
use App\Controllers\ApplicationsController;
use App\Controllers\TestController;

// Instantiate the router
$router = new Router($container ?? null);

$productController = new ProductController();
$userController = new UserController();
$emailController = new EmailController();
$applicationsController = new ApplicationsController();

$router->add('GET', '/framework-test', [TestController::class, 'index']);
$router->add('GET', '/view-test', [TestController::class, 'viewTest']);
$router->add('GET', '/cache-test', [TestController::class, 'cacheTest']);
$router->add('GET', '/di-test', [TestController::class, 'diTest']);
$router->add('GET', '/send-test-email', [TestController::class, 'sendEmail']);

$router->add('GET', '/email/generated', [$emailController, 'getGeneratedEmails']);
$router->add('POST', '/email/generate', [$emailController, 'generate']);
$router->add('POST', '/user/send-reset-otp', [$userController, 'sendResetOtp']);
$router->add('POST', '/user/reset-password', [$userController, 'resetPassword']);
$router->add('POST', '/email/cron-generate', [$emailController, 'cronGenerate']);
$router->add('GET', '/email/{recipient}', [$emailController, 'view']);
$router->add('DELETE', '/email/{id}', [$emailController, 'delete']);
$router->add('POST', '/email/receive', [$emailController, 'receive']);

$router->add('POST', '/product', [$productController, 'create']);
$router->add('GET', '/product/{id}', [$productController, 'view']);
$router->add('PUT', '/product/{id}', [$productController, 'update']);
$router->add('DELETE', '/product/{id}', [$productController, 'delete']);

$router->add('POST', '/login', [$userController, 'login']);
$router->add('POST', '/logout', [$userController, 'logout']);
$router->add('POST', '/register', [$userController, 'register']);

$router->add('GET', '/application', [$applicationsController, 'viewAll']);
$router->add('POST', '/application', [$applicationsController, 'create']);
$router->add('POST', '/application/search', [$applicationsController, 'search']);
$router->add('GET', '/application/{serial}', [$applicationsController, 'view']);
$router->add('PUT', '/application/{id}', [$applicationsController, 'update']);
$router->add('DELETE', '/application/{serial}', [$applicationsController, 'delete']);

$router->add('GET', '/test', function () {
    echo 'Test route hit';
});

$router->add('GET', '/hostname', function () {
    echo Helpers::host();
});

$router->add('GET', '/', function () {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Nemesis 1.0</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; line-height: 1.6; background-color: #f4f4f4; color: #333; }
        header { background: #0078D7; color: #fff; padding: 10px 20px; text-align: center; }
        section { padding: 20px; margin: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        h1, h2 { margin: 0; padding-bottom: 10px; color: #0078D7; }
        p { margin: 10px 0; }
        ul { padding: 0; list-style: none; }
        ul li { background: #f4f4f4; margin: 5px 0; padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <header>
        <h1 style="color:white;">Welcome to Nemesis 1.0</h1>
        <p>Your lightweight PHP framework for API development</p>
    </header>
    <section>
        <h2>About the Author</h2>
        <p>The Nemesis framework is crafted by <a style="text-decoration: none; color: red;" href="https://www.facebook.com/jarir.in.ruet.cse/">Jarir Ahmed</a>, aiming to simplify API development with modern, efficient, and lightweight solutions.</p>
    </section>
    <section>
        <h2>Documentation</h2>
        <p>Get started with Nemesis by exploring the following topics:</p>
        <ul>
            <li>Routing and Controllers</li>
            <li>Core CRUD Operations</li>
            <li>Authentication and Authorization</li>
            <li>Error Handling</li>
            <li>Database Query Handling</li>
        </ul>
    </section>
    <section>
        <h2>News</h2>
        <p>Stay updated with the latest developments in Nemesis.</p>
        <ul>
            <li><a style="text-decoration: none; color: red; href='#'>News Link 1</a></li>
            <li><a style="text-decoration: none; color: red; href='#'>News Link 2</a></li>
            <li><a style="text-decoration: none; color: red; href='#'>News Link 3</a></li>
        </ul>
    </section>
</body>
</html>
HTML;
});

$router->add('GET', '/about', function () {
    echo "This is the About page.";
});


// Resource Routes for Tag
$router->add('GET', '/tags', [\App\Controllers\TagController::class, 'index']);
$router->add('GET', '/tags/create', [\App\Controllers\TagController::class, 'create']);
$router->add('POST', '/tags', [\App\Controllers\TagController::class, 'store']);
$router->add('GET', '/tags/{id}', [\App\Controllers\TagController::class, 'show']);

// Rate limit test route
$router->add('GET', '/throttle-test', function() {
    echo "Throttled route hit";
}, ['throttle:2,1']);

return $router;
