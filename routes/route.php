<?php
use Nemesis\Router\Router;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use App\Controllers\FrontendController;
use Nemesis\Helpers\Helpers;
use App\Controllers\EmailController;
use App\Controllers\ApplicationsController;
use App\Controllers\TestController;

// Instantiate the router
$router = isset($container) ? $container->make(\Nemesis\Router\Router::class) : new Router();
Router::setInstance($router);

// Load Module Routes
foreach (glob(base_path('app/Modules/*/routes.php')) as $routeFile) {
    require $routeFile;
}

// Load Plugin Routes
foreach (\Nemesis\Core\Plugin::getRoutes() as $pluginName => $routeConfig) {
    if (isset($routeConfig['callback'])) {
        call_user_func($routeConfig['callback']);
    }
}

$productController = new ProductController();
$userController = new UserController();
$emailController = new EmailController();
$applicationsController = new ApplicationsController();
$frontendController = new FrontendController();

$router->frontendGroup('react', 'layouts.app', function (Router $router) use ($frontendController): void {
    $router->add('GET', '/login', [$frontendController, 'login'], ['web'])->name('login.page');
    $router->add('GET', '/profile', [$frontendController, 'profile'], ['web'])->name('profile.page');
}, ['middleware' => 'web']);

$router->frontendGroup('vue', 'layouts.app', function (Router $router) use ($frontendController): void {
    $router->add('GET', '/dashboard', [$frontendController, 'dashboard'], ['web'])->name('dashboard.page');
    $router->add('GET', '/settings', [$frontendController, 'settings'], ['web'])->name('settings.page');
}, ['middleware' => 'web']);

$router->frontendGroup('server', 'layouts.app', function (Router $router) use ($frontendController, $userController): void {
    $router->add('GET', '/admin', [$frontendController, 'admin'], ['web'])->name('admin.dashboard');
    $router->add('POST', '/logout', [$userController, 'logout'], ['web'])->name('logout');
}, ['middleware' => 'web']);

$router->frontendGroup('ghost', 'layouts.app', function (Router $router) use ($frontendController): void {
    $router->add('GET', '/ghost-preview', [$frontendController, 'preview'], ['web'])->name('ghost.preview');
}, ['middleware' => 'web']);

$router->frontendGroup('alpine', 'layouts.app', function (Router $router) use ($frontendController): void {
    $router->add('GET', '/alpine-preview', [$frontendController, 'preview'], ['web'])->name('alpine.preview');
}, ['middleware' => 'web']);

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

$router->add('POST', '/login', [$userController, 'login'], ['web'])->name('login.submit');
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
    \Nemesis\Helpers\Helpers::redirect('/docs/index.html');
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

// Health check endpoint — Phase 4 | Added: 2026-04-02
$router->get('/_health', [\Nemesis\Http\HealthCheck::class, 'handle'])->name('health');

return $router;
