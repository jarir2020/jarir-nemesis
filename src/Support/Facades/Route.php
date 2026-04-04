<?php
declare(strict_types=1);

namespace Nemesis\Support\Facades;

use Nemesis\Core\Container;
use Nemesis\Router\Router;

class Route {
    protected static $router;

    protected static function getRouter() {
        if (!self::$router) {
            return Container::getInstance()->make(Router::class);
        }
        return self::$router;
    }

    public static function __callStatic($method, $args) {
        return call_user_func_array([self::getRouter(), $method], $args);
    }
}
