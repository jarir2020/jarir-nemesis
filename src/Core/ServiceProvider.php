<?php
declare(strict_types=1);
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
