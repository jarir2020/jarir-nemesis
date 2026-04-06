<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: GitHub provider | 2026-04-06

namespace Nemesis\Auth\Social\Providers;

use Nemesis\Auth\Social\AbstractSocialProvider;
use Nemesis\Auth\Social\SocialUser;

class GitHubProvider extends AbstractSocialProvider
{
    protected function authorizationEndpoint(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function userEndpoint(): string
    {
        return 'https://api.github.com/user';
    }

    protected function defaultScopes(): array
    {
        return ['read:user', 'user:email'];
    }

    protected function providerName(): string
    {
        return 'github';
    }

    /**
     * GitHub may return email as null if user hides it.
     * Fetch from /user/emails endpoint in that case.
     */
    protected function mapUser(array $raw, string $token): SocialUser
    {
        $email = $raw['email'] ?? '';

        if (!$email) {
            $emails = $this->get('https://api.github.com/user/emails', $token);
            foreach ($emails as $e) {
                if (!empty($e['primary']) && !empty($e['verified'])) {
                    $email = $e['email'];
                    break;
                }
            }
        }

        $raw['email'] = $email;
        return SocialUser::fromArray($raw, $this->providerName(), $token);
    }
}
