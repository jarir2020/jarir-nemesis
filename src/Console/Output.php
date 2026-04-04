<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Console I/O | Created: 2026-04-03

namespace Nemesis\Console;

/**
 * Typed CLI output — coloured messages, tables, progress bars, interactive prompts.
 *
 * Usage inside a Command::handle():
 *   $this->output->info('Migrating...');
 *   $this->output->success('Done!');
 *   $this->output->error('Failed.');
 *   $this->output->warn('Deprecated.');
 *   $this->output->table(['Name', 'Email'], $rows);
 *   $this->output->progressBar(100, function(int $i) { ... });
 *   $name = $this->output->ask('Your name?');
 *   $ok   = $this->output->confirm('Continue?');
 *   $drv  = $this->output->choice('Driver?', ['mysql', 'pgsql', 'sqlite']);
 */
class Output
{
    // ANSI colour codes
    private const RESET   = "\033[0m";
    private const GREEN   = "\033[32m";
    private const YELLOW  = "\033[33m";
    private const RED     = "\033[31m";
    private const CYAN    = "\033[36m";
    private const BOLD    = "\033[1m";

    private bool $colours;

    public function __construct(bool $colours = true)
    {
        // Disable colours on Windows without ANSICON / on non-TTY streams
        $this->colours = $colours && (DIRECTORY_SEPARATOR !== '\\' || getenv('ANSICON') !== false);
    }

    // -------------------------------------------------------------------------
    // Message types
    // -------------------------------------------------------------------------

    public function info(string $message): void
    {
        $this->writeln($this->colour(self::CYAN, $message));
    }

    public function success(string $message): void
    {
        $this->writeln($this->colour(self::GREEN, $message));
    }

    public function warn(string $message): void
    {
        $this->writeln($this->colour(self::YELLOW, "[WARN] {$message}"));
    }

    public function error(string $message): void
    {
        $this->writeln($this->colour(self::RED, "[ERROR] {$message}"), STDERR);
    }

    public function line(string $message = ''): void
    {
        $this->writeln($message);
    }

    public function newLine(int $count = 1): void
    {
        echo str_repeat(PHP_EOL, $count);
    }

    // -------------------------------------------------------------------------
    // Tables
    // -------------------------------------------------------------------------

    /**
     * @param  list<string>        $headers
     * @param  list<list<string>>  $rows
     */
    public function table(array $headers, array $rows): void
    {
        $cols = count($headers);
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $line = '+' . implode('+', array_map(fn(int $w) => str_repeat('-', $w + 2), $widths)) . '+';
        $this->writeln($line);

        // Header
        $headerRow = '|';
        foreach ($headers as $i => $h) {
            $headerRow .= ' ' . $this->colour(self::BOLD, str_pad($h, $widths[$i])) . ' |';
        }
        $this->writeln($headerRow);
        $this->writeln($line);

        // Rows
        foreach ($rows as $row) {
            $r = '|';
            for ($i = 0; $i < $cols; $i++) {
                $r .= ' ' . str_pad((string) ($row[$i] ?? ''), $widths[$i]) . ' |';
            }
            $this->writeln($r);
        }
        $this->writeln($line);
    }

    // -------------------------------------------------------------------------
    // Progress bar
    // -------------------------------------------------------------------------

    /**
     * Simple progress bar.
     *
     * @param  int      $total   Total number of steps.
     * @param  callable $work    fn(int $current): void — called for each step.
     *                           Call advance() inside $work if you need manual control.
     */
    public function progressBar(int $total, callable $work): void
    {
        $done = 0;
        $this->drawProgress($done, $total);

        for ($i = 1; $i <= $total; $i++) {
            $work($i);
            $done = $i;
            $this->drawProgress($done, $total);
        }

        echo PHP_EOL;
    }

    // -------------------------------------------------------------------------
    // Interactive prompts
    // -------------------------------------------------------------------------

    public function ask(string $question, string $default = ''): string
    {
        $prompt = $this->colour(self::CYAN, $question);
        if ($default !== '') $prompt .= " [{$default}]";
        echo $prompt . ': ';

        $answer = trim((string) fgets(STDIN));
        return $answer !== '' ? $answer : $default;
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $hint   = $default ? '[Y/n]' : '[y/N]';
        $prompt = $this->colour(self::YELLOW, $question) . " {$hint}: ";
        echo $prompt;

        $answer = strtolower(trim((string) fgets(STDIN)));
        if ($answer === '') return $default;
        return in_array($answer, ['y', 'yes'], true);
    }

    /**
     * @param list<string> $choices
     */
    public function choice(string $question, array $choices, int|string $default = 0): string
    {
        $this->writeln($this->colour(self::CYAN, $question));
        foreach ($choices as $i => $choice) {
            $this->writeln("  [{$i}] {$choice}");
        }
        echo "Choice [{$default}]: ";

        $answer = trim((string) fgets(STDIN));
        if ($answer === '') $answer = (string) $default;

        // Accept index or value
        if (isset($choices[(int) $answer])) return $choices[(int) $answer];
        if (in_array($answer, $choices, true)) return $answer;

        return $choices[(int) $default] ?? $choices[0];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param resource $stream */
    private function writeln(string $message, mixed $stream = STDOUT): void
    {
        fwrite($stream, $message . PHP_EOL);
    }

    private function colour(string $code, string $text): string
    {
        if (!$this->colours) return $text;
        return $code . $text . self::RESET;
    }

    private function drawProgress(int $done, int $total): void
    {
        $pct   = $total > 0 ? (int) round($done / $total * 100) : 100;
        $bar   = str_repeat('=', (int) ($pct / 2)) . str_repeat(' ', 50 - (int) ($pct / 2));
        echo "\r[{$bar}] {$pct}% ({$done}/{$total})";
    }
}
