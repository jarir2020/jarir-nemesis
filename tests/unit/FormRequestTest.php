<?php
declare(strict_types=1);

use Nemesis\Core\Container;
use Nemesis\Core\ValidationException;
use Nemesis\Exceptions\AuthorizationException;
use Nemesis\Http\FormRequest;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase1StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => 'required|string',
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Email is not valid.',
        ];
    }
}

class Phase1DeniedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }
}

class Phase1FormRequestController
{
    public function store(Phase1StoreUserRequest $request): Response
    {
        return Response::json([
            'validated' => $request->validated(),
            'class' => get_class($request),
        ]);
    }
}

class FormRequestTest extends TestCase
{
    private ?Container $previousContainer = null;

    public function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/form-request';
        $_SERVER['HTTP_HOST'] = 'example.test';
    }

    public function tearDown(): void
    {
        if ($this->previousContainer instanceof Container) {
            Container::setInstance($this->previousContainer);
        }
        $_GET = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST']);
    }

    public function testValidatedReturnsOnlyDeclaredFieldsAndSupportsFieldAccess(): void
    {
        $_POST = [
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'is_admin' => true,
        ];

        $request = new Phase1StoreUserRequest();

        $this->assertSame([
            'name' => 'Alice',
            'email' => 'alice@example.test',
        ], $request->validated());
        $this->assertSame('alice@example.test', $request->validated('email'));
        $this->assertSame('fallback', $request->validated('missing', 'fallback'));
        $this->assertArrayNotHasKey('is_admin', $request->validated());
    }

    public function testCustomMessageIsReturnedThroughValidationException(): void
    {
        $_POST = [
            'name' => 'Alice',
            'email' => 'not-an-email',
        ];

        $request = new Phase1StoreUserRequest();
        $this->expectException(ValidationException::class);

        try {
            $request->validated();
        } catch (ValidationException $exception) {
            $this->assertSame(['email' => ['Email is not valid.']], $exception->getErrors());
            throw $exception;
        }
    }

    public function testAuthorizationFailureRaisesForbiddenAuthorizationException(): void
    {
        $this->expectException(AuthorizationException::class);

        (new Phase1DeniedRequest())->validated();
    }

    public function testRouterHydratesTypedFormRequestAfterMiddlewareStateChanges(): void
    {
        $container = new Container();
        Container::setInstance($container);
        $container->singleton(Request::class);

        $_POST = [
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'is_admin' => true,
        ];

        $router = new Router($container);
        $router->post('/form-request', [Phase1FormRequestController::class, 'store']);
        $router->globalMiddleware(function (Request $request, callable $next): mixed {
            return $next($request->withAttribute('middleware.user', 42));
        });

        $response = $router->dispatch('/form-request', 'POST');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Phase1StoreUserRequest', $payload['class']);
        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.test'], $payload['validated']);
    }

    public function testRequestStateIsCopiedWhenUpgradingToFormRequest(): void
    {
        $request = (new Request())
            ->withAttribute('middleware.user', 42);
        $request->setMeta('route.name', 'users.store');

        $formRequest = (new Phase1StoreUserRequest())->initializeFrom($request);

        $this->assertSame(42, $formRequest->getAttribute('middleware.user'));
        $this->assertSame('users.store', $formRequest->getMeta('route.name'));
    }
}
