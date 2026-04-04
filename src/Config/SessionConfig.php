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
    ) {}

    public static function fromEnv(): static
    {
        return new static(
            driver:     (string) (getenv('SESSION_DRIVER')  ?: 'file'),
            lifetime:   (int)    (getenv('SESSION_LIFETIME')?: 120),
            cookieName: (string) (getenv('SESSION_COOKIE')  ?: 'nemesis_session'),
            secure:     filter_var(getenv('SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOLEAN),
            sameSite:   (string) (getenv('SESSION_SAME_SITE')?: 'lax'),
        );
    }

    public static function required(): array
    {
        return [];
    }
}
