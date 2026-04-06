<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: provider contract | 2026-04-06

namespace Nemesis\Auth\Social;

interface SocialProviderInterface
{
    /** OAuth2 authorization URL to redirect the user to. */
    public function getAuthorizationUrl(array $scopes = []): string;

    /** State token stored in session for CSRF protection. */
    public function getState(): string;

    /** Exchange authorization code for access token. */
    public function getAccessToken(string $code): array;

    /** Fetch the authenticated user's profile from the provider. */
    public function getUser(string $accessToken): SocialUser;
}
