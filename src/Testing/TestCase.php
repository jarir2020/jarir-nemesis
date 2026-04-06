<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Updated: 2026-04-02

namespace Nemesis\Testing;

use Nemesis\Core\Application;
use Nemesis\Testing\Concerns\MakesHttpRequests;
use Nemesis\Testing\Concerns\ActingAs;
use Nemesis\Testing\Concerns\WithFaker;

/**
 * Base test case for all Nemesis unit and integration tests.
 *
 * Optional traits (mix in when needed):
 *   use RefreshDatabase;     — wrap each test in a DB transaction
 *   use DatabaseAssertions;  — assertDatabaseHas / assertDatabaseMissing
 */
abstract class TestCase
{
    use MakesHttpRequests;
    use ActingAs;
    use WithFaker;

    protected mixed $app = null;

    public function setUp(): void
    {
        // Boot application if needed — subclasses can override
    }

    public function tearDown(): void
    {
        // Cleanup — subclasses can override
    }

    // =========================================================================
    // Assertions
    // =========================================================================

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        if ($condition !== true) {
            throw new \Exception($message ?: "Failed asserting that condition is true.");
        }
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        if ($condition !== false) {
            throw new \Exception($message ?: "Failed asserting that condition is false.");
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            throw new \Exception($message ?: sprintf(
                "Failed asserting that %s equals expected %s.",
                var_export($actual, true), var_export($expected, true)
            ));
        }
    }

    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected == $actual) {
            throw new \Exception($message ?: "Failed asserting that value is NOT equal to " . var_export($expected, true));
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception($message ?: sprintf(
                "Failed asserting that %s is identical to %s.",
                var_export($actual, true), var_export($expected, true)
            ));
        }
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is null.");
        }
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new \Exception($message ?: "Failed asserting that value is not null.");
        }
    }

    protected function assertCount(int $expectedCount, array|\Countable $haystack, string $message = ''): void
    {
        $actual = count($haystack);
        if ($actual !== $expectedCount) {
            throw new \Exception($message ?: "Failed asserting count {$actual} equals expected {$expectedCount}.");
        }
    }

    protected function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $type = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is instance of {$expected}.");
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \Exception($message ?: "Failed asserting that string contains '{$needle}'.");
        }
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \Exception($message ?: "Failed asserting that string does NOT contain '{$needle}'.");
        }
    }

    protected function assertEmpty(mixed $actual, string $message = ''): void
    {
        if (!empty($actual)) {
            throw new \Exception($message ?: "Failed asserting that value is empty.");
        }
    }

    protected function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        if (empty($actual)) {
            throw new \Exception($message ?: "Failed asserting that value is not empty.");
        }
    }

    protected function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual > $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is greater than {$expected}.");
        }
    }

    protected function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual >= $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} >= {$expected}.");
        }
    }

    protected function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual < $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is less than {$expected}.");
        }
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            throw new \Exception($message ?: "Failed asserting that array contains " . var_export($needle, true) . ".");
        }
    }

    protected function assertNotContains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (in_array($needle, $haystack, true)) {
            throw new \Exception($message ?: "Failed asserting that array does NOT contain " . var_export($needle, true) . ".");
        }
    }

    // Added: 2026-04-03
    protected function assertIsInt(mixed $actual, string $message = ''): void
    {
        if (!is_int($actual)) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is of type int.");
        }
    }

    protected function assertIsString(mixed $actual, string $message = ''): void
    {
        if (!is_string($actual)) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is of type string.");
        }
    }

    protected function assertIsBool(mixed $actual, string $message = ''): void
    {
        if (!is_bool($actual)) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is of type bool.");
        }
    }

    protected function assertIsArray(mixed $actual, string $message = ''): void
    {
        if (!is_array($actual)) {
            throw new \Exception($message ?: "Failed asserting that value is of type array.");
        }
    }

    protected function assertIsFloat(mixed $actual, string $message = ''): void
    {
        if (!is_float($actual)) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is of type float.");
        }
    }

    protected function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual <= $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} <= {$expected}.");
        }
    }

    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            throw new \Exception($message ?: "Failed asserting that two values are not identical.");
        }
    }

    protected function expectException(string $exceptionClass): void
    {
        // Store expectation for the test runner to check (simple flag approach)
        $this->_expectedException = $exceptionClass;
    }

    /** @internal Used by expectException mechanism */
    public string $_expectedException = '';

    protected function fail(string $message = ''): never
    {
        throw new \Exception($message ?: 'Test explicitly failed.');
    }

    // -------------------------------------------------------------------------
    // Array assertions
    // -------------------------------------------------------------------------

    public static function assertArrayHasKey(mixed $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array has key {$key}.");
        }
    }

    public static function assertArrayNotHasKey(mixed $key, array $array, string $message = ''): void
    {
        if (array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array does NOT have key {$key}.");
        }
    }

    // Added: 2026-04-06 — extended assertion helpers for Phase 16+
    protected function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        if (!str_starts_with($string, $prefix)) {
            throw new \Exception($message ?: "Failed asserting that '{$string}' starts with '{$prefix}'.");
        }
    }

    protected function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        if (!str_ends_with($string, $suffix)) {
            throw new \Exception($message ?: "Failed asserting that '{$string}' ends with '{$suffix}'.");
        }
    }

    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            throw new \Exception($message ?: "Failed asserting that '{$string}' matches pattern '{$pattern}'.");
        }
    }

    protected function assertIsNumeric(mixed $actual, string $message = ''): void
    {
        if (!is_numeric($actual)) {
            throw new \Exception($message ?: 'Failed asserting that value is numeric.');
        }
    }

    // Added: 2026-04-06 — Phase 17 shorthand aliases + exception message assertion
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
    }

    protected function expectExceptionMessageContains(string $substring): void
    {
        $this->_expectedExceptionMessageSubstring = $substring;
    }

    /** @internal Used by expectExceptionMessageContains mechanism */
    public string $_expectedExceptionMessageSubstring = '';
}
