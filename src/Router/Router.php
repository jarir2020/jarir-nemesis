<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 4 — Routing Enhancements | Updated: 2026-04-02

namespace Nemesis\Router;

use Nemesis\Core\Container;

class Router
{
    // -------------------------------------------------------------------------
    // Static singleton — used by the route() global helper
    // -------------------------------------------------------------------------

    protected static ?self $staticInstance = null;

    public static function getInstance(): static
    {
        if (self::$staticInstance === null) {
            self::$staticInstance = new static;
        }
        return self::$staticInstance;
    }

    public static function setInstance(self $instance): void
    {
        self::$staticInstance = $instance;
    }

    // -------------------------------------------------------------------------
    // Core state
    // -------------------------------------------------------------------------

    protected array $routes          = [];
    protected array $globalMiddlewares = [];
    protected array $binders         = [];   // Route model binders: param → callable
    protected array $groupStack      = [];
    protected ?Container $container  = null;

    protected static string $cachedPath = __DIR__ . '/../../storage/framework/routes.php';

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
        $this->loadCachedRoutes();
    }

    // -------------------------------------------------------------------------
    // Route cache
    // -------------------------------------------------------------------------

    protected function loadCachedRoutes(): void
    {
        if (file_exists(self::$cachedPath)) {
            $this->routes = require self::$cachedPath;
        }
    }

    public function cache(): void
    {
        foreach ($this->routes as $route) {
            if ($route['action'] instanceof \Closure) {
                throw new \Exception("Cannot cache routes with Closures. Use a Controller class for: " . $route['uri']);
            }
            foreach ($route['middleware'] as $m) {
                if ($m instanceof \Closure) {
                    throw new \Exception("Cannot cache Closure middleware for route: " . $route['uri']);
                }
            }
        }

        if (!is_dir(dirname(self::$cachedPath))) {
            mkdir(dirname(self::$cachedPath), 0755, true);
        }

        file_put_contents(self::$cachedPath, "<?php\n\nreturn " . var_export($this->routes, true) . ";\n");
    }

    public static function clear(): void
    {
        if (file_exists(self::$cachedPath)) {
            unlink(self::$cachedPath);
        }
    }

    // -------------------------------------------------------------------------
    // Route model binding — Phase 4
    // Registers a resolver: bind('user', fn($id) => User::findOrFail($id))
    // -------------------------------------------------------------------------

    public function bind(string $param, callable $resolver): void
    {
        $this->binders[$param] = $resolver;
    }

    // -------------------------------------------------------------------------
    // Named route URL generation — Phase 4 (powers the route() global helper)
    // route('product.show', ['id' => 5]) → '/product/5'
    // -------------------------------------------------------------------------

    public function generate(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if (($route['name'] ?? null) === $name) {
                $uri = $route['uri'];

                // Substitute named parameters
                foreach ($params as $key => $value) {
                    $uri = str_replace('{' . $key . '}', (string) $value, $uri);
                    $uri = str_replace('{' . $key . '?}', (string) $value, $uri);
                }

                // Remove leftover optional segments
                $uri = preg_replace('/\/?\{[^}]+\?\}/', '', $uri);

                return $uri;
            }
        }

        throw new \InvalidArgumentException("No route named [{$name}] found.");
    }

    // -------------------------------------------------------------------------
    // Attribute routing — Phase 4
    // Scans a controller class for #[Route(...)] attributes and registers them.
    // Usage: $router->scanAttributes(ProductController::class);
    // -------------------------------------------------------------------------

    public function scanAttributes(string $controllerClass): void
    {
        $ref = new \ReflectionClass($controllerClass);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(\Nemesis\Attributes\Route::class);

            foreach ($attrs as $attr) {
                /** @var \Nemesis\Attributes\Route $routeAttr */
                $routeAttr = $attr->newInstance();

                $this->add(
                    strtoupper($routeAttr->method),
                    $routeAttr->uri,
                    [$controllerClass, $method->getName()],
                    $routeAttr->middleware
                );

                if ($routeAttr->name !== null) {
                    $this->name($routeAttr->name);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Global middleware
    // -------------------------------------------------------------------------

    public function globalMiddleware(mixed $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    // -------------------------------------------------------------------------
    // Grouping (supports prefix, middleware, domain)
    // -------------------------------------------------------------------------

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function add(string $method, string $uri, mixed $action, array $middleware = []): static
    {
        $prefix          = '';
        $groupMiddleware = [];
        $domain          = null;

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
            }
            if (isset($group['domain'])) {
                $domain = $group['domain'];
            }
        }

        $uri        = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $middleware = array_merge($groupMiddleware, $middleware);

        $this->routes[] = [
            'method'      => strtoupper($method),
            'uri'         => $uri,
            'action'      => $action,
            'middleware'  => $middleware,
            'name'        => null,
            'constraints' => [],
            'domain'      => $domain,
        ];

        return $this;
    }

    public function name(string $name): static
    {
        if (!empty($this->routes)) {
            $this->routes[count($this->routes) - 1]['name'] = $name;
        }
        return $this;
    }

    public function where(string $name, string $expression): static
    {
        if (!empty($this->routes)) {
            $this->routes[count($this->routes) - 1]['constraints'][$name] = $expression;
        }
        return $this;
    }

    public function fallback(mixed $action): void
    {
        $this->add('ANY', '{fallback}', $action)->where('fallback', '.*');
    }

    // HTTP verb shortcuts

    public function get(string $uri, mixed $action): static
    {
        return $this->add('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): static
    {
        return $this->add('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): static
    {
        return $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, mixed $action): static
    {
        return $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, mixed $action): static
    {
        return $this->add('DELETE', $uri, $action);
    }

    public function options(string $uri, mixed $action): static
    {
        return $this->add('OPTIONS', $uri, $action);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function dispatch(string $uri, string $method): mixed
    {
        $uri    = (string) strtok($uri, '?');   // strip query string
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $request = $this->container->make(\Nemesis\Http\Request::class);

        foreach ($this->routes as $route) {
            // --- Subdomain / domain check ---
            if (!empty($route['domain'])) {
                $domainPattern = '#^' . str_replace(['.', '{', '}'], ['\.', '(?P<', '>[^.]+)'], $route['domain']) . '$#';
                if (!preg_match($domainPattern, $host, $domainMatches)) {
                    continue;
                }
            }

            // --- Method match (ANY matches every method) ---
            if ($route['method'] !== 'ANY' && strtoupper($method) !== $route['method']) {
                continue;
            }

            // --- URI pattern building ---
            $uriPattern = $route['uri'];

            foreach ($route['constraints'] as $param => $regex) {
                $uriPattern = str_replace('{' . $param . '}', '(?P<' . $param . '>' . $regex . ')', $uriPattern);
            }

            // Replace remaining {param} and {param?} placeholders
            $uriPattern = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^/]*)', $uriPattern);
            $uriPattern = preg_replace('/\{(\w+)\}/',   '(?P<$1>[^/]+)', $uriPattern);

            if (!preg_match("#^{$uriPattern}$#", $uri, $matches)) {
                continue;
            }

            // --- Collect named captures ---
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // --- Apply route model binders ---
            foreach ($this->binders as $paramName => $resolver) {
                if (isset($params[$paramName])) {
                    $params[$paramName] = $resolver($params[$paramName]);
                }
            }

            // --- Run middleware pipeline ---
            $middleware = array_merge($this->globalMiddlewares, $this->resolveMiddleware($route['middleware']));

            return (new \Nemesis\Http\Pipeline())
                ->send($request)
                ->through($middleware)
                ->then(function ($request) use ($route, $params) {
                    return $this->callAction($route['action'], $request, $params);
                });
        }

        // No route matched
        http_response_code(404);
        if (file_exists(__DIR__ . '/../Errors/404.php')) {
            include __DIR__ . '/../Errors/404.php';
        } else {
            echo json_encode(['error' => 'Route not found']);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Action invocation helpers
    // -------------------------------------------------------------------------

    protected function callAction(mixed $action, mixed $request, array $params): mixed
    {
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action);
            $instance = $this->container->make($controller);
            return call_user_func_array([$instance, $method], array_merge([$request], array_values($params)));
        }

        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            $instance = is_string($controller) ? $this->container->make($controller) : $controller;
            return call_user_func_array([$instance, $method], array_merge([$request], array_values($params)));
        }

        if (is_callable($action)) {
            return call_user_func_array($action, array_merge([$request], array_values($params)));
        }

        throw new \Exception("Invalid route action.");
    }

    // -------------------------------------------------------------------------
    // Middleware resolution
    // -------------------------------------------------------------------------

    protected function resolveMiddleware(array $middleware): array
    {
        $kernel  = new \App\Http\Kernel();
        $mapped  = $kernel->getRouteMiddleware();
        $groups  = $kernel->getMiddlewareGroups();
        $resolved = [];

        foreach ($middleware as $m) {
            if (is_string($m)) {
                if (isset($groups[$m])) {
                    $resolved = array_merge($resolved, $this->resolveMiddleware($groups[$m]));
                    continue;
                }

                $parts = explode(':', $m, 2);
                $name  = $parts[0];
                $args  = isset($parts[1]) ? explode(',', $parts[1]) : [];

                if (isset($mapped[$name])) {
                    $class     = $mapped[$name];
                    $resolved[] = fn($req, $next) => (new $class())->handle($req, $next, ...$args);
                    continue;
                }
            }
            $resolved[] = $m;
        }

        return $resolved;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getBinders(): array
    {
        return $this->binders;
    }
}
