<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-03

namespace Nemesis\Config;

readonly class CacheConfig
{
    public function __construct(
        public string $driver,
        public int    $ttl,
        public string $prefix,
        public string $redisHost,
        public int    $redisPort,
    ) {}

    public static function fromEnv(): static
    {
        return new static(
            driver:    (string) (getenv('CACHE_DRIVER') ?: 'file'),
            ttl:       (int)    (getenv('CACHE_TTL')    ?: 3600),
            prefix:    (string) (getenv('CACHE_PREFIX') ?: 'nemesis_'),
            redisHost: (string) (getenv('REDIS_HOST')   ?: '127.0.0.1'),
            redisPort: (int)    (getenv('REDIS_PORT')   ?: 6379),
        );
    }

    public static function required(): array
    {
        return ['CACHE_DRIVER'];
    }
}
