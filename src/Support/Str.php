<?php
declare(strict_types=1);

namespace Nemesis\Support;

class Str {
    public static function random($length = 16) {
        // ceil ensures odd lengths work; substr trims to exact requested length | Fixed: 2026-04-03
        $bytes = (int) ceil($length / 2);
        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }

    public static function password($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?';
        $str = '';
        for ($i = 0; $i < $length; $i++) $str .= $chars[rand(0, strlen($chars) - 1)];
        return $str;
    }

    public static function token($length = 60) {
        return self::random($length);
    }

    public static function generateRandomString($length = 60) {
        return substr(md5(microtime(true) . uniqid('', true)), 0, $length);
    }

    public static function generateRandomNumber($length = 10) {
        $str = '';
        for ($i = 0; $i < $length; $i++) $str .= mt_rand(0, 9);
        return $str;
    }

    public static function generateRandomNumberInRange($min, $max) {
        return rand($min, $max);
    }

    public static function rollDice() {
        return rand(1, 6);
    }

    // --- Added Enhancements ---

    public static function lower($value) { return mb_strtolower($value, 'UTF-8'); }
    public static function upper($value) { return mb_strtoupper($value, 'UTF-8'); }
    
    public static function length($value) { return mb_strlen($value); }
    public static function limit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) return $value;
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    public static function contains($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) return true;
        }
        return false;
    }

    public static function slug($title, $separator = '-') {
        $title = static::ascii($title);
        $title = preg_replace('!['.preg_quote($separator === '-' ? '_' : '-').']+!u', $separator, $title);
        $title = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', mb_strtolower($title));
        $title = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $title);
        return trim($title, $separator);
    }

    public static function snake($value, $delimiter = '_') {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $value));
        }
        return $value;
    }

    public static function camel($value) {
        return lcfirst(static::studly($value));
    }

    public static function studly($value) {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    public static function title($value) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function ascii($value) {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    }
    
    public static function uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
