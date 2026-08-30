<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Gap 2 — upgraded to AES-256-GCM with HMAC-SHA256 (AEAD)
// BREAKING: legacy base64(ct::iv) payloads are no longer accepted.
// Encrypt any persisted data with the new format before upgrading.
// Updated: 2026-08-30

namespace Nemesis\Security;

class Crypt {
    /**
     * AEAD payload format version. Bump when changing the on-wire layout.
     */
    public const VERSION = 'v2';

    /**
     * AES-256-GCM authenticated encryption.
     * GCM provides confidentiality + integrity in one operation; the HMAC
     * provides an additional layer of defense in depth and explicit version
     * binding (so we can rotate keys without ciphertext confusion).
     */
    protected static string $cipher = 'aes-256-gcm';

    /**
     * 12-byte IV is the recommended size for GCM.
     */
    protected const IV_LENGTH = 12;

    /**
     * 16-byte GCM auth tag.
     */
    protected const TAG_LENGTH = 16;

    /**
     * 32-byte HMAC-SHA256 output.
     */
    protected const HMAC_LENGTH = 32;

    /**
     * The master key. Subkeys are derived from this via HKDF.
     */
    protected static ?string $key = null;

    public static function setKey(string $key): void
    {
        if (strlen($key) < 16) {
            throw new \InvalidArgumentException('Crypt::setKey() requires a key of at least 16 bytes.');
        }
        self::$key = $key;
    }

    public static function getKey(): ?string
    {
        return self::$key;
    }

    /**
     * Derive two 32-byte subkeys from the master key using HKDF-SHA256.
     * [0..31]   -> AES-256 key
     * [32..63]  -> HMAC-SHA256 key
     */
    protected static function deriveKeys(): array
    {
        if (self::$key === null) {
            throw new \RuntimeException('Crypt::setKey() must be called before encrypt/decrypt.');
        }
        $derived = hash_hkdf('sha256', self::$key, 64, 'nemesis-crypt-' . self::VERSION, '');
        return [
            'aes' => substr($derived, 0, 32),
            'hmac' => substr($derived, 32, 32),
        ];
    }

    /**
     * Encrypt a value with AES-256-GCM and produce an authenticated envelope.
     *
     * Output format: "v2:" . base64( iv || ciphertext || gcm_tag || hmac )
     *
     * The leading "v2:" version tag lets us rotate the format later and
     * cleanly reject legacy payloads.
     */
    public static function encrypt($value): string
    {
        $keys = self::deriveKeys();
        $iv = random_bytes(self::IV_LENGTH);

        $ciphertext = openssl_encrypt(
            (string) $value,
            self::$cipher,
            $keys['aes'],
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Crypt::encrypt() failed: ' . openssl_error_string());
        }

        // Defense-in-depth: explicit HMAC over (iv || ciphertext || tag).
        // GCM already authenticates, but the HMAC binds the version prefix
        // and lets us detect cross-version confusion attacks.
        $mac = hash_hmac('sha256', $iv . $ciphertext . $tag, $keys['hmac'], true);

        return self::VERSION . ':' . base64_encode($iv . $ciphertext . $tag . $mac);
    }

    /**
     * Decrypt and verify a v2 envelope. Throws on any tampering.
     */
    public static function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::VERSION . ':')) {
            throw new \RuntimeException(
                'Crypt::decrypt() only accepts ' . self::VERSION . ' envelopes. '
                . 'Legacy base64(ct::iv) payloads are not supported in v7.1.1+. '
                . 'Re-encrypt with Crypt::encrypt() before upgrading.'
            );
        }

        $raw = base64_decode(substr($payload, strlen(self::VERSION) + 1), true);
        if ($raw === false) {
            throw new \RuntimeException('Crypt::decrypt() received malformed base64.');
        }

        $expected = self::IV_LENGTH /*iv*/ + /*ct variable*/ 0 + self::TAG_LENGTH + self::HMAC_LENGTH;
        if (strlen($raw) < $expected) {
            throw new \RuntimeException('Crypt::decrypt() received truncated payload.');
        }

        $iv        = substr($raw, 0, self::IV_LENGTH);
        $tag       = substr($raw, -self::TAG_LENGTH - self::HMAC_LENGTH, self::TAG_LENGTH);
        $mac       = substr($raw, -self::HMAC_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH, strlen($raw) - self::IV_LENGTH - self::TAG_LENGTH - self::HMAC_LENGTH);

        $keys = self::deriveKeys();

        // Verify HMAC first (constant-time). This rejects tampered ciphertext
        // before we even attempt AES decryption.
        $expectedMac = hash_hmac('sha256', $iv . $ciphertext . $tag, $keys['hmac'], true);
        if (!hash_equals($expectedMac, $mac)) {
            throw new \RuntimeException('Crypt::decrypt() authentication failed (HMAC mismatch).');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $keys['aes'],
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Crypt::decrypt() AES-GCM verification failed.');
        }

        return $plaintext;
    }
}
