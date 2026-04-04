<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Password Strength Scorer | Added: 2026-04-03

namespace Nemesis\Security;

/**
 * Password Strength Scorer.
 *
 * Scores a password on a 0–100 scale and maps it to a human label.
 *
 * Usage:
 *   $result = PasswordStrength::analyze('MyP@ssw0rd!');
 *   // ['score' => 78, 'label' => 'strong', 'suggestions' => [...], 'passed' => true]
 *
 *   PasswordStrength::check('weak', 60); // throws \InvalidArgumentException
 */
class PasswordStrength
{
    // Score thresholds
    public const VERY_WEAK  = 0;
    public const WEAK       = 20;
    public const FAIR       = 40;
    public const STRONG     = 60;
    public const VERY_STRONG = 80;

    // Labels
    private const LABELS = [
        self::VERY_STRONG => 'very_strong',
        self::STRONG      => 'strong',
        self::FAIR        => 'fair',
        self::WEAK        => 'weak',
        self::VERY_WEAK   => 'very_weak',
    ];

    // Common weak passwords (top-100 subset)
    private const COMMON = [
        'password', '123456', '12345678', 'qwerty', 'abc123', 'monkey',
        'letmein', 'dragon', '111111', 'baseball', 'iloveyou', 'master',
        'sunshine', 'ashley', 'bailey', 'passw0rd', 'shadow', '123123',
        'superman', 'michael', 'qwerty123', 'password1', 'admin', 'welcome',
        'login', 'hello', 'football', 'starwars', 'trustno1', 'passw0rd!',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Analyse a password and return a full result array.
     *
     * @return array{score:int, label:string, suggestions:string[], passed:bool}
     */
    public static function analyze(string $password, int $minScore = 0): array
    {
        $score       = self::score($password);
        $label       = self::label($score);
        $suggestions = self::suggestions($password, $score);

        return [
            'score'       => $score,
            'label'       => $label,
            'suggestions' => $suggestions,
            'passed'      => $score >= $minScore,
        ];
    }

    /**
     * Score a password (0–100).
     */
    public static function score(string $password): int
    {
        if ($password === '') return 0;

        $score = 0;
        $len   = mb_strlen($password);

        // --- Length bonus ---
        $score += match (true) {
            $len >= 20 => 25,
            $len >= 16 => 20,
            $len >= 12 => 15,
            $len >= 10 => 10,
            $len >= 8  => 5,
            default    => 0,
        };

        // --- Character variety ---
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[\W_]/', $password)) $score += 15;  // symbols

        // --- Diversity bonus (multiple char classes) ---
        $classes = 0;
        if (preg_match('/[a-z]/', $password)) $classes++;
        if (preg_match('/[A-Z]/', $password)) $classes++;
        if (preg_match('/[0-9]/', $password)) $classes++;
        if (preg_match('/[\W_]/', $password)) $classes++;

        $score += ($classes - 1) * 5;   // 0, 5, 10, 15 bonus

        // --- Entropy estimate (unique chars ratio) ---
        $unique = count(array_unique(str_split($password)));
        if ($unique / $len >= 0.75) $score += 10;
        elseif ($unique / $len >= 0.5) $score += 5;

        // --- Penalties ---

        // Common password
        if (in_array(strtolower($password), self::COMMON, true)) {
            $score = (int) ($score * 0.1);
        }

        // All same character
        if (preg_match('/^(.)\1+$/', $password)) {
            $score = (int) ($score * 0.1);
        }

        // Sequential characters (abc, 123)
        if (self::hasSequential($password)) $score -= 10;

        // Keyboard walks (qwerty, asdf)
        if (self::hasKeyboardWalk($password)) $score -= 10;

        return max(0, min(100, $score));
    }

    /**
     * Get a human-readable label for a given score.
     */
    public static function label(int $score): string
    {
        foreach (self::LABELS as $threshold => $label) {
            if ($score >= $threshold) return $label;
        }
        return 'very_weak';
    }

    /**
     * Return improvement suggestions for a password.
     *
     * @return string[]
     */
    public static function suggestions(string $password, ?int $score = null): array
    {
        $score ??= self::score($password);
        $tips  = [];
        $len   = mb_strlen($password);

        if ($len < 12)                              $tips[] = 'Use at least 12 characters.';
        if (!preg_match('/[A-Z]/', $password))      $tips[] = 'Add uppercase letters (A–Z).';
        if (!preg_match('/[a-z]/', $password))      $tips[] = 'Add lowercase letters (a–z).';
        if (!preg_match('/[0-9]/', $password))      $tips[] = 'Include at least one digit (0–9).';
        if (!preg_match('/[\W_]/', $password))      $tips[] = 'Include at least one symbol (!@#$…).';
        if (in_array(strtolower($password), self::COMMON, true))
                                                    $tips[] = 'Avoid common passwords.';
        if (self::hasSequential($password))         $tips[] = 'Avoid sequential characters (abc, 123).';
        if (self::hasKeyboardWalk($password))       $tips[] = 'Avoid keyboard patterns (qwerty, asdf).';
        if ($score >= self::VERY_STRONG)            $tips = [];  // perfect — no tips

        return $tips;
    }

    /**
     * Assert the password meets a minimum score; throw if it doesn't.
     *
     * @throws \InvalidArgumentException
     */
    public static function check(string $password, int $minScore = self::STRONG): void
    {
        $result = self::analyze($password, $minScore);
        if (!$result['passed']) {
            $tips = implode(' ', $result['suggestions']);
            throw new \InvalidArgumentException(
                "Password too weak (score {$result['score']}/{$minScore}). {$tips}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private static function hasSequential(string $password): bool
    {
        $lower = strtolower($password);
        for ($i = 0; $i < mb_strlen($lower) - 2; $i++) {
            $a = ord($lower[$i]);
            $b = ord($lower[$i + 1]);
            $c = ord($lower[$i + 2]);
            // ascending or descending sequence
            if (($b === $a + 1 && $c === $b + 1) || ($b === $a - 1 && $c === $b - 1)) {
                return true;
            }
        }
        return false;
    }

    private static function hasKeyboardWalk(string $password): bool
    {
        $walks = ['qwerty', 'asdfgh', 'zxcvbn', 'qwertz', 'azerty', '12345', '09876'];
        $lower = strtolower($password);
        foreach ($walks as $walk) {
            if (str_contains($lower, $walk)) return true;
            if (str_contains($lower, strrev($walk))) return true;
        }
        return false;
    }
}
