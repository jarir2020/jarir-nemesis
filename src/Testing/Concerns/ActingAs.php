<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Concerns;

/**
 * ActingAs
 *
 * Sets a fake authenticated user for the duration of a test.
 * Stores the user in $_SESSION so Auth helpers see it as logged in.
 *
 * Usage:
 *   $user = ['id' => 1, 'name' => 'Alice', 'role' => 'admin'];
 *   $this->actingAs($user);
 *   // subsequent calls to auth() / Auth::user() return $user
 */
trait ActingAs
{
    /** The currently authenticated user for this test. */
    protected mixed $actingAsUser = null;

    /**
     * Set the authenticated user for the test.
     *
     * @param  mixed   $user   Any user object or array
     * @param  string  $guard  Guard name (stored as session key prefix)
     */
    protected function actingAs(mixed $user, string $guard = 'web'): static
    {
        $this->actingAsUser = $user;

        // Seed the session so session-based auth sees the user
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION["{$guard}_user"] = $user;
        if (is_array($user) && isset($user['id'])) {
            $_SESSION["{$guard}_user_id"] = $user['id'];
        }

        return $this;
    }

    /**
     * Clear the acting user and clean up session state.
     */
    protected function clearActingAs(string $guard = 'web'): void
    {
        $this->actingAsUser = null;
        unset($_SESSION["{$guard}_user"], $_SESSION["{$guard}_user_id"]);
    }

    /**
     * Return the currently acting user (or null if not set).
     */
    protected function getActingUser(): mixed
    {
        return $this->actingAsUser;
    }
}
