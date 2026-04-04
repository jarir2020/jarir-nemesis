<?php
declare(strict_types=1);

namespace Nemesis\Core\Traits;

trait HasFactory {
    public static function factory() {
        $factoryClass = 'Database\\Factories\\' . class_basename(static::class) . 'Factory';
        
        // Handle namespace path resolution if differ
        // For now assume Database\Factories namespace relative to App root is mapped
        
        // Hardcode fallback for this exercise since we don't have full autoloading for factories yet
        // In real app, we'd guess the factory name
        
        if (class_exists($factoryClass)) {
            return new $factoryClass();
        }
        
        // Try App\Database\Factories
        $appFactory = 'App\\Database\\Factories\\' . class_basename(static::class) . 'Factory';
        if (class_exists($appFactory)) {
            return new $appFactory();
        }

        throw new \Exception("Factory not found for " . static::class);
    }
}
