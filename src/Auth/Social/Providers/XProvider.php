<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: X (Twitter) OAuth2 provider | 2026-04-06

namespace Nemesis\Auth\Social\Providers;

use Nemesis\Auth\Social\AbstractSocialProvider;
use Nemesis\Auth\Social\SocialUser;

/**
 * X (Twitter) uses OAuth 2.0 with PKCE.
 * The authorization URL must include code_challenge + code_challenge_method.
 */
class XProvider extends AbstractSocialProvider
{
    private string $codeVerifier;
    private string $codeChallenge;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        parent::__construct($clientId, $clientSecret, $redirectUri);
        $this->codeVerifier  = $this->generateVerifier();
        $this->codeChallenge = $this->generateChallenge($this->codeVerifier);
    }

    protected function authorizationEndpoint(): string
    {
        return 'https://twitter.com/i/oauth2/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://api.twitter.com/2/oauth2/token';
    }

    protected function userEndpoint(): string
    {
        return 'https://api.twitter.com/2/users/me?user.fields=profile_image_url,name,username';
    }

    protected function defaultScopes(): array
    {
        return ['tweet.read', 'users.read', 'offline.access'];
    }

    protected function providerName(): string
    {
        return 'x';
    }

    public function getAuthorizationUrl(array $scopes = []): string
    {
        $scopes = array_unique(array_merge($this->defaultScopes(), $scopes));
        $params = [
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => implode(' ', $scopes),
            'state'                 => $this->state,
            'code_challenge'        => $this->codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        return $this->authorizationEndpoint() . '?' . http_build_query($params);
    }

    public function getAccessToken(string $code): array
    {
        $params = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'code_verifier' => $this->codeVerifier,
        ];
        return $this->post($this->tokenEndpoint(), $params);
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        $data = $raw['data'] ?? $raw;
        return new SocialUser(
            id:       (string) ($data['id']       ?? ''),
            name:     (string) ($data['name']     ?? $data['username'] ?? ''),
            email:    '',  // X API v2 does not expose email without special access
            avatar:   (string) ($data['profile_image_url'] ?? ''),
            token:    $token,
            provider: 'x',
            raw:      $raw,
        );
    }

    public function getCodeVerifier(): string { return $this->codeVerifier; }

    private function generateVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
