<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing;

/**
 * SimpleFaker — zero-dependency fake-data generator for tests.
 * Covers the most common test data needs without requiring fakerphp/faker.
 */
class SimpleFaker
{
    private static array $firstNames = ['Alice','Bob','Charlie','Diana','Eve','Frank','Grace','Hank','Iris','Jack'];
    private static array $lastNames  = ['Smith','Jones','Williams','Brown','Davis','Miller','Wilson','Moore','Taylor','Anderson'];
    private static array $domains    = ['example.com','test.org','fake.net','sample.io','demo.dev'];
    private static array $words      = ['lorem','ipsum','dolor','sit','amet','consectetur','adipiscing','elit','sed','do'];
    private static array $tlds       = ['com','org','net','io','dev','co'];

    // -------------------------------------------------------------------------
    // Personal
    // -------------------------------------------------------------------------

    public function firstName(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    public function email(): string
    {
        return strtolower($this->firstName()) . '.' . mt_rand(1, 9999)
            . '@' . self::$domains[array_rand(self::$domains)];
    }

    public function username(): string
    {
        return strtolower($this->firstName()) . mt_rand(10, 999);
    }

    public function password(int $length = 16): string
    {
        return $this->randomString($length);
    }

    // -------------------------------------------------------------------------
    // Numbers
    // -------------------------------------------------------------------------

    public function numberBetween(int $min = 0, int $max = 1000): int
    {
        return mt_rand($min, $max);
    }

    public function float(int $min = 0, int $max = 100, int $decimals = 2): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }

    public function boolean(): bool
    {
        return (bool) mt_rand(0, 1);
    }

    // -------------------------------------------------------------------------
    // Text
    // -------------------------------------------------------------------------

    public function word(): string
    {
        return self::$words[array_rand(self::$words)];
    }

    public function sentence(int $wordCount = 6): string
    {
        $words = [];
        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $this->word();
        }
        return ucfirst(implode(' ', $words)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $parts = [];
        for ($i = 0; $i < $sentences; $i++) {
            $parts[] = $this->sentence();
        }
        return implode(' ', $parts);
    }

    public function slug(): string
    {
        return $this->word() . '-' . $this->word() . '-' . mt_rand(1, 999);
    }

    // -------------------------------------------------------------------------
    // Internet
    // -------------------------------------------------------------------------

    public function url(): string
    {
        $tld = self::$tlds[array_rand(self::$tlds)];
        return 'https://' . $this->word() . '-' . mt_rand(1, 99) . '.' . $tld;
    }

    public function ipv4(): string
    {
        return mt_rand(1, 254) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
    }

    // -------------------------------------------------------------------------
    // Dates
    // -------------------------------------------------------------------------

    public function dateTimeThisYear(): \DateTime
    {
        $start = mktime(0, 0, 0, 1, 1, (int) date('Y'));
        return new \DateTime('@' . mt_rand($start, time()));
    }

    public function pastDate(int $daysBack = 365): \DateTime
    {
        return new \DateTime('-' . mt_rand(1, $daysBack) . ' days');
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function randomString(int $length = 16): string
    {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }

    public function randomElement(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
