<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Auth\Social\SocialUser;
use Nemesis\Auth\Social\SocialAuth;
use Nemesis\Auth\Social\AbstractSocialProvider;
use Nemesis\Auth\Social\SocialProviderInterface;
use Nemesis\Auth\Social\Providers\GoogleProvider;
use Nemesis\Auth\Social\Providers\GitHubProvider;
use Nemesis\Auth\Social\Providers\XProvider;
use Nemesis\Auth\Social\Providers\FacebookProvider;
use Nemesis\Auth\Social\Providers\LinkedInProvider;
use Nemesis\Core\Database;

// Bootstrap in-memory SQLite for Phase 17 tests
function phase17Bootstrap(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    putenv('JWT_SECRET=nemesis-phase17-test-secret-key-32ch');
    putenv('JWT_TTL=900');
    putenv('JWT_REFRESH_TTL=604800');
    putenv('APP_NAME=NemesisTest');

    // Unset social provider env vars so config() fallback uses empty strings
    foreach (['GOOGLE','GITHUB','X','FACEBOOK','LINKEDIN'] as $p) {
        putenv("{$p}_CLIENT_ID=test-client-id");
        putenv("{$p}_CLIENT_SECRET=test-client-secret");
        putenv("{$p}_REDIRECT=https://example.com/auth/callback");
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    Database::setPdo($pdo);

    // users table required by SocialAuth::findOrCreateUser
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL DEFAULT '',
        email TEXT DEFAULT NULL,
        password TEXT NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed one existing user (for email-match path)
    $pdo->exec("INSERT INTO users (name, email, password) VALUES ('Existing User', 'existing@test.com', '')");

    // social_accounts will be auto-created by SocialAuth::ensureSocialAccountsTable()

    SocialAuth::reset();
}

// =============================================================================
// SocialUser — DTO
// =============================================================================

class SocialUserTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $user = new SocialUser(
            id:       '12345',
            name:     'Jane Doe',
            email:    'jane@example.com',
            avatar:   'https://example.com/avatar.jpg',
            token:    'tok_abc',
            provider: 'google',
            raw:      ['sub' => '12345'],
        );

        $this->assertEquals('12345',       $user->id);
        $this->assertEquals('Jane Doe',    $user->name);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertEquals('google',      $user->provider);
        $this->assertEquals('tok_abc',     $user->token);
        $this->assertEquals(['sub' => '12345'], $user->raw);
    }

    public function testFromArray(): void
    {
        $raw = [
            'id'         => '99',
            'login'      => 'johndoe',
            'name'       => 'John Doe',
            'email'      => 'john@example.com',
            'avatar_url' => 'https://example.com/pic.png',
        ];

        $user = SocialUser::fromArray($raw, 'github', 'tok_gh');

        $this->assertEquals('99',          $user->id);
        $this->assertEquals('John Doe',    $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('github',      $user->provider);
        $this->assertEquals('tok_gh',      $user->token);
    }

    public function testFromArrayFallsBackToLoginForName(): void
    {
        $raw = ['id' => '1', 'login' => 'ghost_user', 'email' => ''];
        $user = SocialUser::fromArray($raw, 'github', 'tok');
        $this->assertEquals('ghost_user', $user->name);
    }

    public function testFromArrayAvatarUrlFallback(): void
    {
        $raw = ['id' => '2', 'avatar_url' => 'https://avatars.github.com/u/2'];
        $user = SocialUser::fromArray($raw, 'github', 'tok');
        $this->assertEquals('https://avatars.github.com/u/2', $user->avatar);
    }
}

// =============================================================================
// Provider instantiation + URL generation
// =============================================================================

class SocialProviderInstantiationTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); }

    public function testGoogleProviderImplementsInterface(): void
    {
        $p = new GoogleProvider('cid', 'csecret', 'https://example.com/callback');
        $this->assertInstanceOf(SocialProviderInterface::class, $p);
    }

    public function testGitHubProviderImplementsInterface(): void
    {
        $p = new GitHubProvider('cid', 'csecret', 'https://example.com/callback');
        $this->assertInstanceOf(SocialProviderInterface::class, $p);
    }

    public function testXProviderImplementsInterface(): void
    {
        $p = new XProvider('cid', 'csecret', 'https://example.com/callback');
        $this->assertInstanceOf(SocialProviderInterface::class, $p);
    }

    public function testFacebookProviderImplementsInterface(): void
    {
        $p = new FacebookProvider('cid', 'csecret', 'https://example.com/callback');
        $this->assertInstanceOf(SocialProviderInterface::class, $p);
    }

    public function testLinkedInProviderImplementsInterface(): void
    {
        $p = new LinkedInProvider('cid', 'csecret', 'https://example.com/callback');
        $this->assertInstanceOf(SocialProviderInterface::class, $p);
    }
}

