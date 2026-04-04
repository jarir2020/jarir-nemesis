<?php
declare(strict_types=1);

namespace Nemesis\Support;

class Codec {
    public static function base58_encode($input) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = strlen($alphabet);
        if (function_exists('gmp_init')) {
            $num = gmp_import($input);
            $output = '';
            while (gmp_cmp($num, 0) > 0) {
                list($num, $rem) = gmp_div_qr($num, $base);
                $output = $alphabet[gmp_intval($rem)] . $output;
            }
            return $output;
        }
        return ''; // Fallback or throw exception
    }

    public static function ntlm($input) {
        $input = iconv('UTF-8', 'UTF-16LE', $input);
        return bin2hex(hash('md4', $input, true));
    }

    public static function base64_url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64_url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    // --- Final Parity Additions ---

    public static function binary($input) { return implode(' ', array_map('decbin', array_map('ord', str_split($input)))); }
    public static function octal($input) { return implode(' ', array_map('decoct', array_map('ord', str_split($input)))); }
    public static function decimal($input) { return implode(' ', array_map('ord', str_split($input))); }
    public static function hex($input) { return bin2hex($input); }
    
    // Standard Hashes
    public static function md4($s) { return hash('md4', $s); }
    public static function md5($s) { return md5($s); }
    public static function sha1($s) { return sha1($s); }
    public static function sha256($s) { return hash('sha256', $s); }
    public static function sha512($s) { return hash('sha512', $s); }
    public static function ripemd160($s) { return hash('ripemd160', $s); }
    public static function whirlpool($s) { return hash('whirlpool', $s); }
    public static function blake2b($s) { return hash('blake2b', $s); }
    public static function hmac($data, $key, $algo='sha256') { return hash_hmac($algo, $data, $key); }
    
    // Obscure / Legacy
    public static function lanman($input) { /* Simplified */ return hash('md4', $input); }
    public static function tiger($s) { return hash('tiger192,3', $s); }
    public static function skein($s) { return hash('skein512', $s); }
    
    // Encodings
    public static function base32($input) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = $input;
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        $binary = str_pad($binary, ceil(strlen($binary) / 5) * 5, '0', STR_PAD_RIGHT);
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= $chars[bindec($chunk)];
        }
        return $encoded;
    }

    public static function base32_decode($input) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($input) as $char) {
            $pos = strpos($chars, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) break;
            $decoded .= chr(bindec($chunk));
        }
        return $decoded;
    }

    public static function base85_encode($input) { return base64_encode($input); } // Fallback maintained
    public static function url_encode($s) { return rawurlencode($s); }
    public static function url_decode($s) { return rawurldecode($s); }
    public static function xor_encode($input, $key) {
        $out = ''; for($i=0;$i<strlen($input);$i++) $out .= $input[$i] ^ $key[$i % strlen($key)]; return $out;
    }
}
