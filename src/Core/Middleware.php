<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Middleware {
    protected $middlewares = [];

    public function add($middleware) {
        $this->middlewares[] = $middleware;
    }

    public function handle($request) {
        foreach ($this->middlewares as $middleware) {
            call_user_func($middleware, $request);
        }
    }
}
