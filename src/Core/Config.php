<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Config {
    protected static $configPath;
    protected static $cachedPath = __DIR__ . '/../../storage/framework/config.php';

    protected static $items = [];

    public static function load($path) {
        self::$configPath = rtrim((string) $path, '/\\');
        self::$cachedPath = self::$configPath . '/storage/framework/config.php';
        self::$items = [];

        // A cache contains the fully resolved config arrays and the environment
        // values used to build them. This keeps direct getenv() consumers
        // working after config:cache as well as config() consumers.
        if (file_exists(self::$cachedPath)) {
            $cached = require self::$cachedPath;
            if (is_array($cached) && array_key_exists('__nemesis_config_cache', $cached)) {
                self::$items = is_array($cached['config'] ?? null) ? $cached['config'] : [];
                self::restoreEnv($cached['env'] ?? []);
            } elseif (is_array($cached)) {
                // Read older cache files written as a plain config array.
                self::$items = $cached;
            }
            return;
        }

        // Load .env first
        self::loadEnv(self::$configPath);

        // Load config files
        self::loadConfigurationFiles(self::$configPath . '/config');
    }

    protected static function loadEnv($path) {
        if (!file_exists($path . '/.env')) {
            return;
        }

        $lines = file($path . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

            [$name, $value] = explode('=', $line, 2);
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
        $missing = new \stdClass();
        $value = self::arr_get(self::$items, $key, $missing);
        if ($value !== $missing) {
            return $value;
        }

        // Fallback to environment variables
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Write the resolved configuration to the project cache.
     *
     * @return string Absolute cache path.
     */
    public static function cache(): string
    {
        if (self::$configPath === null) {
            throw new \RuntimeException('Configuration must be loaded before it can be cached.');
        }

        $directory = dirname(self::$cachedPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create configuration cache directory: {$directory}");
        }

        $payload = [
            '__nemesis_config_cache' => 1,
            'config' => self::$items,
            'env' => self::environmentSnapshot(),
        ];

        if (file_put_contents(self::$cachedPath, "<?php\n\nreturn " . var_export($payload, true) . ";\n", LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write configuration cache: " . self::$cachedPath);
        }

        return self::$cachedPath;
    }

    /** Clear the on-disk configuration cache. */
    public static function clear(): bool
    {
        return !file_exists(self::$cachedPath) || unlink(self::$cachedPath);
    }

    protected static function environmentSnapshot(): array
    {
        $snapshot = [];
        foreach (array_unique(array_merge(array_keys($_ENV), array_keys($_SERVER))) as $name) {
            if (is_string($name) && getenv($name) !== false) {
                $snapshot[$name] = getenv($name);
            }
        }

        return $snapshot;
    }

    protected static function restoreEnv(mixed $values): void
    {
        if (!is_array($values)) {
            return;
        }

        foreach ($values as $name => $value) {
            if (!is_string($name) || is_array($value) || is_object($value)) {
                continue;
            }

            putenv($name . '=' . (string) $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    protected static function arr_get($array, $key, $default = null) {
        if (is_null($key)) return $array;
        if (is_array($array) && array_key_exists($key, $array)) return $array[$key];
        if (strpos($key, '.') === false) return $default;

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
