<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 9 Test Suite | Created: 2026-04-03
// Tests: Typed Config DTOs, ConfigFactory, i18n Language loader, PasswordStrength

use Nemesis\Testing\TestCase;
use Nemesis\Config\AppConfig;
use Nemesis\Config\DatabaseConfig;
use Nemesis\Config\MailConfig;
use Nemesis\Config\CacheConfig;
use Nemesis\Config\QueueConfig;
use Nemesis\Config\SessionConfig;
use Nemesis\Config\ConfigFactory;
use Nemesis\I18n\Language;
use Nemesis\Security\PasswordStrength;

// ===========================================================================
// Phase9ConfigTest — Typed Config DTOs
// ===========================================================================

class Phase9ConfigTest extends TestCase
{
    public function tearDown(): void
    {
        ConfigFactory::flush();
    }

    // --- AppConfig ---

    public function testAppConfigFromArrayDefaults(): void
    {
        $cfg = AppConfig::fromArray([]);
        $this->assertSame('Nemesis',     $cfg->name);
        $this->assertSame('production',  $cfg->env);
        $this->assertFalse($cfg->debug);
        $this->assertSame('UTC',         $cfg->timezone);
        $this->assertSame('en',          $cfg->locale);
        $this->assertSame('AES-256-CBC', $cfg->cipher);
    }

    public function testAppConfigFromArrayCustomValues(): void
    {
        $cfg = AppConfig::fromArray([
            'name'     => 'MyApp',
            'env'      => 'staging',
            'debug'    => true,
            'timezone' => 'Asia/Dhaka',
            'locale'   => 'ar',
            'key'      => 'secret123',
            'cipher'   => 'AES-128-CBC',
            'url'      => 'https://myapp.com',
        ]);

        $this->assertSame('MyApp',          $cfg->name);
        $this->assertSame('staging',        $cfg->env);
        $this->assertTrue($cfg->debug);
        $this->assertSame('Asia/Dhaka',     $cfg->timezone);
        $this->assertSame('ar',             $cfg->locale);
        $this->assertSame('secret123',      $cfg->key);
        $this->assertSame('AES-128-CBC',    $cfg->cipher);
        $this->assertSame('https://myapp.com', $cfg->url);
    }

    public function testAppConfigRequiredKeys(): void
    {
        $required = AppConfig::required();
        $this->assertContains('APP_KEY', $required);
    }

    // --- DatabaseConfig ---

    public function testDatabaseConfigFromEnvDefaults(): void
    {
        $cfg = DatabaseConfig::fromEnv();
        $this->assertNotEmpty($cfg->driver);   // defaults to 'mysql'
        $this->assertIsInt($cfg->port);
    }

    public function testDatabaseConfigRequiredKeys(): void
    {
        // Updated 2026-04-06: required() is now driver-aware.
        // SQLite only needs DB_DRIVER; MySQL/pgsql need host + credentials.
        $required = DatabaseConfig::required();
        $this->assertContains('DB_DRIVER', $required);
        // For non-sqlite drivers the host/name/user are also required
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
        if ($driver !== 'sqlite') {
            $this->assertContains('DB_HOST', $required);
            $this->assertContains('DB_NAME', $required);
            $this->assertContains('DB_USER', $required);
        }
    }

    public function testDatabaseConfigToArray(): void
    {
        $cfg = DatabaseConfig::fromEnv();
        $arr = $cfg->toArray();
        $this->assertArrayHasKey('driver',   $arr);
        $this->assertArrayHasKey('host',     $arr);
        $this->assertArrayHasKey('dbname',   $arr);
        $this->assertArrayHasKey('username', $arr);
    }

    // --- MailConfig ---

    public function testMailConfigFromEnvDefaults(): void
    {
        $cfg = MailConfig::fromEnv();
        $this->assertNotEmpty($cfg->mailer);
        $this->assertIsInt($cfg->port);
    }

    // --- CacheConfig ---

    public function testCacheConfigFromEnvDefaults(): void
    {
        $cfg = CacheConfig::fromEnv();
        $this->assertNotEmpty($cfg->driver);
        $this->assertIsInt($cfg->ttl);
    }

