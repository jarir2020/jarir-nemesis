<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: LinkedIn provider | 2026-04-06

namespace Nemesis\Auth\Social\Providers;

use Nemesis\Auth\Social\AbstractSocialProvider;
use Nemesis\Auth\Social\SocialUser;

class LinkedInProvider extends AbstractSocialProvider
{
    protected function authorizationEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    protected function userEndpoint(): string
    {
        return 'https://api.linkedin.com/v2/userinfo';
    }

    protected function defaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    protected function providerName(): string
    {
        return 'linkedin';
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        return new SocialUser(
            id:       (string) ($raw['sub']     ?? ''),
            name:     (string) ($raw['name']    ?? trim(($raw['given_name'] ?? '') . ' ' . ($raw['family_name'] ?? ''))),
            email:    (string) ($raw['email']   ?? ''),
            avatar:   (string) ($raw['picture'] ?? ''),
            token:    $token,
            provider: 'linkedin',
            raw:      $raw,
        );
    }
}
