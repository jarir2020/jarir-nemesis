<?php
declare(strict_types=1);

namespace Nemesis\Auth;

class OAuth2Provider {
    protected $config;
    protected $providers = [];

    public function __construct($config = []) {
        $this->config = $config;
    }

    public function registerProvider($name, $clientId, $clientSecret, $redirectUri, $endpoints) {
        $this->providers[$name] = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'endpoints' => $endpoints
        ];
    }

    public function getAuthorizationUrl($provider, $state = null, $scopes = []) {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Provider $provider not registered");
        }

        $config = $this->providers[$provider];
        $state = $state ?? bin2hex(random_bytes(16));

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'state' => $state,
            'scope' => implode(' ', $scopes)
        ];

        $_SESSION['oauth2_state'] = $state;

        return $config['endpoints']['authorization'] . '?' . http_build_query($params);
    }

    public function getAccessToken($provider, $code) {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Provider $provider not registered");
        }

        $config = $this->providers[$provider];

        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($config['endpoints']['token']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getUserInfo($provider, $accessToken) {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Provider $provider not registered");
        }

        $config = $this->providers[$provider];

        $ch = curl_init($config['endpoints']['user_info']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    // Preset configurations for popular providers
    public static function google($clientId, $clientSecret, $redirectUri) {
        $provider = new self();
        $provider->registerProvider('google', $clientId, $clientSecret, $redirectUri, [
            'authorization' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'user_info' => 'https://www.googleapis.com/oauth2/v2/userinfo'
        ]);
        return $provider;
    }

    public static function github($clientId, $clientSecret, $redirectUri) {
        $provider = new self();
        $provider->registerProvider('github', $clientId, $clientSecret, $redirectUri, [
            'authorization' => 'https://github.com/login/oauth/authorize',
            'token' => 'https://github.com/login/oauth/access_token',
            'user_info' => 'https://api.github.com/user'
        ]);
        return $provider;
    }
}
