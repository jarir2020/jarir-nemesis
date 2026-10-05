<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-03

namespace Nemesis\Config;

readonly class SessionConfig
{
    public function __construct(
        public string $driver,
        public int    $lifetime,
        public string $cookieName,
        public bool   $secure,
        public string $sameSite,
        public string $path = '',
    ) {}

    public static function fromEnv(): static
    {
        $settings = function_exists('config')
            ? (array) \config('session', [])
            : [];

        $envValue = static function (string $key, mixed $default): mixed {
            $value = getenv($key);
            if ($value === false || $value === '') {
                return $default;
            }

            return function_exists('env') ? \env($key, $default) : $value;
        };

        $path = $envValue(
            'SESSION_PATH',
            $envValue('SESSION_SAVE_PATH', $settings['path'] ?? '')
        );
        if ($path === '') {
            $path = function_exists('base_path')
                ? base_path('storage/session')
                : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'session';
        }

        return new static(
            driver:     (string) $envValue('SESSION_DRIVER', $settings['driver'] ?? 'file'),
            lifetime:   (int)    $envValue('SESSION_LIFETIME', $settings['lifetime'] ?? 120),
            cookieName: (string) $envValue('SESSION_COOKIE', $settings['cookie'] ?? $settings['cookie_name'] ?? 'nemesis_session'),
            secure:     (bool)   $envValue(
                'SESSION_SECURE_COOKIE',
                $envValue('SESSION_SECURE', $settings['secure'] ?? false)
            ),
            sameSite:   strtolower((string) $envValue(
                'SESSION_SAME_SITE',
                $settings['same_site'] ?? $settings['sameSite'] ?? 'lax'
            )),
            path:       (string) $path,
        );
    }

    public static function required(): array
    {
        return [];
    }
}
