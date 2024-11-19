<?php
use Nemesis\Router\Router;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use Nemesis\Helpers\Helpers;

// Instantiate the router
$router = new Router();

$productController = new ProductController(); //Working

$userController = new UserController();

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

// Define routes
$router->add('GET', '/', function () {
    echo "Welcome to Nemesis 1.0!"; //Working
});

$router->add('GET', '/about', function () {
    echo "This is the About page."; //Working
});

return $router;
