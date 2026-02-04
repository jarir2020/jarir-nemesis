<?php

namespace Nemesis\Auth;

class JWT {
    protected static $secret;
    protected static $algorithm = 'HS256';

    public static function setSecret($secret) {
        self::$secret = $secret;
    }

    public static function encode($payload, $expiresIn = 3600) {
        if (!self::$secret) {
            throw new \Exception("JWT secret not set. Call JWT::setSecret() first.");
        }

        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;

        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    public static function decode($token) {
        if (!self::$secret) {
            throw new \Exception("JWT secret not set. Call JWT::setSecret() first.");
        }

        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new \Exception("Invalid JWT token format");
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        // Verify signature
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, self::$secret, true);
        $base64UrlSignatureCheck = self::base64UrlEncode($signature);

        if ($base64UrlSignature !== $base64UrlSignatureCheck) {
            throw new \Exception("Invalid JWT signature");
        }

        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception("JWT token has expired");
        }

        return $payload;
    }

    public static function verify($token) {
        try {
            self::decode($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
