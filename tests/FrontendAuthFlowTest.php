<?php
declare(strict_types=1);

use App\Controllers\UserController;
use Nemesis\Router\Router;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Testing\TestCase;

class FrontendAuthFlowLoginController extends UserController
{
    protected function findUserByEmail(string $email): ?array
    {
        return [
            'id' => 7,
            'email' => $email,
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
        ];
    }

    protected function persistAuthToken(int|string $userId, string $authToken): mixed
    {
        return true;
    }

    protected function generateAuthToken(): string
    {
        return 'stub-auth-token';
    }
}

class FrontendAuthFlowTest extends TestCase
{
    public function setUp(): void
    {
        $router = new Router();
        $router->get('/dashboard', fn() => null)->name('dashboard.page');
        $router->post('/login', fn() => null)->name('login.submit');
        Router::setInstance($router);
    }

    public function tearDown(): void
    {
        Router::setInstance(new Router());
    }

    public function testHtmlLoginRedirectsToDashboardPage(): void
    {
        $controller = new FrontendAuthFlowLoginController();
        $request = new class([
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ], 'text/html') extends Request {
            protected array $data;
            protected string $accept;

            public function __construct(array $data, string $accept)
            {
                $this->data = $data;
                $this->accept = $accept;
            }

            public function all(): array
            {
                return $this->data;
            }

            public function header(string $key, mixed $default = null): mixed
            {
                return $key === 'Accept' ? $this->accept : $default;
            }
        };

        $response = $controller->login($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect());
        $this->assertSame('/dashboard', $response->getRedirectUrl());
    }

    public function testJsonLoginReturnsRedirectTarget(): void
    {
        $controller = new FrontendAuthFlowLoginController();
        $request = new class([
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ], 'application/json') extends Request {
            protected array $data;
            protected string $accept;

            public function __construct(array $data, string $accept)
            {
                $this->data = $data;
                $this->accept = $accept;
            }

            public function all(): array
            {
                return $this->data;
            }

            public function header(string $key, mixed $default = null): mixed
            {
                return $key === 'Accept' ? $this->accept : $default;
            }
        };

        $response = $controller->login($request);
        $body = $response->getContent();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('stub-auth-token', $body);
        $this->assertStringContainsString('/dashboard', $body);
        $this->assertStringContainsString('redirect_to', $body);
    }
}
