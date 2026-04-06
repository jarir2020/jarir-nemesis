<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: manager facade | 2026-04-06

namespace Nemesis\Auth\Social;

use Nemesis\Auth\Social\Providers\{GoogleProvider, GitHubProvider, XProvider, FacebookProvider, LinkedInProvider};
use Nemesis\Auth\JWT;
use Nemesis\Core\Database;

/**
 * SocialAuth — entry point for social login.
 *
 * Usage:
 *   // Redirect user:
 *   $url = SocialAuth::driver('google')->getAuthorizationUrl();
 *   $_SESSION['oauth_state'] = SocialAuth::driver('google')->getState();
 *   header("Location: $url");
 *
 *   // Handle callback:
 *   $socialUser = SocialAuth::handleCallback('google', $_GET['code'], $_GET['state']);
 *   $tokens     = SocialAuth::findOrCreateUser($socialUser);
 */
class SocialAuth
{
    /** @var array<string, SocialProviderInterface> */
    private static array $instances = [];

    // -------------------------------------------------------------------------
    // Driver factory
    // -------------------------------------------------------------------------

    public static function driver(string $name): SocialProviderInterface
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = self::make($name);
        }
        return self::$instances[$name];
    }

    private static function make(string $name): SocialProviderInterface
    {
        $cfg = self::config($name);

        return match (strtolower($name)) {
            'google'   => new GoogleProvider  ($cfg['client_id'], $cfg['client_secret'], $cfg['redirect']),
            'github'   => new GitHubProvider  ($cfg['client_id'], $cfg['client_secret'], $cfg['redirect']),
            'x',
            'twitter'  => new XProvider       ($cfg['client_id'], $cfg['client_secret'], $cfg['redirect']),
            'facebook' => new FacebookProvider($cfg['client_id'], $cfg['client_secret'], $cfg['redirect']),
            'linkedin' => new LinkedInProvider($cfg['client_id'], $cfg['client_secret'], $cfg['redirect']),
            default    => throw new \InvalidArgumentException("Unknown social driver: {$name}"),
        };
    }

    // -------------------------------------------------------------------------
    // Callback handler
    // -------------------------------------------------------------------------

    /**
     * Validate state + exchange code for SocialUser.
     */
    public static function handleCallback(
        string $driver,
        string $code,
        string $state,
        string $expectedState = ''
    ): SocialUser {
        if ($expectedState && !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('OAuth state mismatch. Possible CSRF attack.');
        }

        $provider    = self::driver($driver);
        $tokenData   = $provider->getAccessToken($code);
        $accessToken = $tokenData['access_token']
            ?? throw new \RuntimeException("No access_token in {$driver} response.");

        return $provider->getUser($accessToken);
    }

    // -------------------------------------------------------------------------
    // Find-or-create user
    // -------------------------------------------------------------------------

    /**
     * Find an existing user by social account or create a new one.
     * Returns a JWT token pair ready to send to the client.
     *
     * @return array{access: string, refresh: string, expires_in: int, token_type: string, user_id: int}
     */
    public static function findOrCreateUser(SocialUser $socialUser): array
    {
        self::ensureSocialAccountsTable();

        // 1. Existing social account link
        $link = Database::view(
            "SELECT * FROM social_accounts WHERE provider = ? AND provider_id = ? LIMIT 1",
            [$socialUser->provider, $socialUser->id]
        );

        if (!empty($link)) {
            $userId = (int) $link[0]['user_id'];
        } else {
            // 2. User with matching email
            $userId = null;
            if ($socialUser->email) {
                $row = Database::view(
                    "SELECT id FROM users WHERE email = ? LIMIT 1",
                    [$socialUser->email]
                );
                if (!empty($row)) {
                    $userId = (int) $row[0]['id'];
                }
            }

            // 3. Create new user
            if (!$userId) {
                Database::statement(
                    "INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)",
                    [$socialUser->name, $socialUser->email, '', date('Y-m-d H:i:s')]
                );
                $userId = (int) Database::connect()->lastInsertId();
            }

            // 4. Link social account
            Database::statement(
                "INSERT INTO social_accounts (user_id, provider, provider_id, avatar, token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $socialUser->provider, $socialUser->id,
                 $socialUser->avatar, $socialUser->token, date('Y-m-d H:i:s')]
            );
        }

        // 5. Issue JWT pair
        $pair = JWT::issueTokenPair([
            'sub'      => $userId,
            'provider' => $socialUser->provider,
        ]);

        return array_merge($pair, ['user_id' => $userId]);
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    private static function config(string $driver): array
    {
        // Try config/social.php first
        $file = __DIR__ . '/../../../config/social.php';
        if (file_exists($file)) {
            $all = require $file;
            if (isset($all[$driver])) {
                return $all[$driver];
            }
        }

        // Fallback to env vars: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT
        $upper = strtoupper($driver);
        return [
            'client_id'     => (string) (getenv("{$upper}_CLIENT_ID")     ?: ''),
            'client_secret' => (string) (getenv("{$upper}_CLIENT_SECRET") ?: ''),
            'redirect'      => (string) (getenv("{$upper}_REDIRECT")      ?: ''),
        ];
    }

    // -------------------------------------------------------------------------
    // DB scaffold
    // -------------------------------------------------------------------------

    private static function ensureSocialAccountsTable(): void
    {
        $pdo    = Database::getPdo();
        $driver = $pdo ? strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) : 'sqlite';

        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS social_accounts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                provider    TEXT NOT NULL,
                provider_id TEXT NOT NULL,
                avatar      TEXT NOT NULL DEFAULT '',
                token       TEXT NOT NULL DEFAULT '',
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(provider, provider_id)
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS social_accounts (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                provider    VARCHAR(32) NOT NULL,
                provider_id VARCHAR(255) NOT NULL,
                avatar      TEXT NOT NULL DEFAULT '',
                token       TEXT NOT NULL DEFAULT '',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_provider (provider, provider_id)
            ) ENGINE=INNODB");
        }
    }

    /** Reset driver instances (useful in tests). */
    public static function reset(): void
    {
        self::$instances = [];
    }
}