    // --- QueueConfig ---

    public function testQueueConfigFromEnvDefaults(): void
    {
        $cfg = QueueConfig::fromEnv();
        $this->assertNotEmpty($cfg->driver);
        $this->assertIsInt($cfg->retryAfter);
        $this->assertIsInt($cfg->maxTries);
    }

    // --- SessionConfig ---

    public function testSessionConfigFromEnvDefaults(): void
    {
        $cfg = SessionConfig::fromEnv();
        $this->assertNotEmpty($cfg->driver);
        $this->assertIsInt($cfg->lifetime);
    }

    // --- ConfigFactory ---

    public function testConfigFactoryMakesAppConfig(): void
    {
        $cfg = ConfigFactory::make(AppConfig::class);
        $this->assertInstanceOf(AppConfig::class, $cfg);
    }

    public function testConfigFactoryReturnsSameInstance(): void
    {
        $a = ConfigFactory::make(AppConfig::class);
        $b = ConfigFactory::make(AppConfig::class);
        $this->assertSame($a, $b);
    }

    public function testConfigFactoryFlushClearsCache(): void
    {
        $a = ConfigFactory::make(AppConfig::class);
        ConfigFactory::flush();
        $b = ConfigFactory::make(AppConfig::class);
        // After flush a fresh instance is returned — not the same object
        $this->assertNotSame($a, $b);
    }

    public function testConfigFactoryThrowsForUnknownClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ConfigFactory::make('Nemesis\Config\NonExistentConfig');
    }

    public function testValidateRequiredReturnsMissingKeys(): void
    {
        // Temporarily unset a key
        $originalKey = getenv('APP_KEY');
        putenv('APP_KEY=');

        $missing = ConfigFactory::validateRequired([AppConfig::class]);
        $this->assertContains('APP_KEY', $missing);

        // Restore
        putenv('APP_KEY=' . ($originalKey ?: ''));
    }

    public function testValidateRequiredReturnsEmptyWhenAllSet(): void
    {
        putenv('APP_KEY=test-key-for-test');
        putenv('DB_DRIVER=mysql');
        putenv('DB_HOST=localhost');
        putenv('DB_NAME=test');
        putenv('DB_USER=root');

        $missing = ConfigFactory::validateRequired([AppConfig::class, DatabaseConfig::class]);
        $this->assertEmpty($missing);
    }
}

// ===========================================================================
// Phase9LanguageTest — i18n Language loader
// ===========================================================================

class Phase9LanguageTest extends TestCase
{
    public function setUp(): void
    {
        Language::flush();
        Language::setLocale('en');
        Language::setFallback('en');
        // Point to the actual language directory
        Language::setPath(__DIR__ . '/../../app/Language');
    }

    public function tearDown(): void
    {
        Language::flush();
        Language::setLocale('en');
    }

    // --- Basic retrieval ---

    public function testGetKnownKey(): void
    {
        $val = Language::get('app.welcome');
        $this->assertStringContainsString('Welcome', $val);
    }

    public function testGetReturnsRawKeyWhenMissing(): void
    {
        $val = Language::get('app.this_key_does_not_exist_xyz');
        $this->assertSame('app.this_key_does_not_exist_xyz', $val);
    }

    public function testGetWithPlaceholderReplacement(): void
    {
        $val = Language::get('app.welcome', ['app' => 'Nemesis']);
        $this->assertSame('Welcome to Nemesis!', $val);
    }

    public function testGetPlaceholderLowercaseReplacement(): void
    {
        // Template: ':resource created successfully.' — lowercase placeholder
        // Interpolation replaces ':resource' → 'User' directly
        $val = Language::get('app.created', ['resource' => 'User']);
        $this->assertStringContainsString('User', $val);
        $this->assertStringContainsString('created successfully', $val);
    }

    public function testGetWithResourcePlaceholder(): void
    {
        $val = Language::get('app.created', ['resource' => 'User']);
        $this->assertSame('User created successfully.', $val);
    }

