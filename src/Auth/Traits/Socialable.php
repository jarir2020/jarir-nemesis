<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: Socialable trait | 2026-04-06

namespace Nemesis\Auth\Traits;

use Nemesis\Auth\Social\SocialAuth;
use Nemesis\Auth\Social\SocialUser;

/**
 * Socialable — mixin for User models that support social login.
 *
 * Usage on your User model:
 *   class User extends Model {
 *       use Socialable;
 *   }
 *
 * Then in controllers:
 *   $user = User::find($id);
 *   $accounts = $user->socialAccounts();
 *   $user->linkSocial($socialUser);
 *   $user->unlinkSocial('google');
 *   $tokens = User::loginWithSocial('google', $code, $state, $expectedState);
 */
trait Socialable
{
    // -------------------------------------------------------------------------
    // Instance helpers
    // -------------------------------------------------------------------------

    /**
     * Get all social accounts linked to this user.
     */
    public function socialAccounts(): array
    {
        $userId = $this->id ?? null;
        if (!$userId) {
            return [];
        }

        return \Nemesis\Core\Database::view(
            "SELECT * FROM social_accounts WHERE user_id = ? ORDER BY created_at DESC",
            [(int) $userId]
        );
    }

    /**
     * Check if user has a specific provider linked.
     */
    public function hasSocial(string $provider): bool
    {
        $userId = $this->id ?? null;
        if (!$userId) {
            return false;
        }

        $row = \Nemesis\Core\Database::view(
            "SELECT id FROM social_accounts WHERE user_id = ? AND provider = ? LIMIT 1",
            [(int) $userId, strtolower($provider)]
        );

        return !empty($row);
    }

    /**
     * Link a SocialUser to this user (upsert).
     */
    public function linkSocial(SocialUser $socialUser): void
    {
        $userId = $this->id ?? null;
        if (!$userId) {
            throw new \RuntimeException('Cannot link social account: user has no id.');
        }

        // Check existing link for this provider + provider_id
        $existing = \Nemesis\Core\Database::view(
            "SELECT id FROM social_accounts WHERE provider = ? AND provider_id = ? LIMIT 1",
            [$socialUser->provider, $socialUser->id]
        );

        if (!empty($existing)) {
            // Update token and avatar
            \Nemesis\Core\Database::statement(
                "UPDATE social_accounts SET user_id = ?, avatar = ?, token = ? WHERE provider = ? AND provider_id = ?",
                [(int) $userId, $socialUser->avatar, $socialUser->token, $socialUser->provider, $socialUser->id]
            );
        } else {
            \Nemesis\Core\Database::statement(
                "INSERT INTO social_accounts (user_id, provider, provider_id, avatar, token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [(int) $userId, $socialUser->provider, $socialUser->id,
                 $socialUser->avatar, $socialUser->token, date('Y-m-d H:i:s')]
            );
        }
    }

    /**
     * Unlink a specific social provider from this user.
     */
    public function unlinkSocial(string $provider): bool
    {
        $userId = $this->id ?? null;
        if (!$userId) {
            return false;
        }

        \Nemesis\Core\Database::statement(
            "DELETE FROM social_accounts WHERE user_id = ? AND provider = ?",
            [(int) $userId, strtolower($provider)]
        );

        return true;
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Full social login flow: validate state, exchange code, find/create user.
     * Returns JWT token pair + user_id.
     *
     * @return array{access: string, refresh: string, expires_in: int, token_type: string, user_id: int}
     */
    public static function loginWithSocial(
        string $driver,
        string $code,
        string $state,
        string $expectedState = ''
    ): array {
        $socialUser = SocialAuth::handleCallback($driver, $code, $state, $expectedState);
        return SocialAuth::findOrCreateUser($socialUser);
    }

    /**
     * Get the OAuth redirect URL for a given driver.
     * Store getState() in session before redirecting.
     *
     * Example:
     *   [$url, $state] = User::socialRedirect('google');
     *   $_SESSION['oauth_state'] = $state;
     *   header("Location: $url");
     */
    public static function socialRedirect(string $driver, array $scopes = []): array
    {
        $provider = SocialAuth::driver($driver);
        $url      = $provider->getAuthorizationUrl($scopes);
        $state    = $provider->getState();
        return [$url, $state];
    }
}
