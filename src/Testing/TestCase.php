<?php

namespace Nemesis\Testing;

use Nemesis\Core\Application;

abstract class TestCase {
    protected $app;

    public function setUp() {
        // Boot application if needed
    }

    public function tearDown() {
        // Cleanup
    }

    // --- Assertions ---

    protected function assertTrue($condition, $message = '') {
        if ($condition !== true) {
            throw new \Exception($message ?: "Failed asserting that condition is true.");
        }
    }

    protected function assertFalse($condition, $message = '') {
        if ($condition !== false) {
            throw new \Exception($message ?: "Failed asserting that condition is false.");
        }
    }

    protected function assertEquals($expected, $actual, $message = '') {
        if ($expected != $actual) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " matches expected " . var_export($expected, true));
        }
    }

    protected function assertSame($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is identical to " . var_export($expected, true));
        }
    }

    protected function assertNull($actual, $message = '') {
        if ($actual !== null) {
            throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " is null.");
        }
    }

    protected function assertNotNull($actual, $message = '') {
        if ($actual === null) {
            throw new \Exception($message ?: "Failed asserting that value is not null.");
        }
    }

    protected function assertCount($expectedCount, $haystack, $message = '') {
        if (count($haystack) !== $expectedCount) {
            throw new \Exception($message ?: "Failed asserting that array count " . count($haystack) . " matches expected $expectedCount.");
        }
    }

    protected function assertInstanceOf($expected, $actual, $message = '') {
        if (!($actual instanceof $expected)) {
            $type = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is instance of {$expected}");
        }
    }

    protected function assertStringContainsString($needle, $haystack, $message = '') {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception($message ?: "Failed asserting that string contains '{$needle}'");
        }
    }

    protected function assertNotEquals($expected, $actual, $message = '') {
        if ($expected == $actual) {
            throw new \Exception($message ?: "Failed asserting that value is NOT equal to " . var_export($expected, true));
        }
    }

    public static function assertArrayHasKey($key, $array, $message = '') {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array has key {$key}");
        }
    }

    public static function assertArrayNotHasKey($key, $array, $message = '') {
        if (array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array does NOT have key {$key}");
        }
    }
}
