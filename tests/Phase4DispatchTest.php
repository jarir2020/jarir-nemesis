<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Router\RouteModelBinder;
use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase4DispatchBinderController
{
    public function show(Request $request, mixed $post): string
    {
        return $request->getMeta('route.framework', 'none') . ':' . ($post['title'] ?? 'missing');
    }
}

class Phase4DispatchTest extends TestCase
{
    public function testDispatchResolvesStaticRouteModelBinders(): void
    {
        $router = new Router();
        Router::setInstance($router);
        RouteModelBinder::flush();

        RouteModelBinder::bind('post', fn(string $id) => [
            'id' => (int) $id,
            'title' => 'Post ' . $id,
        ]);

        $router->get('/posts/{post}', [new Phase4DispatchBinderController(), 'show'])->name('posts.show');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/posts/7';
        $_SERVER['HTTP_HOST'] = 'example.test';

        $response = $router->dispatch('/posts/7', 'GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('none:Post 7', $response->getContent());
    }

    public function testRequestMethodHonorsFormSpoofingAndRoutesDispatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/posts/7';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_POST = ['_method' => 'PATCH'];
        $_GET = [];

        $request = new Request();
        $this->assertSame('PATCH', $request->method());

        $router = new Router();
        Router::setInstance($router);
        $router->patch('/posts/{post}', [new Phase4DispatchBinderController(), 'show'])->name('posts.patch');

        RouteModelBinder::flush();
        RouteModelBinder::bind('post', fn(string $id) => [
            'id' => (int) $id,
            'title' => 'Patch ' . $id,
        ]);

        $response = $router->dispatch('/posts/7', $request->method());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('none:Patch 7', $response->getContent());

        $_POST = [];
    }
}

$test = new Phase4DispatchTest();

echo "--- Phase 4 Dispatch Test ---\n";
echo "Running testDispatchResolvesStaticRouteModelBinders... ";
try {
    $test->setUp();
    $test->testDispatchResolvesStaticRouteModelBinders();
    $test->tearDown();
    echo "PASS\n";
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Running testRequestMethodHonorsFormSpoofingAndRoutesDispatch... ";
try {
    $test->setUp();
    $test->testRequestMethodHonorsFormSpoofingAndRoutesDispatch();
    $test->tearDown();
    echo "PASS\n";
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Phase 4 Dispatch Test Complete ---\n";
