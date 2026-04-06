<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: Google provider | 2026-04-06

namespace Nemesis\Auth\Social\Providers;

use Nemesis\Auth\Social\AbstractSocialProvider;

class GoogleProvider extends AbstractSocialProvider
{
    protected function authorizationEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function userEndpoint(): string
    {
        return 'https://www.googleapis.com/oauth2/v2/userinfo';
    }

    protected function defaultScopes(): array
    {
        return ['openid', 'email', 'profile'];
    }

    protected function providerName(): string
    {
        return 'google';
    }
}
