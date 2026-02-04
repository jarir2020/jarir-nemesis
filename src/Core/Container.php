<?php
namespace Nemesis\Core;

class Container {
    protected static $instance = null;
    protected $bindings = [];
    protected $instances = [];

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    public static function setInstance($container) {
        self::$instance = $container;
    }

    public function bind($abstract, $concrete = null, $shared = false) {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Support Interface binding
        if ($abstract !== $concrete) {
            // Already handled by resolving later, just store mapping
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }

    public function make($abstract) {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        if ($concrete instanceof \Closure) {
            $object = $concrete($this);
        } else {
            $object = $this->resolve($concrete);
        }

        if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function resolve($concrete) {
        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$concrete} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies($parameters) {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency {$parameter->name}");
            }
        }

        return $dependencies;
    }
}
