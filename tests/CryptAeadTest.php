<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Tests for Gap 2 — Crypt AEAD (AES-256-GCM + HMAC)
// Updated: 2026-08-30

namespace Tests\Unit;

use Nemesis\Testing\TestCase;
use Nemesis\Security\Crypt;
use RuntimeException;

class CryptAeadTest extends TestCase
{
    public function setUp(): void
    {
        Crypt::setKey('a-32-character-master-key-1234');
    }

    public function test_round_trip(): void
    {
        $plain = 'The quick brown fox jumps over the lazy dog.';
        $ct    = Crypt::encrypt($plain);
        $this->assertNotSame($plain, $ct);
        $this->assertStringStartsWith('v2:', $ct);
        $this->assertSame($plain, Crypt::decrypt($ct));
    }

    public function test_round_trip_empty_string(): void
    {
        $ct = Crypt::encrypt('');
        $this->assertSame('', Crypt::decrypt($ct));
    }

    public function test_round_trip_unicode(): void
    {
        $plain = '日本語テスト 🔒 émojis';
        $ct    = Crypt::encrypt($plain);
        $this->assertSame($plain, Crypt::decrypt($ct));
    }

    public function test_two_encryptions_of_same_plaintext_differ(): void
    {
        // Random IV ensures nondeterminism.
        $a = Crypt::encrypt('same');
        $b = Crypt::encrypt('same');
        $this->assertNotSame($a, $b);
    }

    public function test_legacy_payloads_are_rejected(): void
    {
        // v7.1.1 dropped legacy support. A base64(ct::iv) payload must
        // raise a clear error rather than silently produce garbage.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/legacy|base64\(ct::iv\)/i');
        Crypt::decrypt(base64_encode('legacyciphertext::123456789012'));
    }

    public function test_tampered_ciphertext_is_rejected(): void
    {
        $ct    = Crypt::encrypt('secret');
        $raw   = base64_decode(substr($ct, 3), true);
        // Flip a byte in the ciphertext region.
        $raw[20] = chr(ord($raw[20]) ^ 0x01);
        $bad    = 'v2:' . base64_encode($raw);

        $this->expectException(RuntimeException::class);
        Crypt::decrypt($bad);
    }

    public function test_wrong_key_fails(): void
    {
        $ct = Crypt::encrypt('hello');
        Crypt::setKey('a-different-32-character-key!!!');
        $this->expectException(RuntimeException::class);
        Crypt::decrypt($ct);
    }

    public function test_short_key_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Crypt::setKey('too-short');
    }

    public function test_get_key_returns_set_value(): void
    {
        Crypt::setKey('another-32-character-key-here!!');
        $this->assertSame('another-32-character-key-here!!', Crypt::getKey());
    }
}