// =============================================================================
// Authorization URL generation
// =============================================================================

class SocialAuthorizationUrlTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); }

    public function testGoogleAuthUrlContainsRequiredParams(): void
    {
        $p   = new GoogleProvider('my-client-id', 'secret', 'https://app.com/callback');
        $url = $p->getAuthorizationUrl();

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContains('client_id=my-client-id', $url);
        $this->assertStringContains('response_type=code', $url);
        $this->assertStringContains('redirect_uri=', $url);
        $this->assertStringContains('scope=', $url);
        $this->assertStringContains('state=', $url);
    }

    public function testGitHubAuthUrlContainsRequiredParams(): void
    {
        $p   = new GitHubProvider('gh-cid', 'secret', 'https://app.com/callback');
        $url = $p->getAuthorizationUrl();

        $this->assertStringStartsWith('https://github.com/login/oauth/authorize', $url);
        $this->assertStringContains('client_id=gh-cid', $url);
        $this->assertStringContains('scope=', $url);
    }

    public function testXAuthUrlContainsPkceParams(): void
    {
        $p   = new XProvider('x-cid', 'secret', 'https://app.com/callback');
        $url = $p->getAuthorizationUrl();

        $this->assertStringContains('code_challenge=', $url);
        $this->assertStringContains('code_challenge_method=S256', $url);
    }

    public function testFacebookAuthUrlContainsGraphVersion(): void
    {
        $p   = new FacebookProvider('fb-cid', 'secret', 'https://app.com/callback');
        $url = $p->getAuthorizationUrl();

        $this->assertStringContains('facebook.com', $url);
        $this->assertStringContains('client_id=fb-cid', $url);
    }

    public function testLinkedInAuthUrlIsCorrect(): void
    {
        $p   = new LinkedInProvider('li-cid', 'secret', 'https://app.com/callback');
        $url = $p->getAuthorizationUrl();

        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization', $url);
        $this->assertStringContains('client_id=li-cid', $url);
    }
}

// =============================================================================
// State generation
// =============================================================================

class SocialStateTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); }

    public function testStateIsNonEmptyString(): void
    {
        $p = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $this->assertNotEmpty($p->getState());
    }

    public function testEachInstanceHasUniqueState(): void
    {
        $p1 = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $p2 = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $this->assertNotEquals($p1->getState(), $p2->getState());
    }

    public function testStatePresentInAuthUrl(): void
    {
        $p   = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $url = $p->getAuthorizationUrl();
        $this->assertStringContains('state=' . $p->getState(), $url);
    }
}

// =============================================================================
// XProvider PKCE
// =============================================================================

class XProviderPkceTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); }

    public function testCodeVerifierIsUrlSafe(): void
    {
        $p = new XProvider('cid', 'sec', 'https://app.com/cb');
        $v = $p->getCodeVerifier();
        // Must be URL-safe base64 (no +, /, =)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $v);
    }

    public function testCodeVerifierHasAdequateLength(): void
    {
        $p = new XProvider('cid', 'sec', 'https://app.com/cb');
        // 32 random bytes → ~43 base64url chars
        $this->assertGreaterThanOrEqual(40, strlen($p->getCodeVerifier()));
    }

    public function testPkceIncludedInAuthUrl(): void
    {
        $p   = new XProvider('cid', 'sec', 'https://app.com/cb');
        $url = $p->getAuthorizationUrl();
        $this->assertStringContains('code_challenge=', $url);
        $this->assertStringContains('code_challenge_method=S256', $url);
    }
}

// =============================================================================
// SocialAuth driver factory
// =============================================================================

class SocialAuthDriverTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); SocialAuth::reset(); }

    public function testDriverReturnsGoogleProvider(): void
    {
        $p = SocialAuth::driver('google');
        $this->assertInstanceOf(GoogleProvider::class, $p);
    }

    public function testDriverReturnsGitHubProvider(): void
    {
        $p = SocialAuth::driver('github');
        $this->assertInstanceOf(GitHubProvider::class, $p);
    }

    public function testDriverReturnsXProvider(): void
    {
        $p = SocialAuth::driver('x');
        $this->assertInstanceOf(XProvider::class, $p);
    }

    public function testDriverAliasTwitterReturnsXProvider(): void
    {
        SocialAuth::reset();
        $p = SocialAuth::driver('twitter');
        $this->assertInstanceOf(XProvider::class, $p);
    }

    public function testDriverReturnsFacebookProvider(): void
    {
        $p = SocialAuth::driver('facebook');
        $this->assertInstanceOf(FacebookProvider::class, $p);
    }

    public function testDriverReturnsLinkedInProvider(): void
    {
        $p = SocialAuth::driver('linkedin');
        $this->assertInstanceOf(LinkedInProvider::class, $p);
    }

    public function testDriverCachesInstance(): void
    {
        $p1 = SocialAuth::driver('google');
        $p2 = SocialAuth::driver('google');
        $this->assertSame($p1, $p2);
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SocialAuth::driver('myspace');
    }

    public function testResetClearsCache(): void
    {
        $p1 = SocialAuth::driver('google');
        SocialAuth::reset();
        $p2 = SocialAuth::driver('google');
        $this->assertNotSame($p1, $p2);
    }
}

