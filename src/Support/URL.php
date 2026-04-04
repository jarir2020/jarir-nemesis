<?php
declare(strict_types=1);

namespace Nemesis\Support;

class URL {
    public static function sign($url, $expiration = null) {
        $appKey = getenv('APP_KEY') ?: 'secret';
        $expires = $expiration ? time() + $expiration : null;
        
        $payload = $url . ($expires ? "?expires=$expires" : "");
        $signature = hash_hmac('sha256', $payload, $appKey);
        
        return $url . (str_contains($url, '?') ? '&' : '?') . 
               ($expires ? "expires=$expires&" : "") . 
               "signature=$signature";
    }

    public static function verifySign($requestUrl) {
        $appKey = getenv('APP_KEY') ?: 'secret';
        $parts = parse_url($requestUrl);
        parse_str($parts['query'] ?? '', $query);
        
        if (!isset($query['signature'])) return false;
        
        $signature = $query['signature'];
        unset($query['signature']);
        
        // Rebuild base URL with original query
        $baseUrl = $parts['scheme'] . "://" . $parts['host'] . ($parts['port'] ?? '') . $parts['path'];
        $newQuery = http_build_query($query);
        $payload = $baseUrl . ($newQuery ? "?$newQuery" : "");
        
        // Check expiration
        if (isset($query['expires']) && time() > $query['expires']) return false;
        
        $expected = hash_hmac('sha256', $payload, $appKey);
        return hash_equals($expected, $signature);
    }
}
