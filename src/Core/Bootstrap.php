<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Bootstrap config validation | Updated: 2026-04-03

namespace Nemesis\Core;

use Nemesis\Config\ConfigFactory;
use Nemesis\Config\AppConfig;
use Nemesis\Config\DatabaseConfig;
use Nemesis\Config\CacheConfig;
use Nemesis\Config\QueueConfig;

class Bootstrap
{
    /** Config DTOs whose required() env keys are validated on every boot. */
    private const CONFIG_CLASSES = [
        AppConfig::class,
        DatabaseConfig::class,
        CacheConfig::class,
        QueueConfig::class,
    ];

    public static function check(): void
    {
        // 1. .env must exist
        if (!file_exists(__DIR__ . '/../../.env')) {
            self::fail('Configuration file (.env) is missing.');
        }

        // 2. APP_KEY must be set and long enough
        if (!getenv('APP_KEY') || strlen((string) getenv('APP_KEY')) < 32) {
            self::fail("APP_KEY is not set or too short. Run: php nemesis key:generate");
        }

        // 3. Timezone — added 2026-04-02
        $tz = (string) (getenv('APP_TIMEZONE') ?: 'UTC');
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            self::fail("APP_TIMEZONE '{$tz}' is invalid. Use a valid PHP timezone, e.g. 'UTC' or 'Asia/Dhaka'.");
        }
        date_default_timezone_set($tz);

        // 4. Required env keys — fail-fast validation added 2026-04-03
        $missing = ConfigFactory::validateRequired(self::CONFIG_CLASSES);
        if (!empty($missing)) {
            self::fail(
                'Missing required .env keys: ' . implode(', ', $missing) . "\n" .
                'Copy .env.example to .env and fill in the values.'
            );
        }

        // 5. Storage must be writable (warn, don't die — may not exist in all setups)
        $storage = __DIR__ . '/../../storage';
        if (is_dir($storage) && !is_writable($storage)) {
            self::fail("Storage directory is not writable: {$storage}");
        }
    }

    protected static function fail(string $message): never
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[Boot Error] {$message}\n");
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>[Boot Error] Nemesis Framework failed to start.</h1>';
            echo '<p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
        }
        exit(1);
    }
}
