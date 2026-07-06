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

    public function warmCache(): string
    {
        $cacheableRoutes = array_values(array_filter($this->routes, fn(array $route): bool => $this->isCacheableRoute($route)));

        if (!is_dir(dirname(self::$cachedPath))) {
            mkdir(dirname(self::$cachedPath), 0755, true);
        }

        file_put_contents(self::$cachedPath, "<?php\n\nreturn " . var_export($cacheableRoutes, true) . ";\n");
        return self::$cachedPath;
    }

    public function cache(): void
    {
        $this->warmCache();
    }

    public static function clearCache(): bool
    {
        if (file_exists(self::$cachedPath)) {
            return unlink(self::$cachedPath);
        }

        return true;
    }

    public static function clear(): void
    {
        self::clearCache();
    }

    protected function isCacheableRoute(array $route): bool
    {
        $action = $route['action'] ?? null;
        if ($action instanceof \Closure) {
            return false;
        }

        if (is_array($action)) {
            foreach ($action as $part) {
                if ($part instanceof \Closure || is_object($part)) {
                    return false;
                }
            }
        }

        foreach (($route['middleware'] ?? []) as $middleware) {
            if ($middleware instanceof \Closure) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return a normalized, export-friendly view of all registered routes.
     *
     * @param  array<string, string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function routeSummary(array $filters = []): array
    {
        $summary = [];
        $methodFilter = isset($filters['method']) ? strtoupper(trim($filters['method'])) : null;
        $frameworkFilter = isset($filters['framework']) ? strtolower(trim($filters['framework'])) : null;
        $middlewareFilter = isset($filters['middleware']) ? strtolower(trim($filters['middleware'])) : null;

        foreach ($this->routes as $index => $route) {
            $meta = (array) ($route['meta'] ?? []);
            $middleware = array_values(array_unique(array_filter((array) ($route['middleware'] ?? []), fn($item) => $item !== null && $item !== '')));
            $routeMethod = strtoupper((string) ($route['method'] ?? 'GET'));
            $routeFramework = strtolower((string) ($meta['framework'] ?? ''));

            if ($methodFilter !== null && $methodFilter !== '' && $routeMethod !== $methodFilter) {
                continue;
            }

            if ($frameworkFilter !== null && $frameworkFilter !== '' && $routeFramework !== $frameworkFilter) {
                continue;
            }

            if ($middlewareFilter !== null && $middlewareFilter !== '' && !in_array($middlewareFilter, array_map('strtolower', array_map('strval', $middleware)), true)) {
                continue;
            }

            $summary[] = [
                'index'      => $index + 1,
                'method'     => $routeMethod,
                'uri'        => $route['uri'] ?? '/',
                'name'       => $route['name'] ?? null,
                'domain'     => $route['domain'] ?? null,
                'middleware' => $middleware,
                'framework'  => $meta['framework'] ?? null,
                'layout'     => $meta['layout'] ?? null,
                'action'     => $this->describeAction($route['action'] ?? null),
                'cacheable'  => $this->isCacheableRoute($route),
            ];
        }

        return $summary;
    }

    /**
     * High-level route diagnostics for CLI / tooling.
     *
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function diagnostics(array $filters = []): array
    {
        $summary = $this->routeSummary($filters);
        $methods = [];
        $frameworks = [];
        $layouts = [];
        $middleware = [];

        foreach ($summary as $route) {
            $methods[$route['method']] = ($methods[$route['method']] ?? 0) + 1;

            if (!empty($route['framework'])) {
                $frameworks[$route['framework']] = ($frameworks[$route['framework']] ?? 0) + 1;
            }

            if (!empty($route['layout'])) {
                $layouts[$route['layout']] = ($layouts[$route['layout']] ?? 0) + 1;
            }

            foreach ((array) $route['middleware'] as $item) {
                $middleware[$item] = ($middleware[$item] ?? 0) + 1;
            }
        }

        return [
            'route_count' => count($summary),
            'named_route_count' => count(array_filter($summary, fn(array $route): bool => !empty($route['name']))),
            'cacheable_route_count' => count(array_filter($summary, fn(array $route): bool => !empty($route['cacheable']))),
            'fallback_route' => $this->findFallbackRoute() !== null,
            'methods' => $methods,
            'frameworks' => $frameworks,
            'layouts' => $layouts,
            'middleware' => $middleware,
        ];
    }

    /**
     * Export diagnostics + route summary as JSON, PHP, or YAML.
     *
     * @param  array<string, string>  $filters
     * @return string Export payload or written file path
     */
    public function exportRoutes(?string $path = null, string $format = 'json', array $filters = []): string
    {
        $payload = [
            'diagnostics' => $this->diagnostics($filters),
            'routes' => $this->routeSummary($filters),
        ];

        $format = strtolower(trim($format));
        if ($path !== null && $path !== '' && $format === 'json') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'yaml', 'yml', 'json'], true)) {
                $format = $ext === 'yml' ? 'yaml' : $ext;
            }
        }

        $content = match ($format) {
            'php' => $this->renderPhpExport($payload),
            'yaml' => $this->renderYamlExport($payload),
            default => (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        };

        if ($path === null || $path === '') {
            return $content;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content . PHP_EOL);
        return $path;
    }

    /**
     * Build a detailed route match report for 404/debug output.
     *
     * @return array<string, mixed>
     */
    public function matchDiagnostics(string $uri, string $method, ?string $host = null): array
    {
        $uri = (string) strtok($uri, '?');
        $method = strtoupper($method);
        $host = $host ?? ($_SERVER['HTTP_HOST'] ?? '');
        $checked = [];
        $matched = null;
        $matchedParams = [];

        foreach ($this->routes as $index => $route) {
            if ($this->isFallbackRoute($route)) {
                continue;
            }
            $evaluation = $this->evaluateRoute($route, $uri, $method, $host);
            $checked[] = [
                'index' => $index + 1,
                'method' => strtoupper((string) ($route['method'] ?? 'GET')),
                'uri' => $route['uri'] ?? '/',
                'name' => $route['name'] ?? null,
                'framework' => (string) (($route['meta']['framework'] ?? null) ?? ''),
                'matched' => $evaluation['matched'],
                'reasons' => $evaluation['reasons'],
            ];

            if ($evaluation['matched']) {
                $matched = $this->summarizeRoute($route, $index);
                $matchedParams = $evaluation['params'];
                break;
            }
        }

        return [
            'requested' => [
                'uri' => $uri,
                'method' => $method,
                'host' => $host,
            ],
            'matched' => $matched !== null,
            'matched_route' => $matched,
            'matched_params' => $matchedParams,
            'fallback_route' => $this->summarizeRoute($this->findFallbackRoute(), null),
            'checked_routes' => $checked,
        ];
    }

    protected function describeAction(mixed $action): string
    {
        if (is_string($action)) {
            return $action;
        }

        if (is_array($action) && count($action) === 2) {
            $controller = is_object($action[0]) ? get_class($action[0]) : (string) $action[0];
            return $controller . '@' . (string) $action[1];
        }

        if ($action instanceof \Closure) {
            return 'Closure';
        }

        if (is_object($action)) {
            return get_class($action);
        }

        return gettype($action);
    }

    /**
     * @param array<string, mixed>|null $route
     * @return array<string, mixed>|null
     */
    protected function summarizeRoute(?array $route, ?int $index = null): ?array
    {
        if ($route === null) {
            return null;
        }

        $meta = (array) ($route['meta'] ?? []);
        $middleware = array_values(array_unique(array_filter((array) ($route['middleware'] ?? []), fn($item) => $item !== null && $item !== '')));

        return [
            'index' => $index === null ? null : $index + 1,
            'method' => strtoupper((string) ($route['method'] ?? 'GET')),
            'uri' => $route['uri'] ?? '/',
            'name' => $route['name'] ?? null,
            'domain' => $route['domain'] ?? null,
            'middleware' => $middleware,
            'framework' => $meta['framework'] ?? null,
            'layout' => $meta['layout'] ?? null,
            'action' => $this->describeAction($route['action'] ?? null),
            'cacheable' => $this->isCacheableRoute($route),
        ];
    }

    /**
     * Evaluate a route against a request.
     *
     * @return array{matched: bool, reasons: array<int, string>, params: array<string, string>}
     */
    protected function evaluateRoute(array $route, string $uri, string $method, string $host): array
    {
        $reasons = [];

        if (!empty($route['domain'])) {
            $domainPattern = $this->buildDomainPattern((string) $route['domain']);
            if (!preg_match($domainPattern, $host)) {
                $reasons[] = 'domain mismatch';
                return ['matched' => false, 'reasons' => $reasons, 'params' => []];
            }
        }

        if (($route['method'] ?? 'GET') !== 'ANY' && strtoupper($method) !== strtoupper((string) ($route['method'] ?? 'GET'))) {
            $reasons[] = 'method mismatch';
            return ['matched' => false, 'reasons' => $reasons, 'params' => []];
        }

        $uriPattern = $this->buildRoutePattern($route);
        if (!preg_match($uriPattern, $uri, $matches)) {
            $reasons[] = 'uri mismatch';
            return ['matched' => false, 'reasons' => $reasons, 'params' => []];
        }

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return ['matched' => true, 'reasons' => [], 'params' => $params];
    }

    /**
     * Find the first route that matches a request.
     *
     * @return array{route: array<string, mixed>, params: array<string, string>, index: int}|null
     */
    protected function findMatchingRoute(string $uri, string $method, string $host): ?array
    {
        foreach ($this->routes as $index => $route) {
            if ($this->isFallbackRoute($route)) {
                continue;
            }
            $evaluation = $this->evaluateRoute($route, $uri, $method, $host);
            if ($evaluation['matched']) {
                return [
                    'route' => $route,
                    'params' => $evaluation['params'],
                    'index' => $index,
                ];
            }
        }

        return null;
    }

    protected function isFallbackRoute(array $route): bool
    {
        return ($route['method'] ?? null) === 'ANY'
            && ($route['uri'] ?? null) === '/{fallback}';
    }

    protected function buildDomainPattern(string $domain): string
    {
        $pattern = preg_quote($domain, '#');
        $pattern = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^.]+)', $pattern) ?? $pattern;
        return '#^' . $pattern . '$#';
    }

    protected function buildRoutePattern(array $route): string
    {
        $uriPattern = (string) ($route['uri'] ?? '/');

        foreach (($route['constraints'] ?? []) as $param => $regex) {
            $uriPattern = str_replace('{' . $param . '}', '(?P<' . $param . '>' . $regex . ')', $uriPattern);
        }

        $uriPattern = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^/]*)', $uriPattern) ?? $uriPattern;
        $uriPattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $uriPattern) ?? $uriPattern;

        return '#^' . $uriPattern . '$#';
    }

    protected function renderPhpExport(array $payload): string
    {
        return "<?php\n\nreturn " . var_export($payload, true) . ";\n";
    }

    protected function renderYamlExport(array $payload, int $indent = 0): string
    {
        $lines = [];
        $pad = str_repeat('  ', $indent);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                if ($this->isList($value)) {
                    $lines[] = $pad . $key . ':';
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $lines[] = $pad . '  -';
                            $nested = trim($this->renderYamlExport($item, $indent + 2));
                            if ($nested !== '') {
                                foreach (explode("\n", $nested) as $nestedLine) {
                                    $lines[] = $nestedLine;
                                }
                            }
                        } else {
                            $lines[] = $pad . '  - ' . $this->yamlScalar($item);
                        }
                    }
                } else {
                    $lines[] = $pad . $key . ':';
                    $nested = trim($this->renderYamlExport($value, $indent + 1));
                    if ($nested !== '') {
                        foreach (explode("\n", $nested) as $nestedLine) {
                            $lines[] = $nestedLine;
                        }
                    }
                }
            } else {
                $lines[] = $pad . $key . ': ' . $this->yamlScalar($value);
            }
        }

        return implode("\n", $lines);
    }

    protected function yamlScalar(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if ($value === null) {
            return 'null';
        }

        $value = (string) $value;
        if ($value === '' || preg_match('/[:#\-\{\}\[\],&\*\?]|^\s|\s$/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }

    protected function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    protected function wantsJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $xhrHeader = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $xhrHeader === 'xmlhttprequest';
    }

    protected static function isDebug(): bool
    {
        return filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
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

    /**
     * Group routes around a shared frontend framework and layout shell.
     *
     * @param array<string, mixed> $attributes
     */
    public function frontendGroup(string $framework, string $layout, \Closure $callback, array $attributes = []): void
    {
        $manager = \Nemesis\Frontend\FrontendManager::getInstance();
        $framework = $manager->normalizeFramework($framework);

        if (!$manager->supportsFramework($framework) && !$manager->isAllowed($framework)) {
            throw new \InvalidArgumentException("Frontend framework [{$framework}] is not supported by the frontend config.");
        }

        if (!$manager->isAllowed($framework)) {
            throw new \InvalidArgumentException("Frontend framework [{$framework}] is not allowed.");
        }

        $attributes['framework'] = $framework;
        $attributes['layout'] = $layout;

        $middleware = $attributes['middleware'] ?? [];
        $middleware = array_merge((array) $middleware, ["framework:{$framework}"]);
        $attributes['middleware'] = $middleware;

        $this->group($attributes, $callback);
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function add(string $method, string $uri, mixed $action, array $middleware = []): static
    {
        $prefix          = '';
        $groupMiddleware = [];
        $domain          = null;
        $groupMeta       = [];

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
            foreach (['framework', 'layout'] as $key) {
                if (array_key_exists($key, $group)) {
                    $groupMeta[$key] = $group[$key];
                }
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
            'meta'        => $groupMeta,
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

    protected function findFallbackRoute(): ?array
    {
        for ($i = count($this->routes) - 1; $i >= 0; $i--) {
            $route = $this->routes[$i];
            if (($route['method'] ?? null) === 'ANY' && ($route['uri'] ?? null) === '/{fallback}') {
                return $route;
            }
        }

        return null;
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

        $match = $this->findMatchingRoute($uri, $method, $host);
        if ($match !== null) {
            $route = $match['route'];
            $params = $match['params'];

            // --- Route metadata becomes request metadata ---
            foreach (($route['meta'] ?? []) as $key => $value) {
                $request->setMeta("route.{$key}", $value);
                if ($key === 'framework' && $request->getMeta('frontend.framework') === null) {
                    $request->setMeta('frontend.framework', $value);
                }
                if ($key === 'layout') {
                    $request->setMeta('frontend.layout', $value);
                }
            }

            // --- Apply route model binders ---
            $params = RouteModelBinder::resolveAll($params);

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

        // No route matched: try explicit fallback route first.
        $fallback = $this->findFallbackRoute();
        if ($fallback !== null) {
            $middleware = array_merge($this->globalMiddlewares, $this->resolveMiddleware($fallback['middleware']));
            return (new \Nemesis\Http\Pipeline())
                ->send($request)
                ->through($middleware)
                ->then(function ($request) use ($fallback) {
                    return $this->callAction($fallback['action'], $request, []);
                });
        }

        if (!headers_sent()) {
            http_response_code(404);
        }
        $routeDiagnostics = $this->matchDiagnostics($uri, $method, $host);
        if (file_exists(__DIR__ . '/Errors/404.php')) {
            ob_start();
            include __DIR__ . '/Errors/404.php';
            $content = (string) ob_get_clean();
            return \Nemesis\Http\Response::make($content, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $payload = ['error' => 'Route not found'];
        if (self::isDebug()) {
            $payload['diagnostics'] = $routeDiagnostics;
        }

        return \Nemesis\Http\Response::json($payload, 404);
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
