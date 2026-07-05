<?php
declare(strict_types=1);

namespace Nemesis\Support;

use Nemesis\Router\Router;

/**
 * High-level REST resource helper.
 */
class Api
{
    /**
     * Register a conventional REST resource route set.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function resource(
        string $name,
        string|object $controller,
        ?Router $router = null,
        array $options = []
    ): array {
        $router ??= Router::getInstance();
        $uriPrefix = trim((string) ($options['prefix'] ?? $name), '/');
        $namePrefix = trim((string) ($options['as'] ?? $name), '.');
        $middleware = (array) ($options['middleware'] ?? []);
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? [];

        $definitions = [
            ['GET',    "/{$uriPrefix}",                 'index',   "{$namePrefix}.index"],
            ['GET',    "/{$uriPrefix}/create",          'create',  "{$namePrefix}.create"],
            ['POST',   "/{$uriPrefix}",                 'store',   "{$namePrefix}.store"],
            ['GET',    "/{$uriPrefix}/{id}",            'show',    "{$namePrefix}.show"],
            ['GET',    "/{$uriPrefix}/{id}/edit",       'edit',    "{$namePrefix}.edit"],
            ['PUT',    "/{$uriPrefix}/{id}",            'update',  "{$namePrefix}.update"],
            ['PATCH',  "/{$uriPrefix}/{id}",            'update',  "{$namePrefix}.update.patch"],
            ['DELETE', "/{$uriPrefix}/{id}",            'destroy', "{$namePrefix}.destroy"],
        ];

        if (is_array($only) && $only !== []) {
            $definitions = array_values(array_filter(
                $definitions,
                fn(array $route): bool => in_array($route[2], $only, true)
            ));
        }

        if (is_array($except) && $except !== []) {
            $definitions = array_values(array_filter(
                $definitions,
                fn(array $route): bool => !in_array($route[2], $except, true)
            ));
        }

        $registered = [];
        foreach ($definitions as [$method, $uri, $action, $routeName]) {
            $route = $router->add($method, $uri, [$controller, $action], $middleware)->name($routeName);
            $registered[] = [
                'method' => $method,
                'uri' => $uri,
                'action' => $action,
                'name' => $routeName,
                'route' => $route,
            ];
        }

        return $registered;
    }
}
