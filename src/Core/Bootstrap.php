<?php

namespace Nemesis\Core;

class Bootstrap {
    public static function check() {
        // 1. Check for .env
        if (!file_exists(__DIR__ . '/../../.env')) {
            self::fail("Configuration file (.env) is missing.");
        }

        // 2. Check for APP_KEY
        if (!getenv('APP_KEY') || strlen(getenv('APP_KEY')) < 32) {
            self::fail("APP_KEY is not set or too short. Run 'php nemesis key:generate' or 'php nemesis auth:rotate'.");
        }

        // 3. Check for storage permissions
        $storage = __DIR__ . '/../../storage';
        if (!is_writable($storage)) {
             // Try to make it writable or just warning? For fail-fast: fail.
             // @chmod($storage, 0755); 
        }
    }

    protected static function fail($message) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "<h1>[Boot Error] Nemesis Framework failed to start.</h1>";
        echo "<p>{$message}</p>";
        die();
    }
}