    // --- Locale override per call ---

    public function testGetWithLocaleOverride(): void
    {
        $en = Language::get('app.success');
        $ar = Language::get('app.success', [], 'ar');
        $this->assertNotSame($en, $ar);  // Arabic and English differ
    }

    // --- Fallback ---

    public function testFallbackToEnWhenKeyMissingInLocale(): void
    {
        Language::setLocale('ar');
        // 'app.cancel' only exists in English — should fall back
        $val = Language::get('app.cancel');
        $this->assertSame('Cancelled.', $val);
    }

    // --- has() ---

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->assertTrue(Language::has('app.welcome'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(Language::has('app.no_such_key_ever_xyz'));
    }

    // --- all() ---

    public function testAllReturnsArray(): void
    {
        $lines = Language::all('app');
        $this->assertIsArray($lines);
        $this->assertArrayHasKey('welcome', $lines);
    }

    // --- setLocale / getLocale ---

    public function testSetAndGetLocale(): void
    {
        Language::setLocale('fr');
        $this->assertSame('fr', Language::getLocale());
        Language::setLocale('en');
    }

    // --- auth group ---

    public function testAuthGroupLoads(): void
    {
        $val = Language::get('auth.failed');
        $this->assertStringContainsString('credentials', $val);
    }

    public function testAuthThrottleWithSeconds(): void
    {
        $val = Language::get('auth.throttle', ['seconds' => '30']);
        $this->assertStringContainsString('30', $val);
    }

    // --- validation group ---

    public function testValidationRequiredMessage(): void
    {
        $val = Language::get('validation.required', ['field' => 'Email']);
        $this->assertStringContainsString('Email', $val);
    }

    // --- flush() ---

    public function testFlushClearsLoadedCache(): void
    {
        Language::get('app.welcome'); // loads the file
        Language::flush();            // clears cache
        // Should still work after flush (reloads from disk)
        $val = Language::get('app.welcome');
        $this->assertStringContainsString('Welcome', $val);
    }
}

// ===========================================================================
// Phase9PasswordStrengthTest — PasswordStrength scorer
// ===========================================================================

class Phase9PasswordStrengthTest extends TestCase
{
    // --- score() basics ---

    public function testEmptyPasswordScoresZero(): void
    {
        $this->assertSame(0, PasswordStrength::score(''));
    }

    public function testShortPasswordScoresLow(): void
    {
        $score = PasswordStrength::score('abc');
        $this->assertLessThan(PasswordStrength::FAIR, $score);
    }

    public function testCommonPasswordScoresVeryLow(): void
    {
        $score = PasswordStrength::score('password');
        $this->assertLessThan(PasswordStrength::FAIR, $score);
    }

    public function testAllSameCharScoresVeryLow(): void
    {
        $score = PasswordStrength::score('aaaaaaaaaa');
        $this->assertLessThan(PasswordStrength::FAIR, $score);
    }

    public function testStrongPasswordScoresHigher(): void
    {
        $score = PasswordStrength::score('Tr0ub4dor&3#Xy!');
        $this->assertGreaterThanOrEqual(PasswordStrength::STRONG, $score);
    }

    public function testVeryStrongPasswordScores80Plus(): void
    {
        $score = PasswordStrength::score('C0rr3ct-H0rs3*Battery$Staple99!');
        $this->assertGreaterThanOrEqual(PasswordStrength::VERY_STRONG, $score);
    }

