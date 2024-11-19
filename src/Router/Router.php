<?php

namespace Nemesis\Router;

class Router {
    protected $routes = [];

    public function add($method, $uri, $action) {
        $this->routes[] = compact('method', 'uri', 'action');
    }

    public function dispatch($uri, $method) {
        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $route['uri']);
            if ($method == $route['method'] && preg_match("#^{$pattern}$#", $uri, $matches)) {
                // Extract parameters from the URI
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }

                // Call the controller and pass the parameters
                $action = $route['action'];
                call_user_func_array($action, $params);
                return;
            }
        }

        // If no routes matched
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['message' => 'Route not found']);
    }

    public function getRoutes() {
        return $this->routes;
    }
}
