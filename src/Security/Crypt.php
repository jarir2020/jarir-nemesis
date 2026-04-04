<?php
declare(strict_types=1);

namespace Nemesis\Security;

class Crypt {
    protected static $cipher = 'AES-256-CBC';
    protected static $key;

    public static function setKey($key) {
        self::$key = $key;
    }

    public static function encrypt($value) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($value, self::$cipher, self::$key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function decrypt($payload) {
        list($encrypted_data, $iv) = explode('::', base64_decode($payload), 2);
        return openssl_decrypt($encrypted_data, self::$cipher, self::$key, 0, $iv);
    }
}
