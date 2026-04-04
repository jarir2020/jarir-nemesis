<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-03

namespace Nemesis\Config;

/**
 * Typed application config DTO.
 * Populated from config/app.php + .env at boot.
 *
 * Usage:  $cfg = AppConfig::fromArray(config('app'));
 *         $cfg->name;  $cfg->debug;  $cfg->timezone;
 */
readonly class AppConfig
{
    public function __construct(
        public string $name,
        public string $env,
        public bool   $debug,
        public string $url,
        public string $timezone,
        public string $locale,
        public string $key,
        public string $cipher,
    ) {}

    public static function fromArray(array $cfg): static
    {
        return new static(
            name:     (string)  ($cfg['name']     ?? 'Nemesis'),
            env:      (string)  ($cfg['env']      ?? 'production'),
            debug:    (bool)    ($cfg['debug']    ?? false),
            url:      (string)  ($cfg['url']      ?? 'http://localhost'),
            timezone: (string)  ($cfg['timezone'] ?? 'UTC'),
            locale:   (string)  ($cfg['locale']   ?? 'en'),
            key:      (string)  ($cfg['key']      ?? ''),
            cipher:   (string)  ($cfg['cipher']   ?? 'AES-256-CBC'),
        );
    }

    /** Required keys that must be present (non-empty) in .env / config. */
    public static function required(): array
    {
        return ['APP_KEY'];
    }
}