// =============================================================================
// SocialAuth::handleCallback — state validation
// =============================================================================

class SocialAuthHandleCallbackStateTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); SocialAuth::reset(); }

    public function testStateMismatchThrows(): void
    {
        // We can test the state guard without hitting a real OAuth server
        // because the mismatch is caught before the HTTP call.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageContains('state mismatch');

        // Use a provider that would fail on the HTTP call anyway —
        // but state check fires first
        SocialAuth::handleCallback('google', 'fake-code', 'bad-state', 'expected-state');
    }
}

// =============================================================================
// SocialAuth::findOrCreateUser
// =============================================================================

class SocialAuthFindOrCreateUserTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); SocialAuth::reset(); }

    private function makeSocialUser(
        string $id,
        string $email,
        string $provider = 'google'
    ): SocialUser {
        return new SocialUser(
            id:       $id,
            name:     'Test User',
            email:    $email,
            avatar:   '',
            token:    'tok_' . $id,
            provider: $provider,
            raw:      [],
        );
    }

    public function testCreatesNewUserWhenNoneExists(): void
    {
        $su     = $this->makeSocialUser('g001', 'newuser@test.com');
        $result = SocialAuth::findOrCreateUser($su);

        $this->assertArrayHasKey('access',     $result);
        $this->assertArrayHasKey('refresh',    $result);
        $this->assertArrayHasKey('user_id',    $result);
        $this->assertIsNumeric($result['user_id']);
        $this->assertGreaterThan(0, $result['user_id']);
    }

    public function testLinksExistingUserByEmail(): void
    {
        // existing@test.com was seeded in phase17Bootstrap
        $su     = $this->makeSocialUser('g-existing', 'existing@test.com');
        $result = SocialAuth::findOrCreateUser($su);

        // Should return user_id = 1 (the seeded user)
        $this->assertEquals(1, $result['user_id']);
    }

    public function testReturnsSameUserIdOnSubsequentLogin(): void
    {
        $su      = $this->makeSocialUser('g002', 'repeat@test.com');
        $first   = SocialAuth::findOrCreateUser($su);
        $second  = SocialAuth::findOrCreateUser($su);
        $this->assertEquals($first['user_id'], $second['user_id']);
    }

    public function testDifferentProvidersCanLinkToSameUser(): void
    {
        $email = 'shared@test.com';
        $su1   = $this->makeSocialUser('google-uid-1', $email, 'google');
        $su2   = $this->makeSocialUser('github-uid-1', $email, 'github');

        $r1 = SocialAuth::findOrCreateUser($su1);
        $r2 = SocialAuth::findOrCreateUser($su2);

        // Both should resolve to same user
        $this->assertEquals($r1['user_id'], $r2['user_id']);
    }

    public function testTokenPairStructureIsCorrect(): void
    {
        $su     = $this->makeSocialUser('g003', 'structcheck@test.com');
        $result = SocialAuth::findOrCreateUser($su);

        $this->assertArrayHasKey('access',     $result);
        $this->assertArrayHasKey('refresh',    $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertGreaterThan(0,   $result['expires_in']);
    }

    public function testHandlesNullEmail(): void
    {
        $su = new SocialUser(
            id:       'x-no-email',
            name:     'No Email User',
            email:    '',
            avatar:   '',
            token:    'tok_x',
            provider: 'x',
            raw:      [],
        );

        $result = SocialAuth::findOrCreateUser($su);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertGreaterThan(0, $result['user_id']);
    }
}

// =============================================================================
// SocialAuth config fallback
// =============================================================================

class SocialAuthConfigTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); SocialAuth::reset(); }

    public function testConfigReadsFromEnvVars(): void
    {
        putenv('GOOGLE_CLIENT_ID=env-client-id');
        putenv('GOOGLE_CLIENT_SECRET=env-secret');
        putenv('GOOGLE_REDIRECT=https://env.example.com/callback');

        SocialAuth::reset();
        $p   = SocialAuth::driver('google');
        $url = $p->getAuthorizationUrl();

        $this->assertStringContains('client_id=env-client-id', $url);
    }
}

