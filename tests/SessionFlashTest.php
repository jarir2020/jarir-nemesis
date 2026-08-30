<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Tests for Gap 1 — Session::all(), flash(), getOldInput()
// Updated: 2026-08-30

namespace Tests\Unit;

use Nemesis\Testing\TestCase;
use Nemesis\Http\Session;

class SessionFlashTest extends TestCase
{
    public function setUp(): void
    {
        // Each test gets a clean session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];
    }

    public function test_all_returns_full_session(): void
    {
        $_SESSION['user_id']   = 42;
        $_SESSION['user_type'] = 'admin';

        $all = Session::all();

        $this->assertIsArray($all);
        $this->assertSame(42, $all['user_id']);
        $this->assertSame('admin', $all['user_type']);
    }

    public function test_flash_stores_value(): void
    {
        Session::flash('notice', 'Hello, world!');

        $this->assertSame('Hello, world!', $_SESSION['_flash']['notice']);
    }

    public function test_get_flash_consumes_value(): void
    {
        Session::flash('notice', 'Hello');
        $this->assertSame('Hello', Session::getFlash('notice'));
        // After consumption, the key is gone.
        $this->assertArrayNotHasKey('notice', $_SESSION['_flash'] ?? []);
    }

    public function test_old_input_round_trip(): void
    {
        Session::flashOldInput(['email' => 'user@example.com', 'name' => 'User']);

        $this->assertSame('user@example.com', Session::getOldInput('email'));
        $this->assertSame('User', Session::getOldInput('name'));
        // Default is returned for unknown keys.
        $this->assertNull(Session::getOldInput('missing'));
        $this->assertSame('fallback', Session::getOldInput('missing', 'fallback'));
    }

    public function test_pull_returns_and_removes(): void
    {
        Session::set('temp', 'value');
        $this->assertSame('value', Session::pull('temp'));
        $this->assertFalse(Session::has('temp'));
    }

    public function test_reflash_and_keep(): void
    {
        Session::flash('a', 1);
        Session::flash('b', 2);
        Session::flash('c', 3);

        // Keep only 'a' and 'c' for the next request.
        Session::keep(['a', 'c']);

        $this->assertArrayHasKey('a', $_SESSION['_flash']);
        $this->assertArrayHasKey('c', $_SESSION['_flash']);
        $this->assertArrayNotHasKey('b', $_SESSION['_flash']);
    }

    public function test_token_regeneration(): void
    {
        $first = Session::token();
        Session::regenerateToken();
        $second = Session::token();

        $this->assertNotSame($first, $second);
        $this->assertSame(64, strlen($second)); // bin2hex(random_bytes(32)) = 64 chars
    }
}
