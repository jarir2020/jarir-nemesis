<?php

namespace JarirAhmed\HashHelper;
use ChristianRiesen\Base32;

class HashHelper
{
    /**
     * Encode a string in binary format.
     *
     * @param string $input The string to encode.
     * @return string The binary encoded string.
     */
    public function toBinary(string $input): string
    {
        return implode(' ', array_map('decbin', array_map('ord', str_split($input))));
    }

    /**
     * Encode a string in octal format.
     *
     * @param string $input The string to encode.
     * @return string The octal encoded string.
     */
    public function toOctal(string $input): string
    {
        return implode(' ', array_map('decoct', array_map('ord', str_split($input))));
    }

    /**
     * Encode a string in decimal format.
     *
     * @param string $input The string to encode.
     * @return string The decimal encoded string.
     */
    public function toDecimal(string $input): string
    {
        return implode(' ', array_map('ord', str_split($input)));
    }

    /**
     * Encode a string in hexadecimal format.
     *
     * @param string $input The string to encode.
     * @return string The hexadecimal encoded string.
     */
    public function toHexadecimal(string $input): string
    {
        return bin2hex($input);
    }

    /**
     * Encode a string in base32 format.
     *
     * @param string $input The string to encode.
     * @return string The base32 encoded string.
     */
    public function toBase32(string $input): string
    {
        return Base32::encode($input);
    }

    /**
     * Encode a string in base64 format.
     *
     * @param string $input The string to encode.
     * @return string The base64 encoded string.
     */
    public function toBase64(string $input): string
    {
        return base64_encode($input);
    }

    /**
     * Encode a string in a custom Base-N format.
     *
     * @param string $input The string to encode.
     * @param int $base The base to encode (from 2 to 62).
     * @return string The Base-N encoded string.
     */
    public function toBaseN(string $input, int $base = 62): string
    {
        if ($base < 2 || $base > 62) {
            throw new \InvalidArgumentException("Base-N encoding supports bases between 2 and 62.");
        }

        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        $number = intval(bin2hex($input), 16);

        while ($number > 0) {
            $result = $chars[$number % $base] . $result;
            $number = intdiv($number, $base);
        }

        return $result;
    }

    /**
     * Hash a string using MD4.
     *
     * @param string $input The string to hash.
     * @return string The MD4 hash.
     */
    public function toMd4(string $input): string
    {
        return hash('md4', $input);
    }

    /**
     * Hash a string using MD5.
     *
     * @param string $input The string to hash.
     * @return string The MD5 hash.
     */
    public function toMd5(string $input): string
    {
        return md5($input);
    }

    /**
     * Hash a string using SHA-1.
     *
     * @param string $input The string to hash.
     * @return string The SHA-1 hash.
     */
    public function toSha1(string $input): string
    {
        return sha1($input);
    }

    /**
     * Hash a string using SHA-224.
     *
     * @param string $input The string to hash.
     * @return string The SHA-224 hash.
     */
    public function toSha224(string $input): string
    {
        return hash('sha224', $input);
    }

    /**
     * Hash a string using SHA-256.
     *
     * @param string $input The string to hash.
     * @return string The SHA-256 hash.
     */
    public function toSha256(string $input): string
    {
        return hash('sha256', $input);
    }

    /**
     * Hash a string using SHA-384.
     *
     * @param string $input The string to hash.
     * @return string The SHA-384 hash.
     */
    public function toSha384(string $input): string
    {
        return hash('sha384', $input);
    }

    /**
     * Hash a string using SHA-512.
     *
     * @param string $input The string to hash.
     * @return string The SHA-512 hash.
     */
    public function toSha512(string $input): string
    {
        return hash('sha512', $input);
    }

    /**
     * Hash a string using NTLM.
     *
     * @param string $input The string to hash.
     * @return string The NTLM hash.
     */
    public function toNTLM(string $input): string
    {
        $input = iconv('UTF-8', 'UTF-16LE', $input);
        return bin2hex(hash('md4', $input, true));
    }

