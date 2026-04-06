<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — Auth Suite Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Auth\JWT;
use Nemesis\Auth\Totp;
use Nemesis\Auth\ApiKey;
use Nemesis\Auth\AuthManager;
use Nemesis\Core\Database;

// Bootstrap in-memory SQLite for all auth tests
function phase16Bootstrap(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    putenv('JWT_SECRET=nemesis-phase16-test-secret-key-32ch');
    putenv('JWT_TTL=900');
    putenv('JWT_REFRESH_TTL=604800');
    putenv('APP_NAME=NemesisTest');

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    Database::setPdo($pdo);

    // Seed a users table for AuthManager tests
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT, email TEXT UNIQUE,
        password TEXT, role TEXT DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("INSERT INTO users (name,email,password,role) VALUES
        ('Alice','alice@test.com','" . password_hash('secret123', PASSWORD_BCRYPT) . "','admin'),
        ('Bob','bob@test.com','"   . password_hash('pass456',   PASSWORD_BCRYPT) . "','user')");
}

// =============================================================================
// JWT — Encode / Decode
// =============================================================================

class JwtEncodeDecodeTest extends TestCase
{
    public function setUp(): void { phase16Bootstrap(); }

    public function testEncodeAndDecode(): void
    {
        $token   = JWT::encode(['sub' => 1, 'role' => 'admin']);
        $payload = JWT::decode($token);

        $this->assertEquals(1,       $payload['sub']);
        $this->assertEquals('admin', $payload['role']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function testExpiredTokenThrows(): void
    {
        $token = JWT::encode(['sub' => 1], -1); // already expired
        $this->expectException(\RuntimeException::class);
        JWT::decode($token);
    }

    public function testTamperedSignatureThrows(): void
    {
        $token  = JWT::encode(['sub' => 1]);
        $parts  = explode('.', $token);
        $parts[2] = 'invalidsignature';
        $this->expectException(\RuntimeException::class);
        JWT::decode(implode('.', $parts));
    }

    public function testVerifyReturnNullOnFailure(): void
    {
        $result = JWT::verify('not.a.valid.jwt');
        $this->assertNull($result);
    }

    public function testVerifyReturnPayloadOnSuccess(): void
    {
        $token   = JWT::encode(['sub' => 99]);
        $payload = JWT::verify($token);
        $this->assertIsArray($payload);
        $this->assertEquals(99, $payload['sub']);
    }
}

// =============================================================================
// JWT — Token Pair (access + refresh)
// =============================================================================

class JwtTokenPairTest extends TestCase
{
    public function setUp(): void { phase16Bootstrap(); }

    public function testIssueTokenPair(): void
    {
        $pair = JWT::issueTokenPair(['sub' => 1, 'email' => 'alice@test.com']);

        $this->assertArrayHasKey('access',     $pair);
        $this->assertArrayHasKey('refresh',    $pair);
        $this->assertArrayHasKey('expires_in', $pair);
        $this->assertEquals('Bearer', $pair['token_type']);
        $this->assertNotEmpty($pair['access']);
        $this->assertNotEmpty($pair['refresh']);
    }

    public function testRefreshTokenPair(): void
    {
        $pair    = JWT::issueTokenPair(['sub' => 1]);
        $newPair = JWT::refreshTokenPair($pair['refresh'], ['sub' => 1, 'email' => 'x@x.com']);

        $this->assertArrayHasKey('access',  $newPair);
        $this->assertArrayHasKey('refresh', $newPair);
        // New tokens should be different
        $this->assertNotEquals($pair['access'],  $newPair['access']);
        $this->assertNotEquals($pair['refresh'], $newPair['refresh']);
    }

    public function testRefreshWithInvalidTokenThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        JWT::refreshTokenPair('totally-fake-refresh-token', ['sub' => 1]);
    }

    public function testRevokeBlacklistsToken(): void
    {
        $token = JWT::encode(['sub' => 1]);
        JWT::revoke($token, false);

        $payload = JWT::verify($token);
        $this->assertNull($payload);
    }

    public function testPurgeBlacklist(): void
    {
        $purged = JWT::purgeBlacklist();
        $this->assertIsInt($purged);
    }
}

// =============================================================================
// TOTP — Code generation & verification
// =============================================================================

class TotpTest extends TestCase
{
    public function setUp(): void { phase16Bootstrap(); }

    public function testGenerateSecret(): void
    {
        $totp   = new Totp();
        $secret = $totp->getSecret();
        $this->assertEquals(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testProvisioningUri(): void
    {
        $totp = new Totp('JBSWY3DPEHPK3PXP', 'MyApp', 'user@test.com');
        $uri  = $totp->getProvisioningUri();
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=MyApp', $uri);
    }

    public function testCurrentCodeVerifies(): void
    {
        $totp = new Totp();
        $code = $totp->now();
        $this->assertTrue($totp->verify($code));
    }

    public function testWrongCodeFails(): void
    {
        $totp = new Totp();
        $this->assertFalse($totp->verify('000000'));
    }

    public function testCodeIs6Digits(): void
    {
        $totp = new Totp();
        $code = $totp->now();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }
}

// =============================================================================
// TOTP — Backup codes
// =============================================================================

class TotpBackupCodesTest extends TestCase
{
    public function setUp(): void { phase16Bootstrap(); }

    public function testGenerateBackupCodes(): void
    {
        $codes = Totp::generateBackupCodes(42);
        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9A-F]{4}-[0-9A-F]{4}$/', $code);
        }
    }

    public function testVerifyBackupCode(): void
    {
        $codes  = Totp::generateBackupCodes(43);
        $result = Totp::verifyBackupCode(43, $codes[0]);
        $this->assertTrue($result);
    }

    public function testBackupCodeIsOneTimeUse(): void
    {
        $codes = Totp::generateBackupCodes(44);
        Totp::verifyBackupCode(44, $codes[0]);
        // Second use must fail
        $this->assertFalse(Totp::verifyBackupCode(44, $codes[0]));
    }

    public function testRemainingBackupCodes(): void
    {
        Totp::generateBackupCodes(45);
        $this->assertEquals(8, Totp::remainingBackupCodes(45));

        Totp::verifyBackupCode(45, Totp::generateBackupCodes(45)[0]);
        // After using one from a fresh set of 8
        $this->assertEquals(7, Totp::remainingBackupCodes(45));
    }

    public function testWrongBackupCodeFails(): void
    {
        Totp::generateBackupCodes(46);
        $this->assertFalse(Totp::verifyBackupCode(46, 'DEAD-BEEF'));
    }
}

// =============================================================================
// ApiKey — create / verify / revoke / rotate
// =============================================================================

class ApiKeyTest extends TestCase
{
    public function setUp(): void { phase16Bootstrap(); }

    public function testCreateReturnsRawKey(): void
    {
        $result = ApiKey::create(1, ['read'], 'Test key');
        $this->assertArrayHasKey('key',    $result);
        $this->assertArrayHasKey('id',     $result);
        $this->assertArrayHasKey('prefix', $result);
        $this->assertStringStartsWith('nms_', $result['key']);
    }

    public function testVerifyValidKey(): void
    {
        $result = ApiKey::create(1, ['read', 'write'], 'Verify key');
        $row    = ApiKey::verify($result['key']);
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['user_id']);
        $this->assertContains('read',  $row['scopes']);
        $this->assertContains('write', $row['scopes']);
    }

    public function testVerifyInvalidKeyReturnsNull(): void
    {
        $this->assertNull(ApiKey::verify('nms_fakekeyxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'));
    }

    public function testHasScope(): void
    {
        $result = ApiKey::create(1, ['read'], 'Scope key');
        $row    = ApiKey::verify($result['key']);
        $this->assertTrue(ApiKey::hasScope($row,  'read'));
        $this->assertFalse(ApiKey::hasScope($row, 'write'));
    }

    public function testWildcardScopePassesAll(): void
    {
        $result = ApiKey::create(1, ['*'], 'Wildcard key');
        $row    = ApiKey::verify($result['key']);
        $this->assertTrue(ApiKey::hasScope($row, 'read'));
        $this->assertTrue(ApiKey::hasScope($row, 'delete'));
    }

    public function testRevokeKey(): void
    {
        $result = ApiKey::create(1, ['*'], 'Revoke key');
        ApiKey::revoke($result['id']);
        $this->assertNull(ApiKey::verify($result['key']));
    }

    public function testRotateKey(): void
    {
        $original = ApiKey::create(1, ['read'], 'Rotate key');
        $new      = ApiKey::rotate($original['id']);
        // Old key no longer works
        $this->assertNull(ApiKey::verify($original['key']));
        // New key works
        $this->assertIsArray(ApiKey::verify($new['key']));
    }

    public function testForUser(): void
    {
        ApiKey::create(99, ['read'], 'User key 1');
        ApiKey::create(99, ['write'], 'User key 2');
        $keys = ApiKey::forUser(99);
        $this->assertGreaterThanOrEqual(2, count($keys));
    }

    public function testRevokeAll(): void
    {
        ApiKey::create(88, ['*'], 'Key A');
        ApiKey::create(88, ['*'], 'Key B');
        $revoked = ApiKey::revokeAll(88);
        $this->assertGreaterThanOrEqual(2, $revoked);
    }

    public function testExpiredKeyReturnsNull(): void
    {
        $result = ApiKey::create(1, ['*'], 'Expiring key', -1); // already expired
        $this->assertNull(ApiKey::verify($result['key']));
    }
}

// =============================================================================
// AuthManager
// =============================================================================

class AuthManagerTest extends TestCase
{
    public function setUp(): void
    {
        phase16Bootstrap();
        AuthManager::reset();
    }

    public function testAttemptSuccessReturnsTokenPair(): void
    {
        $tokens = AuthManager::attempt(['email' => 'alice@test.com', 'password' => 'secret123']);
        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access',  $tokens);
        $this->assertArrayHasKey('refresh', $tokens);
    }

    public function testAttemptWrongPasswordReturnsNull(): void
    {
        $result = AuthManager::attempt(['email' => 'alice@test.com', 'password' => 'wrongpassword']);
        $this->assertNull($result);
    }

    public function testAttemptUnknownEmailReturnsNull(): void
    {
        $result = AuthManager::attempt(['email' => 'nobody@test.com', 'password' => 'anything']);
        $this->assertNull($result);
    }

    public function testRefreshIssuesNewPair(): void
    {
        $pair    = JWT::issueTokenPair(['sub' => 1, 'email' => 'alice@test.com', 'role' => 'admin']);
        $newPair = AuthManager::refresh($pair['refresh'], ['sub' => 1, 'email' => 'alice@test.com', 'role' => 'admin']);
        $this->assertArrayHasKey('access', $newPair);
        $this->assertNotEquals($pair['access'], $newPair['access']);
    }
}

// =============================================================================
// Request::bearerToken + setMeta/getMeta
// =============================================================================

class RequestAuthHelpersTest extends TestCase
{
    public function testBearerTokenExtracted(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $request = new \Nemesis\Http\Request();
        // Simulate Authorization header via $_SERVER
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token-123';
        $fresh = new \Nemesis\Http\Request();
        // bearerToken reads from headers; inject directly for test
        $reflection = new ReflectionProperty(\Nemesis\Http\Request::class, 'headers');
        $reflection->setAccessible(true);
        $reflection->setValue($fresh, ['AUTHORIZATION' => 'Bearer test-token-123']);
        $this->assertEquals('test-token-123', $fresh->bearerToken());
    }

    public function testBearerTokenNullWhenAbsent(): void
    {
        $request = new \Nemesis\Http\Request();
        $reflection = new ReflectionProperty(\Nemesis\Http\Request::class, 'headers');
        $reflection->setAccessible(true);
        $reflection->setValue($request, []);
        $this->assertNull($request->bearerToken());
    }

    public function testSetAndGetMeta(): void
    {
        $request = new \Nemesis\Http\Request();
        $request->setMeta('auth', ['sub' => 5, 'role' => 'admin']);
        $meta = $request->getMeta('auth');
        $this->assertEquals(5,       $meta['sub']);
        $this->assertEquals('admin', $meta['role']);
        $this->assertNull($request->getMeta('nonexistent'));
    }
}
