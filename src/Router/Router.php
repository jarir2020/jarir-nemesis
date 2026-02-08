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

    protected $groupStack = [];

    public function group($attributes, \Closure $callback) {
        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }

    public function add($method, $uri, $action, $middleware = []) {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
            }
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $middleware = array_merge($groupMiddleware, (array) $middleware);

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'name' => null,
            'constraints' => []
        ];

        return $this;
    }

    public function name($name) {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['name'] = $name;
        }
        return $this;
    }

    public function fallback($action) {
        $this->add('ANY', '{fallback}', $action)->where('fallback', '.*');
    }

    public function where($name, $expression) {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['constraints'][$name] = $expression;
        }
        return $this;
    }

    public function get($uri, $action) {
        return $this->add('GET', $uri, $action);
    }

    public function post($uri, $action) {
        return $this->add('POST', $uri, $action);
    }

    public function put($uri, $action) {
        return $this->add('PUT', $uri, $action);
    }

    public function patch($uri, $action) {
        return $this->add('PATCH', $uri, $action);
    }

    public function delete($uri, $action) {
        return $this->add('DELETE', $uri, $action);
    }

    public function options($uri, $action) {
        return $this->add('OPTIONS', $uri, $action);
    }

    public function dispatch($uri, $method) {
        $uri = strtok($uri, '?'); // Strip query string for matching
        $request = $this->container->make(\Nemesis\Http\Request::class);

        foreach ($this->routes as $route) {
            $uriPattern = $route['uri'];
            
            // Apply constraints
            foreach ($route['constraints'] as $param => $regex) {
                $uriPattern = str_replace('{' . $param . '}', '(?P<' . $param . '>' . $regex . ')', $uriPattern);
            }
            
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $uriPattern);
            
            if ($method == $route['method'] && preg_match("#^{$pattern}$#", $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    
                $middleware = array_merge($this->globalMiddlewares, $this->resolveMiddleware($route['middleware']));

                return (new \Nemesis\Http\Pipeline())
                    ->send($request)
                    ->through($middleware)
                    ->then(function ($request) use ($route, $params) {
                        $action = $route['action'];

                        // Handle Controller@method or [Controller::class, 'method']
                        if (is_string($action) && strpos($action, '@') !== false) {
                            [$controller, $methodName] = explode('@', $action);
                            $instance = $this->container->make($controller);
                            return call_user_func_array([$instance, $methodName], array_merge([$request], $params));
                        }

                        if (is_array($action) && count($action) === 2) {
                            [$controller, $methodName] = $action;
                            $instance = is_string($controller) ? $this->container->make($controller) : $controller;
                            return call_user_func_array([$instance, $methodName], array_merge([$request], $params));
                        }

                        if (is_callable($action)) {
                            return call_user_func_array($action, array_merge([$request], $params));
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
        $groups = $kernel->getMiddlewareGroups();

        $resolved = [];

        foreach ($middleware as $m) {
            if (is_string($m)) {
                // Check if it's a group
                if (isset($groups[$m])) {
                    $resolved = array_merge($resolved, $this->resolveMiddleware($groups[$m]));
                    continue;
                }

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
