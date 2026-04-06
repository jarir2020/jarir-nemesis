<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: base provider | 2026-04-06

namespace Nemesis\Auth\Social;

abstract class AbstractSocialProvider implements SocialProviderInterface
{
    protected string $state;

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $redirectUri,
    ) {
        $this->state = bin2hex(random_bytes(16));
    }

    // -------------------------------------------------------------------------
    // To implement per provider
    // -------------------------------------------------------------------------

    abstract protected function authorizationEndpoint(): string;
    abstract protected function tokenEndpoint(): string;
    abstract protected function userEndpoint(): string;
    abstract protected function defaultScopes(): array;
    abstract protected function providerName(): string;

    /** Map raw provider response to SocialUser. Override if field names differ. */
    protected function mapUser(array $raw, string $token): SocialUser
    {
        return SocialUser::fromArray($raw, $this->providerName(), $token);
    }

    // -------------------------------------------------------------------------
    // SocialProviderInterface
    // -------------------------------------------------------------------------

    public function getAuthorizationUrl(array $scopes = []): string
    {
        $scopes = array_unique(array_merge($this->defaultScopes(), $scopes));

        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes),
            'state'         => $this->state,
        ];

        return $this->authorizationEndpoint() . '?' . http_build_query($params);
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getAccessToken(string $code): array
    {
        $params = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ];

        return $this->post($this->tokenEndpoint(), $params, ['Accept: application/json']);
    }

    public function getUser(string $accessToken): SocialUser
    {
        $raw = $this->get($this->userEndpoint(), $accessToken);
        return $this->mapUser($raw, $accessToken);
    }

    // -------------------------------------------------------------------------
    // HTTP helpers (uses curl — no extra dep)
    // -------------------------------------------------------------------------

    protected function get(string $url, string $token, array $extraHeaders = []): array
    {
        $headers = array_merge(
            ["Authorization: Bearer {$token}", 'Accept: application/json'],
            $extraHeaders
        );
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'Nemesis-Framework/6.0',
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return (array) json_decode($body, true);
    }

    protected function post(string $url, array $params, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_USERAGENT      => 'Nemesis-Framework/6.0',
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return (array) json_decode($body, true);
    }
}