// =============================================================================
// Socialable trait
// =============================================================================

class SocialableTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); SocialAuth::reset(); }

    private function makeUserObject(int $id): object
    {
        return new class($id) {
            use \Nemesis\Auth\Traits\Socialable;
            public int $id;
            public function __construct(int $id) { $this->id = $id; }
        };
    }

    public function testSocialAccountsReturnsArray(): void
    {
        $user = $this->makeUserObject(1);
        $this->assertIsArray($user->socialAccounts());
    }

    public function testHasSocialReturnsFalseWhenNotLinked(): void
    {
        $user = $this->makeUserObject(999);
        $this->assertFalse($user->hasSocial('google'));
    }

    public function testLinkSocialAndHasSocial(): void
    {
        // Create a fresh user in DB for this test
        Database::statement(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
            ['Trait User', 'trait@test.com', '']
        );
        $userId = (int) Database::connect()->lastInsertId();

        $user = $this->makeUserObject($userId);
        $this->assertFalse($user->hasSocial('google'));

        $su = new SocialUser(
            id:       'trait-g-001',
            name:     'Trait User',
            email:    'trait@test.com',
            avatar:   '',
            token:    'tok_trait',
            provider: 'google',
            raw:      [],
        );

        $user->linkSocial($su);
        $this->assertTrue($user->hasSocial('google'));
    }

    public function testUnlinkSocial(): void
    {
        // Create a fresh user
        Database::statement(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
            ['Unlink User', 'unlink@test.com', '']
        );
        $userId = (int) Database::connect()->lastInsertId();

        $user = $this->makeUserObject($userId);
        $su   = new SocialUser(
            id:       'ul-g-001',
            name:     'Unlink User',
            email:    'unlink@test.com',
            avatar:   '',
            token:    'tok_ul',
            provider: 'google',
            raw:      [],
        );

        $user->linkSocial($su);
        $this->assertTrue($user->hasSocial('google'));

        $user->unlinkSocial('google');
        $this->assertFalse($user->hasSocial('google'));
    }

    public function testSocialAccountsListsLinkedProviders(): void
    {
        Database::statement(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
            ['Multi User', 'multi@test.com', '']
        );
        $userId = (int) Database::connect()->lastInsertId();
        $user   = $this->makeUserObject($userId);

        foreach (['google' => 'multi-g', 'github' => 'multi-gh'] as $provider => $pid) {
            $user->linkSocial(new SocialUser(
                id:       $pid,
                name:     'Multi User',
                email:    'multi@test.com',
                avatar:   '',
                token:    'tok_' . $provider,
                provider: $provider,
                raw:      [],
            ));
        }

        $accounts = $user->socialAccounts();
        $this->assertCount(2, $accounts);
        $providers = array_column($accounts, 'provider');
        $this->assertContains('google', $providers);
        $this->assertContains('github', $providers);
    }

    public function testSocialRedirectReturnsTupleWithUrlAndState(): void
    {
        SocialAuth::reset();
        putenv('GOOGLE_CLIENT_ID=test-cid');

        // Call via concrete class that uses the trait (avoids deprecated static trait call)
        $user = $this->makeUserObject(1);
        [$url, $state] = $user::socialRedirect('google');

        $this->assertStringStartsWith('https://', $url);
        $this->assertNotEmpty($state);
        $this->assertStringContains('state=' . $state, $url);
    }

    public function testLinkSocialWithoutIdThrows(): void
    {
        $user = new class {
            use \Nemesis\Auth\Traits\Socialable;
            // no $id property — simulates unsaved model
        };

        $this->expectException(\RuntimeException::class);
        $user->linkSocial(new SocialUser(
            id: 'x', name: 'x', email: '', avatar: '', token: '', provider: 'google', raw: []
        ));
    }
}

// =============================================================================
// AbstractSocialProvider — scope merging
// =============================================================================

class SocialProviderScopeTest extends TestCase
{
    public function setUp(): void { phase17Bootstrap(); }

    public function testCustomScopesAreMergedWithDefaults(): void
    {
        $p   = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $url = $p->getAuthorizationUrl(['https://www.googleapis.com/auth/calendar']);
        // calendar scope must appear
        $this->assertStringContains('calendar', $url);
        // default openid scope must still appear
        $this->assertStringContains('openid', $url);
    }

    public function testDuplicateScopesAreDeduped(): void
    {
        $p   = new GoogleProvider('cid', 'sec', 'https://app.com/cb');
        $url = $p->getAuthorizationUrl(['openid', 'email']); // both already default
        // "openid" must appear only once
        $count = substr_count(urldecode($url), 'openid');
        $this->assertEquals(1, $count);
    }
}
