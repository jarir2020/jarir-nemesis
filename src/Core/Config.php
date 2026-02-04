<?php
namespace Nemesis\Core;

class Config {
    protected static $configPath;
    protected static $cachedPath = __DIR__ . '/../../storage/framework/config.php';

    public static function load($path) {
        self::$configPath = $path;
        
        // Load from cache if it exists
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
            
            // Strip surrounding quotes
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

    public static function cache() {
        if (!is_dir(dirname(self::$cachedPath))) {
            mkdir(dirname(self::$cachedPath), 0755, true);
        }

        // We only cache the environment variables for now
        $config = $_ENV;
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents(self::$cachedPath, $content);
    }

    public static function clear() {
        if (file_exists(self::$cachedPath)) {
            unlink(self::$cachedPath);
        }
    }

    public static function get($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}
