<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: Facebook provider | 2026-04-06

namespace Nemesis\Auth\Social\Providers;

use Nemesis\Auth\Social\AbstractSocialProvider;
use Nemesis\Auth\Social\SocialUser;

class FacebookProvider extends AbstractSocialProvider
{
    private const GRAPH_VERSION = 'v19.0';

    protected function authorizationEndpoint(): string
    {
        return 'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/oauth/access_token';
    }

    protected function userEndpoint(): string
    {
        return 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/me?fields=id,name,email,picture';
    }

    protected function defaultScopes(): array
    {
        return ['email', 'public_profile'];
    }

    protected function providerName(): string
    {
        return 'facebook';
    }

    public function getUser(string $accessToken): SocialUser
    {
        // Facebook requires token as query param, not Bearer header
        $url = $this->userEndpoint() . '&access_token=' . urlencode($accessToken);
        $raw = $this->get($url, ''); // empty token — already in URL
        return $this->mapUser($raw, $accessToken);
    }

    protected function get(string $url, string $token, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Nemesis-Framework/6.0',
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return (array) json_decode($body, true);
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        return new SocialUser(
            id:       (string) ($raw['id']    ?? ''),
            name:     (string) ($raw['name']  ?? ''),
            email:    (string) ($raw['email'] ?? ''),
            avatar:   (string) ($raw['picture']['data']['url'] ?? ''),
            token:    $token,
            provider: 'facebook',
            raw:      $raw,
        );
    }
}
