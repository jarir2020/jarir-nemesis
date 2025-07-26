<?php

namespace Nemesis\Router;

class Router {
    protected $routes = [];

    public function add($method, $uri, $action) {
        $this->routes[] = compact('method', 'uri', 'action');
    }

    public function dispatch($uri, $method) {
        foreach ($this->routes as $route) {
            // Adjust the pattern to match any alphanumeric parameters
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            
            if ($method == $route['method'] && preg_match("#^{$pattern}$#", $uri, $matches)) {
                // Extract parameters from the URI
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }
    
                // Merge query string parameters
                $queryStringParams = [];
                if (isset($_GET)) {
                    $queryStringParams = $_GET;
                }
    
                $params = array_merge($params, $queryStringParams);
    
                // Call the controller and pass the parameters
                $action = $route['action'];
                call_user_func_array($action, $params);
                return;
            }
        }
    
        // If no routes matched
        header("HTTP/1.1 404 Not Found");
        //echo json_encode(['message' => 'Route not found']);
        include('Errors/404.php'); 
    }
    

    public function getRoutes() {
        return $this->routes;
    }
}
