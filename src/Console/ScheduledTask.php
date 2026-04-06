<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 18 — Task Scheduler: ScheduledTask | 2026-04-06

namespace Nemesis\Console;

/**
 * ScheduledTask — fluent frequency + condition builder.
 *
 * Chaining example:
 *   $scheduler->call(fn() => doWork())
 *             ->dailyAt('08:00')
 *             ->between('08:00', '18:00')
 *             ->name('morning-work')
 *             ->description('Runs every morning during business hours');
 */
class ScheduledTask
{
    private string  $expression  = '* * * * *';
    private ?string $name        = null;
    private ?string $description = null;
    /** @var list<\Closure> */
    private array   $filters     = [];  // task runs only when ALL return true
    /** @var list<\Closure> */
    private array   $rejects     = [];  // task is skipped when ANY returns true
    /** Between/unlessBetween stored for testable DateTime-aware checks */
    /** @var list<array{start:string,end:string,mode:string}> */
    private array   $timeWindows = [];

    public function __construct(private readonly \Closure $callback) {}

    // -------------------------------------------------------------------------
    // Frequency
    // -------------------------------------------------------------------------

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static          { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static      { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static       { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static   { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static    { return $this->cron('*/30 * * * *'); }
    public function hourly(): static                { return $this->cron('0 * * * *'); }
    public function hourlyAt(int $minute): static   { return $this->cron("{$minute} * * * *"); }

    public function daily(): static                 { return $this->cron('0 0 * * *'); }
    public function dailyAt(string $time): static
    {
        $parts = explode(':', $time, 2);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $this->cron("{$m} {$h} * * *");
    }

    public function twiceDaily(int $first = 1, int $second = 13): static
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    public function weekly(): static               { return $this->cron('0 0 * * 0'); }
    public function weeklyOn(int $day, string $time = '0:0'): static
    {
        $parts = explode(':', $time, 2);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $this->cron("{$m} {$h} * * {$day}");
    }

    public function monthly(): static              { return $this->cron('0 0 1 * *'); }
    public function monthlyOn(int $day = 1, string $time = '0:0'): static
    {
        $parts = explode(':', $time, 2);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $this->cron("{$m} {$h} {$day} * *");
    }

    public function quarterly(): static            { return $this->cron('0 0 1 1,4,7,10 *'); }
    public function yearly(): static               { return $this->cron('0 0 1 1 *'); }
    public function yearlyOn(int $month = 1, int $day = 1, string $time = '0:0'): static
    {
        $parts = explode(':', $time, 2);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $this->cron("{$m} {$h} {$day} {$month} *");
    }

    // Weekday helpers — sets both time (midnight) and day-of-week constraint
    public function weekdays(): static  { return $this->cron('0 0 * * 1-5'); }
    public function weekends(): static  { return $this->cron('0 0 * * 6,0'); }
    public function mondays(): static   { return $this->cron('0 0 * * 1'); }
    public function tuesdays(): static  { return $this->cron('0 0 * * 2'); }
    public function wednesdays(): static { return $this->cron('0 0 * * 3'); }
    public function thursdays(): static { return $this->cron('0 0 * * 4'); }
    public function fridays(): static   { return $this->cron('0 0 * * 5'); }
    public function saturdays(): static { return $this->cron('0 0 * * 6'); }
    public function sundays(): static   { return $this->cron('0 0 * * 0'); }

    // -------------------------------------------------------------------------
    // Time-window conditions (DateTime-aware)
    // -------------------------------------------------------------------------

    /**
     * Only run if current time is within [$start, $end] (e.g. '08:00', '17:00').
     */
    public function between(string $start, string $end): static
    {
        $this->timeWindows[] = ['start' => $start, 'end' => $end, 'mode' => 'allow'];
        return $this;
    }

    /**
     * Skip if current time is within [$start, $end].
     */
    public function unlessBetween(string $start, string $end): static
    {
        $this->timeWindows[] = ['start' => $start, 'end' => $end, 'mode' => 'reject'];
        return $this;
    }

    /**
     * Add a custom filter — task only runs when callable returns true.
     * The callable receives the current \DateTimeInterface as its first argument.
     */
    public function when(callable $condition): static
    {
        $this->filters[] = \Closure::fromCallable($condition);
        return $this;
    }

    /**
     * Add a custom reject — task is skipped when callable returns true.
     */
    public function skip(callable $condition): static
    {
        $this->rejects[] = \Closure::fromCallable($condition);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Meta
    // -------------------------------------------------------------------------

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function description(string $desc): static
    {
        $this->description = $desc;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    public function isDue(\DateTimeInterface $now = null): bool
    {
        $dt = $now ?? new \DateTime();

        if (!$this->matchesCron($this->expression, $dt)) return false;

        // Time-window checks (DateTime-aware)
        foreach ($this->timeWindows as $window) {
            $inRange = $this->timeIsInRange($window['start'], $window['end'], $dt);
            if ($window['mode'] === 'allow' && !$inRange) return false;
            if ($window['mode'] === 'reject' && $inRange) return false;
        }

        // Custom filters
        foreach ($this->filters as $filter) {
            if (!$filter($dt)) return false;
        }

        // Custom rejects
        foreach ($this->rejects as $reject) {
            if ($reject($dt)) return false;
        }

        return true;
    }

    public function run(): void
    {
        ($this->callback)();
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getExpression(): string    { return $this->expression; }
    public function getName(): ?string         { return $this->name; }
    public function getDescription(): ?string  { return $this->description; }

    // -------------------------------------------------------------------------
    // Cron expression parser
    // -------------------------------------------------------------------------

    private function matchesCron(string $expr, \DateTimeInterface $dt): bool
    {
        $parts = explode(' ', trim($expr));
        if (count($parts) !== 5) return false;

        $current = [
            (int) $dt->format('i'), // minute  [0]
            (int) $dt->format('G'), // hour    [1]
            (int) $dt->format('j'), // dom     [2]
            (int) $dt->format('n'), // month   [3]
            (int) $dt->format('w'), // dow     [4]
        ];

        foreach ($parts as $i => $part) {
            if (!$this->matchField($part, $current[$i])) return false;
        }
        return true;
    }

    private function matchField(string $field, int $value): bool
    {
        if ($field === '*') return true;

        // List: 1,3,5  (each segment processed independently)
        foreach (explode(',', $field) as $segment) {
            if ($this->matchSegment(trim($segment), $value)) return true;
        }
        return false;
    }

    private function matchSegment(string $segment, int $value): bool
    {
        // Range + step: 1-5/2
        if (preg_match('/^(\d+)-(\d+)\/(\d+)$/', $segment, $m)) {
            if ($value < (int) $m[1] || $value > (int) $m[2]) return false;
            return (int) $m[3] > 0 && ($value - (int) $m[1]) % (int) $m[3] === 0;
        }

        // Wildcard step: */5
        if (preg_match('/^\*\/(\d+)$/', $segment, $m)) {
            $step = (int) $m[1];
            return $step > 0 && $value % $step === 0;
        }

        // Range: 1-5
        if (preg_match('/^(\d+)-(\d+)$/', $segment, $m)) {
            return $value >= (int) $m[1] && $value <= (int) $m[2];
        }

        // Literal integer
        return is_numeric($segment) && (int) $segment === $value;
    }

    private function timeIsInRange(string $start, string $end, \DateTimeInterface $dt): bool
    {
        $now = (int) $dt->format('Hi'); // e.g. 0830
        $s   = (int) str_replace(':', '', $start);
        $e   = (int) str_replace(':', '', $end);
        return $now >= $s && $now <= $e;
    }
}
