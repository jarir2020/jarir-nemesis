<?php
use Nemesis\Router\Router;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use Nemesis\Helpers\Helpers;
use App\Controllers\EmailController;
use App\Controllers\ApplicationsController;

// Instantiate the router
$router = new Router();

$productController = new ProductController(); //Working

$userController = new UserController();

$emailController = new EmailController();

$router->add('GET', '/email/generated', function () use ($emailController) {
    $emailController->getGeneratedEmails();
});

$router->add('POST', '/email/generate', function () use ($emailController) {
    $emailController->generate();
});

$router->add('POST', '/user/send-reset-otp', function () use ($userController) {
    $userController->sendResetOtp();
});

$router->add('POST', '/user/reset-password', function () use ($userController) {
    $userController->resetPassword();
});

$router->add('POST', '/email/cron-generate', function () use ($emailController) {
    $emailController->cronGenerate();
});

$router->add('POST', '/email/generate', function () use ($emailController) {
    $emailController->generate();
});

$router->add('GET', '/email/{recipient}', function ($recipient) use ($emailController) {
    $emailController->view($recipient);
});

$router->add('DELETE', '/email/{id}', function ($id) use ($emailController) {
    $emailController->delete($id);
});

$router->add('POST', '/email/receive', function () use ($emailController) {
    $emailController->receive();
});

$router->add('POST', '/product', function () use ($productController) {
    $productController->create(); //Working
});

$router->add('GET', '/product/{id}', function ($id) use ($productController) {
    $productController->view($id); //Working
}); 

$router->add('PUT', '/product/{id}', function ($id) use ($productController) {
    $productController->update($id); //Working
});

$router->add('DELETE', '/product/{id}', function ($id) use ($productController) {
    $productController->delete($id); //Working
});

// User authentication routes
$router->add('POST', '/login', function () use ($userController) {
    $userController->login();
});


$router->add('POST', '/testing', function () {
    //var_dump($_SERVER['REQUEST_URI']); // Log the URI
    echo 'Route hit'; 
});


$router->add('GET', '/test', function () {
    echo 'Test route hit'; //Working
});

$router->add('GET', '/hostname', function () {
    echo Helpers::host();
});

$router->add('POST', '/logout', function () use ($userController) {
    $userController->logout();
});

$router->add('POST', '/register', function () use ($userController) {
    $userController->register(); //Working
});


$applicationsController = new ApplicationsController();

$router->add('GET', '/application', function () use ($applicationsController) {
    $applicationsController->viewAll();
});

$router->add('POST', '/application', function () use ($applicationsController) {
    $applicationsController->create();
});

$router->add('POST', '/application/search', function () use ($applicationsController) {
    $applicationsController->search();
});


$router->add('GET', '/application/{serial}', function ($serial) use ($applicationsController) {
    $applicationsController->view($serial);
});

$router->add('PUT', '/application/{id}', function ($id) use ($applicationsController) {
    $applicationsController->update($id);
});


$router->add('DELETE', '/application/{serial}', function ($serial) use ($applicationsController) {
    $applicationsController->delete($serial);
});


// Define routes
$router->add('GET', '/', function () {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Nemesis 1.0</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            background-color: #f4f4f4;
            color: #333;
        }
        header {
            background: #0078D7;
            color: #fff;
            padding: 10px 20px;
            text-align: center;
        }
        section {
            padding: 20px;
            margin: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            margin: 0;
            padding-bottom: 10px;
            color: #0078D7;
        }
        p {
            margin: 10px 0;
        }
        ul {
            padding: 0;
            list-style: none;
        }
        ul li {
            background: #f4f4f4;
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
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
            <li><a style="text-decoration: none; color: red; href="#">News Link 1</a></li>
            <li><a style="text-decoration: none; color: red; href="#">News Link 2</a></li>
            <li><a style="text-decoration: none; color: red; href="#">News Link 3</a></li>
        </ul>
    </section>
</body>
</html>
HTML;
});

$router->add('GET', '/about', function () {
    echo "This is the About page."; //Working
});

return $router;
