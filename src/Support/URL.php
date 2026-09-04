<?php
declare(strict_types=1);

namespace Nemesis\Support;

class URL {
    public static function sign(string $url, ?int $expiration = null): string {
        $appKey = self::signingKey();
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URL supplied for signing.');
        }

        $query = self::query($parts['query'] ?? '');
        if ($expiration !== null) {
            $query['expires'] = time() + $expiration;
        }

        $payload = self::buildUrl($parts, $query);
        $signature = hash_hmac('sha256', $payload, $appKey);

        $query['signature'] = $signature;
        return self::buildUrl($parts, $query);
    }

    public static function verifySign(string $requestUrl): bool {
        $appKey = self::signingKey();
        $parts = parse_url($requestUrl);
        if ($parts === false) return false;

        $query = self::query($parts['query'] ?? '');
        if (!isset($query['signature']) || !is_string($query['signature'])) return false;

        $signature = $query['signature'];
        unset($query['signature']);

        // Check expiration
        if (isset($query['expires'])) {
            if (!is_scalar($query['expires']) || filter_var($query['expires'], FILTER_VALIDATE_INT) === false) {
                return false;
            }
            if (time() >= (int) $query['expires']) return false;
        }

        $payload = self::buildUrl($parts, $query);
        $expected = hash_hmac('sha256', $payload, $appKey);
        return hash_equals($expected, $signature);
    }

    /** @return array<string, mixed> */
    private static function query(string $query): array
    {
        $values = [];
        parse_str($query, $values);
        return $values;
    }

    private static function signingKey(): string
    {
        $key = getenv('APP_KEY');
        if (($key === false || $key === '') && function_exists('config')) {
            $key = config('app.key', '');
        }

        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('APP_KEY must be configured before signing URLs.');
        }

        return $key;
    }

    /** @param array<string, mixed> $parts */
    private static function buildUrl(array $parts, array $query): string
    {
        $url = '';
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
            if (isset($parts['user'])) {
                $url .= $parts['user'];
                if (isset($parts['pass'])) $url .= ':' . $parts['pass'];
                $url .= '@';
            }
            $url .= $parts['host'] ?? '';
            if (isset($parts['port'])) $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';
        if ($url === '') $url = '/';

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }
}
