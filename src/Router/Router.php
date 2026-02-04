<?php

namespace Nemesis\Router;

use Nemesis\Core\Container;

class Router {
    protected $routes = [];
    protected $globalMiddlewares = [];
    protected $container;
    protected static $cachedPath = __DIR__ . '/../../storage/framework/routes.php';

    public function __construct(Container $container = null) {
        $this->container = $container ?: new Container();
        $this->loadCachedRoutes();
    }

    protected function loadCachedRoutes() {
        if (file_exists(self::$cachedPath)) {
            $this->routes = require self::$cachedPath;
        }
    }

    public function cache() {
        foreach ($this->routes as $route) {
            if ($route['action'] instanceof \Closure) {
                throw new \Exception("Cannot cache routes with Closures. Please use Controller classes instead for route: " . $route['uri']);
            }
            foreach ($route['middleware'] as $m) {
                if ($m instanceof \Closure) {
                    throw new \Exception("Cannot cache routes with Closure middleware. Please use Middleware classes instead for route: " . $route['uri']);
                }
            }
        }

        if (!is_dir(dirname(self::$cachedPath))) {
            mkdir(dirname(self::$cachedPath), 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($this->routes, true) . ";\n";
        file_put_contents(self::$cachedPath, $content);
    }

    public static function clear() {
        if (file_exists(self::$cachedPath)) {
            unlink(self::$cachedPath);
        }
    }

    public function globalMiddleware($middleware) {
        $this->globalMiddlewares[] = $middleware;
    }

    public function add($method, $uri, $action, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => (array) $middleware
        ];
    }

    public function dispatch($uri, $method) {
        $uri = strtok($uri, '?'); // Strip query string for matching
        $request = $this->container->make(\Nemesis\Http\Request::class);

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            
            if ($method == $route['method'] && preg_match("#^{$pattern}$#", $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    
                $middleware = $this->resolveMiddleware($route['middleware']);

                return (new \Nemesis\Http\Pipeline())
                    ->send($request)
                    ->through($middleware)
                    ->then(function ($request) use ($route, $params) {
                        $action = $route['action'];

                        // Handle Controller@method or [Controller::class, 'method']
                        if (is_string($action) && strpos($action, '@') !== false) {
                            [$controller, $methodName] = explode('@', $action);
                            $instance = $this->container->make($controller);
                            return call_user_func_array([$instance, $methodName], $params);
                        }

                        if (is_array($action) && count($action) === 2) {
                            [$controller, $methodName] = $action;
                            $instance = is_string($controller) ? $this->container->make($controller) : $controller;
                            return call_user_func_array([$instance, $methodName], $params);
                        }

                        if (is_callable($action)) {
                            return call_user_func_array($action, $params);
                        }
                        
                        throw new \Exception("Invalid route action.");
                    });
            }
        }
    
        header("HTTP/1.1 404 Not Found");
        if (file_exists(__DIR__ . '/../Errors/404.php')) {
            include(__DIR__ . '/../Errors/404.php');
        } else {
            echo json_encode(['error' => 'Route not found']);
        }
    }
    

    protected function resolveMiddleware($middleware) {
        $kernel = new \App\Http\Kernel();
        $mapped = $kernel->getRouteMiddleware();
        $resolved = [];

        foreach ($middleware as $m) {
            if (is_string($m)) {
                $parts = explode(':', $m);
                $name = $parts[0];
                $args = isset($parts[1]) ? explode(',', $parts[1]) : [];

                if (isset($mapped[$name])) {
                    $class = $mapped[$name];
                    $resolved[] = function($request, $next) use ($class, $args) {
                        return (new $class())->handle($request, $next, ...$args);
                    };
                    continue;
                }
            }
            $resolved[] = $m;
        }

        return $resolved;
    }

    public function getRoutes() {
        return $this->routes;
    }
}
