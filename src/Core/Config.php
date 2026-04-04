<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Config {
    protected static $configPath;
    protected static $cachedPath = __DIR__ . '/../../storage/framework/config.php';

    protected static $items = [];

    public static function load($path) {
        self::$configPath = $path;
        
        // Load .env first
        self::loadEnv($path);

        // Load config files
        self::loadConfigurationFiles($path . '/config');
    }

    protected static function loadEnv($path) {
        if (file_exists(self::$cachedPath)) {
            $config = require self::$cachedPath;
            foreach ($config as $name => $value) {
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
            return;
        }

        if (!file_exists($path . '/.env')) {
            return;
        }

        $lines = file($path . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = trim($value, '"');
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = trim($value, "'");
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    protected static function loadConfigurationFiles($path) {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . '/*.php') as $file) {
            $key = basename($file, '.php');
            self::$items[$key] = require $file;
        }
    }

    public static function get($key, $default = null) {
        // Check loaded config arrays first
        $value = self::arr_get(self::$items, $key);
        if ($value !== null) {
            return $value;
        }

        // Fallback to environment variables
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    protected static function arr_get($array, $key, $default = null) {
        if (is_null($key)) return $array;
        if (isset($array[$key])) return $array[$key];
        if (strpos($key, '.') === false) return $array[$key] ?? $default;

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }
}
