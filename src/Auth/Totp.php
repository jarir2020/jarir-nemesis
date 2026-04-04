<?php
declare(strict_types=1);

namespace Nemesis\Auth;

class Totp {
    protected $secret;
    protected $issuer;
    protected $label;

    public function __construct($secret = null, $issuer = null, $label = null) {
        $this->secret = $secret ?? $this->generateSecret();
        $this->issuer = $issuer ?? 'Nemesis App';
        $this->label = $label ?? 'User';
    }

    public function generateSecret($length = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        return $secret;
    }

    public function getSecret() {
        return $this->secret;
    }

    public function getProvisioningUri() {
        $label = rawurlencode($this->issuer . ':' . $this->label);
        $issuer = rawurlencode($this->issuer);
        return "otpauth://totp/{$label}?secret={$this->secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function now() {
        return $this->generateCode(floor(time() / 30));
    }

    public function verify($code, $window = 1) {
        $timestamp = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            if ($this->generateCode($timestamp + $i) === (string)$code) {
                return true;
            }
        }
        return false;
    }

    protected function generateCode($timestamp) {
        $packedTimestamp = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $packedTimestamp, $this->base32Decode($this->secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $code = (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;

        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    protected function base32Decode($secret) {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $output = '';
        $buffer = 0;
        $bufferSize = 0;
        
        $secret = strtoupper($secret);
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if (!isset($base32charsFlipped[$char])) {
                continue;
            }
            $buffer = ($buffer << 5) | $base32charsFlipped[$char];
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $output .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }
        return $output;
    }
}