    /**
     * Hash a string using LANMAN.
     *
     * @param string $input The string to hash.
     * @return string The LANMAN hash.
     */
    public function toLANMAN(string $input): string
    {
        $input = strtoupper(substr($input, 0, 14));
        $input = str_pad($input, 14, "\0", STR_PAD_RIGHT);
        $key = array_map(function ($char) {
            return ord($char) << 1;
        }, str_split($input));

        $DES1 = openssl_encrypt(implode(array_map('chr', array_slice($key, 0, 7))), 'DES-ECB', "\0", OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        $DES2 = openssl_encrypt(implode(array_map('chr', array_slice($key, 7, 7))), 'DES-ECB', "\0", OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

        return strtoupper(bin2hex($DES1 . $DES2));
    }

    /**
     * Hash a string using BCrypt.
     *
     * @param string $input The string to hash.
     * @return string The BCrypt hash.
     */
    public function toBcrypt(string $input): string
    {
        return password_hash($input, PASSWORD_BCRYPT);
    }

    /**
     * Hash a string using MD6.
     *
     * @param string $input The string to hash.
     * @return string The MD6 hash.
     */
    public function toMd6(string $input): string
    {
        return hash('md6', $input);
    }

    public function hmac(string $data, string $key, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $data, $key);
    }

    public function ripemd160(string $data): string
    {
        return hash('ripemd160', $data);
    }

    public function whirlpool(string $data): string
    {
        return hash('whirlpool', $data);
    }

    public function blake2b(string $data): string
    {
        return hash('blake2b', $data);
    }

    public function scrypt(string $password, string $salt, int $cost = 16384, int $blockSize = 8, int $parallelization = 1, int $length = 64): string
    {
        return hash('scrypt', $password . $salt, false, ['cost' => $cost, 'blockSize' => $blockSize, 'parallelization' => $parallelization, 'length' => $length]);
    }

    public function encryptAES(string $data, string $key): string
    {
        $cipher = "AES-128-CTR";
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        return base64_encode($iv . openssl_encrypt($data, $cipher, $key, 0, $iv));
    }

    public function encryptRSA(string $data, string $publicKey): string
    {
        openssl_public_encrypt($data, $encrypted, $publicKey);
        return base64_encode($encrypted);
    }

 public function toBase58(string $input): string
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    $num = gmp_import($input);
    $output = '';


    while ($num > 0) {
        $num = gmp_div_q($num, $base);
        $remainder = gmp_mod($num, $base);
        $output = $alphabet[$remainder] . $output;
    }

  
    foreach (str_split($input) as $char) {
        if ($char === "\0") {
            $output = $alphabet[0] . $output;
        } else {
            break;
        }
    }

    return $output;
}

public function fromBase58(string $input): string
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    $num = gmp_init(0);
    foreach (str_split($input) as $char) {
        $index = strpos($alphabet, $char);
        if ($index === false) {
            throw new \InvalidArgumentException("Invalid Base58 character: $char");
        }
        $num = gmp_add(gmp_mul($num, $base), $index);
    }


    $padding = str_repeat("\0", strlen($input) - strlen(ltrim($input, '1')));
    return gmp_export(gmp_export($num)) . $padding;
}

public function toBase91(string $input): string
{
    $base91 = '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]_^`abcdefghijklmnopqrstuvwxyz{|}~';
    $output = '';
    $len = strlen($input);
    $value = 0;
    $bits = 0;

    for ($i = 0; $i < $len; $i++) {
        $value += ord($input[$i]) << $bits;
        $bits += 8;

        while ($bits > 13) {
            $bits -= 13;
            $output .= $base91[$value % 91];
            $value = intval($value / 91);
        }
    }

    if ($bits > 0) {
        $output .= $base91[$value % 91];
    }

    return $output;
}

public function fromBase91(string $input): string
{
    $base91 = '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]_^`abcdefghijklmnopqrstuvwxyz{|}~';
    $output = '';
    $len = strlen($input);
    $value = 0;
    $bits = 0;

    for ($i = 0; $i < $len; $i++) {
        $value += strpos($base91, $input[$i]) << $bits;
        $bits += 13;

        while ($bits > 8) {
            $bits -= 8;
            $output .= chr($value & 0xFF);
            $value >>= 8;
        }
    }

    return $output;
}

public function tiger_hash(string $input): string
{
    return hash('tiger192,3', $input); 
}

public function skein_hash(string $input): string
{
    return hash('skein512', $input); 
}

private const BASE85_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!#$%&()*+-;<=>?@^_`{|}~';

public function base85_encode(string $input): string
{
    $encoded = '';
    $length = strlen($input);
    $chunks = ceil($length / 4);

    for ($i = 0; $i < $chunks; $i++) {
        $chunk = substr($input, $i * 4, 4);
        $intValue = 0;

        for ($j = 0; $j < strlen($chunk); $j++) {
            $intValue = ($intValue << 8) + ord($chunk[$j]);
        }

        for ($j = 0; $j < 5; $j++) {
            $encoded .= self::BASE85_ALPHABET[$intValue % 85];
            $intValue = (int)($intValue / 85);
        }
    }

    return $encoded;
}

public function base85_decode(string $input): string
{
    $decoded = '';
    $length = strlen($input);
    $chunks = ceil($length / 5);

    for ($i = 0; $i < $chunks; $i++) {
        $chunk = substr($input, $i * 5, 5);
        $intValue = 0;

        for ($j = 0; $j < strlen($chunk); $j++) {
            $intValue = $intValue * 85 + strpos(self::BASE85_ALPHABET, $chunk[$j]);
        }

        for ($j = 0; $j < 4; $j++) {
            $decoded .= chr(($intValue >> (24 - ($j * 8))) & 0xFF);
        }
    }

    return rtrim($decoded, "\0");
}

public function ascii85_encode(string $input): string
{
    return $this->base85_encode($input); 
}

public function ascii85_decode(string $input): string
{
    return $this->base85_decode($input); 
}


public function url_encode(string $input): string
{
    return rawurlencode($input);
}

public function url_decode(string $input): string
{
    return rawurldecode($input);
}


public function q_encode(string $input): string
{
    return quoted_printable_encode($input);
}

public function q_decode(string $input): string
{
    return quoted_printable_decode($input);
}


public function xor_encode(string $input, string $key): string
{
    $output = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $output .= $input[$i] ^ $key[$i % strlen($key)];
    }
    return $output;
}

public function xor_decode(string $input, string $key): string
{
    return $this->xor_encode($input, $key); // XOR is symmetric
}

}