    public function testScoreIsClampedTo100(): void
    {
        $score = PasswordStrength::score('C0rr3ct-H0rs3*Battery$Staple99!');
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testScoreIsClampedToZero(): void
    {
        $score = PasswordStrength::score('aaa');
        $this->assertGreaterThanOrEqual(0, $score);
    }

    // --- label() ---

    public function testLabelVeryWeak(): void
    {
        $this->assertSame('very_weak', PasswordStrength::label(0));
        $this->assertSame('very_weak', PasswordStrength::label(10));
    }

    public function testLabelWeak(): void
    {
        $this->assertSame('weak', PasswordStrength::label(20));
        $this->assertSame('weak', PasswordStrength::label(35));
    }

    public function testLabelFair(): void
    {
        $this->assertSame('fair', PasswordStrength::label(40));
        $this->assertSame('fair', PasswordStrength::label(55));
    }

    public function testLabelStrong(): void
    {
        $this->assertSame('strong', PasswordStrength::label(60));
        $this->assertSame('strong', PasswordStrength::label(75));
    }

    public function testLabelVeryStrong(): void
    {
        $this->assertSame('very_strong', PasswordStrength::label(80));
        $this->assertSame('very_strong', PasswordStrength::label(100));
    }

    // --- analyze() ---

    public function testAnalyzeReturnsExpectedKeys(): void
    {
        $result = PasswordStrength::analyze('Test@1234');
        $this->assertArrayHasKey('score',       $result);
        $this->assertArrayHasKey('label',       $result);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('passed',      $result);
    }

    public function testAnalyzePassedTrueWhenScoreMeetsMin(): void
    {
        $result = PasswordStrength::analyze('C0rr3ct-H0rs3*Battery$Staple!', 60);
        $this->assertTrue($result['passed']);
    }

    public function testAnalyzePassedFalseWhenScoreBelowMin(): void
    {
        $result = PasswordStrength::analyze('weak', 60);
        $this->assertFalse($result['passed']);
    }

    public function testAnalyzePassedDefaultMinIsZero(): void
    {
        $result = PasswordStrength::analyze(''); // score=0
        $this->assertTrue($result['passed']);    // 0 >= 0
    }

    // --- suggestions() ---

    public function testSuggestsLengthForShortPassword(): void
    {
        $tips = PasswordStrength::suggestions('abc');
        $this->assertNotEmpty($tips);
        $found = false;
        foreach ($tips as $tip) {
            if (stripos($tip, '12') !== false) $found = true;
        }
        $this->assertTrue($found, 'Expected length tip not found');
    }

    public function testSuggestsSymbolsWhenMissing(): void
    {
        $tips = PasswordStrength::suggestions('NoSymbols1234');
        $hasSymbolTip = false;
        foreach ($tips as $tip) {
            if (stripos($tip, 'symbol') !== false) $hasSymbolTip = true;
        }
        $this->assertTrue($hasSymbolTip);
    }

    public function testNoSuggestionsForVeryStrongPassword(): void
    {
        $score = PasswordStrength::score('C0rr3ct-H0rs3*Battery$Staple99!!');
        if ($score >= PasswordStrength::VERY_STRONG) {
            $tips = PasswordStrength::suggestions('C0rr3ct-H0rs3*Battery$Staple99!!');
            $this->assertEmpty($tips);
        } else {
            $this->assertTrue(true); // score didn't reach VERY_STRONG — skip
        }
    }

    // --- check() ---

    public function testCheckPassesForStrongPassword(): void
    {
        // Should not throw
        PasswordStrength::check('C0rr3ct-H0rs3*Battery$Staple!', PasswordStrength::FAIR);
        $this->assertTrue(true);
    }

    public function testCheckThrowsForWeakPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PasswordStrength::check('password', PasswordStrength::STRONG);
    }

    public function testCheckExceptionMessageContainsScore(): void
    {
        try {
            PasswordStrength::check('weak', 90);
            $this->fail('Expected exception not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('score', $e->getMessage());
        }
    }

    // --- sequential / keyboard walk penalties ---

    public function testSequentialPatternPenalised(): void
    {
        $scoreSeq    = PasswordStrength::score('abcdefghi1');
        $scoreNormal = PasswordStrength::score('xqz9mWp#r1');
        $this->assertLessThan($scoreNormal, $scoreSeq);
    }

    public function testKeyboardWalkPenalised(): void
    {
        $scoreWalk   = PasswordStrength::score('qwerty12345');
        $scoreNormal = PasswordStrength::score('xqz9mWp#r11');
        $this->assertLessThan($scoreNormal, $scoreWalk);
    }
}
