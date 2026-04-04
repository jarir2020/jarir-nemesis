<?php
declare(strict_types=1);

namespace Nemesis\Support;

class UserAgent {
    public static function ip() {
        return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function userAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public static function os() {
        $userAgent = self::userAgent();
        $osArray = [
            'Windows' => 'Win', 'Mac' => 'Macintosh', 'Linux' => 'Linux',
            'Android' => 'Android', 'iOS' => 'iPhone|iPad'
        ];
        foreach ($osArray as $os => $regex) {
            if (preg_match("/$regex/i", $userAgent)) return $os;
        }
        return 'Unknown OS';
    }

    public static function browser() {
        $userAgent = self::userAgent();
        $browserArray = [
            'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari',
            'Edge' => 'Edge', 'IE' => 'MSIE|Trident'
        ];
        foreach ($browserArray as $browser => $regex) {
            if (preg_match("/$regex/i", $userAgent)) return $browser;
        }
        return 'Unknown Browser';
    }

    public static function geo($ip = null) {
        $ip = $ip ?: self::ip();
        if ($ip === '127.0.0.1') return ['country' => 'Localhost', 'city' => 'Localhost'];
        
        $json = @file_get_contents("http://ip-api.com/json/{$ip}");
        return $json ? json_decode($json, true) : [];
    }
    
    // --- Final Parity Additions ---
    
    public static function language() { return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en'; }
    public static function referer() { return $_SERVER['HTTP_REFERER'] ?? null; }
    public static function method() { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
    public static function host() { return $_SERVER['HTTP_HOST'] ?? 'localhost'; }
    public static function protocol() { return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'HTTPS' : 'HTTP'; }
    public static function uri() { return $_SERVER['REQUEST_URI'] ?? '/'; }
    public static function time() { return $_SERVER['REQUEST_TIME'] ?? time(); }
    public static function all() {
        return [
            'ip' => self::ip(),
            'ua' => self::userAgent(),
            'os' => self::os(),
            'browser' => self::browser(),
            'lang' => self::language(),
            'geo' => self::geo()
        ];
    }
}
