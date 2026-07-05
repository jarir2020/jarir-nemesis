<?php
declare(strict_types=1);

namespace Nemesis\Auth;

/**
 * MicroserviceBridge — optional adapter for external auth microservices.
 *
 * It can delegate to a package client if one is available, or to an injected
 * transport callback for tests and local development.
 */
class MicroserviceBridge
{
    /** @var null|callable(string, array, array): array|null */
    private static $transport = null;

    private static array $config = [
        'base_url' => '',
        'client_class' => '',
        'timeout' => 5,
        'token' => '',
    ];

    public static function configure(array $config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    public static function reset(): void
    {
        static::$transport = null;
        static::$config = [
            'base_url' => '',
            'client_class' => '',
            'timeout' => 5,
            'token' => '',
        ];
    }

    public static function setTransport(?callable $transport): void
    {
        static::$transport = $transport;
    }

    public static function authenticate(array $credentials): ?array
    {
        return static::request('authenticate', $credentials);
    }

    public static function refresh(string $refreshToken): ?array
    {
        return static::request('refresh', ['refresh_token' => $refreshToken]);
    }

    public static function profile(string $accessToken): ?array
    {
        return static::request('profile', ['access_token' => $accessToken]);
    }

    public static function syncUser(array $payload): ?array
    {
        return static::request('syncUser', $payload);
    }

    private static function request(string $action, array $payload): ?array
    {
        $config = static::$config;

        $clientClass = (string) ($config['client_class'] ?? '');
        if ($clientClass !== '' && class_exists($clientClass)) {
            $client = new $clientClass($config);
            if (method_exists($client, $action)) {
                $result = $client->{$action}($payload);
                return is_array($result) ? $result : null;
            }
        }

        if (static::$transport !== null) {
            $result = (static::$transport)($action, $payload, $config);
            return is_array($result) ? $result : null;
        }

        return null;
    }
}
