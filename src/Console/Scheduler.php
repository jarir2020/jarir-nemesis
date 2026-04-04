<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Scheduler | Created: 2026-04-03
// Replaces the global-$argv hack in Schedule.php with a clean command runner.

namespace Nemesis\Console;

/**
 * Scheduler — builds a list of scheduled tasks and runs them.
 *
 * Cron entry:
 *   * * * * * php /path/to/nemesis schedule:run >> /dev/null 2>&1
 *
 * Usage in app/Console/Kernel.php:
 *
 *   public function schedule(Scheduler $scheduler): void
 *   {
 *       $scheduler->command('cache:clear')->daily();
 *       $scheduler->call(fn() => doSomething())->hourly();
 *       $scheduler->command('reports:generate')->cron('0 8 * * 1'); // Every Monday at 8am
 *   }
 */
class Scheduler
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    // -------------------------------------------------------------------------
    // Builder
    // -------------------------------------------------------------------------

    /**
     * Schedule a CLI command by its signature name.
     * The command is executed via the CommandBus (if registered) or by calling
     * the nemesis binary directly as a fallback.
     */
    public function command(string $signature, array $arguments = []): ScheduledTask
    {
        $task = new ScheduledTask(function () use ($signature, $arguments) {
            $bus = CommandBus::getInstance();
            if ($bus->has($signature)) {
                $input  = new Input(array_merge(['nemesis', $signature], $arguments));
                $output = new Output();
                $bus->run($signature, $input, $output);
            } else {
                // Fallback: shell-exec nemesis binary
                $args = implode(' ', array_map('escapeshellarg', $arguments));
                $bin  = escapeshellarg(defined('NEMESIS_BIN') ? NEMESIS_BIN : 'php ' . base_path('nemesis'));
                passthru("{$bin} {$signature} {$args}");
            }
        });
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Schedule an arbitrary callable.
     */
    public function call(callable $callback): ScheduledTask
    {
        $task = new ScheduledTask($callback);
        $this->tasks[] = $task;
        return $task;
    }

    // -------------------------------------------------------------------------
    // Runner
    // -------------------------------------------------------------------------

    /** Execute all tasks that are due now. */
    public function run(): void
    {
        foreach ($this->tasks as $task) {
            if ($task->isDue()) {
                try {
                    $task->run();
                } catch (\Throwable $e) {
                    fwrite(STDERR, "[Scheduler] Error: " . $e->getMessage() . PHP_EOL);
                }
            }
        }
    }

    /** @return list<ScheduledTask> */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}

// =============================================================================
// ScheduledTask — fluent frequency builder
// =============================================================================

class ScheduledTask
{
    private string    $expression = '* * * * *';
    private bool      $runInBackground = false;
    private ?string   $description = null;

    public function __construct(private readonly \Closure $callback) {}

    // -------------------------------------------------------------------------
    // Frequency helpers
    // -------------------------------------------------------------------------

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static         { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static     { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static      { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static  { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static   { return $this->cron('*/30 * * * *'); }
    public function hourly(): static               { return $this->cron('0 * * * *'); }
    public function hourlyAt(int $minute): static  { return $this->cron("{$minute} * * * *"); }
    public function daily(): static                { return $this->cron('0 0 * * *'); }
    public function dailyAt(string $time): static
    {
        [$h, $m] = explode(':', $time, 2) + [0, '0'];
        return $this->cron("{$m} {$h} * * *");
    }
    public function weekly(): static               { return $this->cron('0 0 * * 0'); }
    public function monthly(): static              { return $this->cron('0 0 1 * *'); }
    public function yearly(): static               { return $this->cron('0 0 1 1 *'); }

    public function description(string $desc): static
    {
        $this->description = $desc;
        return $this;
    }

    public function runInBackground(): static
    {
        $this->runInBackground = true;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    public function isDue(): bool
    {
        return $this->matchesCron($this->expression);
    }

    public function run(): void
    {
        ($this->callback)();
    }

    public function getExpression(): string { return $this->expression; }
    public function getDescription(): ?string { return $this->description; }

    // -------------------------------------------------------------------------
    // Cron expression matching
    // -------------------------------------------------------------------------

    private function matchesCron(string $expr): bool
    {
        $parts   = explode(' ', $expr);
        if (count($parts) !== 5) return false;

        $current = [
            (int) date('i'), // minute
            (int) date('G'), // hour
            (int) date('j'), // day of month
            (int) date('n'), // month
            (int) date('w'), // day of week
        ];

        foreach ($parts as $i => $part) {
            if (!$this->matchField($part, $current[$i])) return false;
        }
        return true;
    }

    private function matchField(string $field, int $value): bool
    {
        if ($field === '*') return true;

        // Step: */5
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);
            return $step > 0 && $value % $step === 0;
        }

        // List: 1,3,5
        if (str_contains($field, ',')) {
            return in_array($value, array_map('intval', explode(',', $field)), true);
        }

        // Range: 1-5
        if (str_contains($field, '-')) {
            [$min, $max] = explode('-', $field, 2);
            return $value >= (int) $min && $value <= (int) $max;
        }

        return (int) $field === $value;
    }
}
