<?php
namespace Nemesis\Core;

abstract class ServiceProvider {
    protected $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    abstract public function register();
    
    public function boot() {
        // Optional boot logic
    }
}
