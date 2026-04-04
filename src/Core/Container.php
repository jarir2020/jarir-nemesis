<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 6 — PSR-11 Container | Updated: 2026-04-02

namespace Nemesis\Core;

use Nemesis\Contracts\ContainerInterface;

class Container implements ContainerInterface
{
    protected static ?self $instance = null;
    protected array $bindings   = [];
    protected array $instances  = [];

    // -------------------------------------------------------------------------
    // Singleton accessor
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    public static function setInstance(self $container): void
    {
        self::$instance = $container;
    }

    // -------------------------------------------------------------------------
    // PSR-11: has() — returns true if the container can return an entry
    // Checks registered bindings, cached singletons, and auto-wireable classes.
    // -------------------------------------------------------------------------

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    // -------------------------------------------------------------------------
    // PSR-11: get() — delegates to make() (throws on failure just like PSR-11)
    // -------------------------------------------------------------------------

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    // -------------------------------------------------------------------------
    // Binding registration
    // -------------------------------------------------------------------------

    /**
     * Register a transient binding.
     * Passing null for $concrete causes self-binding (resolve by class name).
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => false];
    }

    /**
     * Register a shared (singleton) binding.
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => true];
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve a class with auto-wiring.
     *
     * @param  array<string, mixed>  $parameters  Named primitive overrides
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Return cached singleton
        if (isset($this->instances[$abstract]) && empty($parameters)) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        $object = ($concrete instanceof \Closure)
            ? $concrete($this)
            : $this->resolve($concrete, $parameters);

        // Cache if shared and no overrides (overrides imply a different instance)
        if (empty($parameters) && ($this->bindings[$abstract]['shared'] ?? false)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function resolve(string $concrete, array $primitives = []): mixed
    {
        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $primitives);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor parameters, honouring named primitive overrides.
     *
     * @param  \ReflectionParameter[]  $params
     * @param  array<string, mixed>    $primitives
     */
    protected function resolveDependencies(array $params, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($params as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency [{$name}] in [{$parameter->getDeclaringClass()?->getName()}].");
            }
        }

        return $dependencies;
    }
}
