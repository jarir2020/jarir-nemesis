<?php
declare(strict_types=1);

namespace Nemesis\Console;

class Event {
    protected $callback;
    protected $expression = '* * * * *'; // Default to every minute

    public function __construct($callback) {
        $this->callback = $callback;
    }

    public function everyMinute() {
        $this->expression = '* * * * *';
        return $this;
    }

    public function everyFiveMinutes() {
        $this->expression = '*/5 * * * *';
        return $this;
    }

    public function hourly() {
        $this->expression = '0 * * * *';
        return $this;
    }

    public function daily() {
        $this->expression = '0 0 * * *';
        return $this;
    }

    public function isDue() {
        // Simple mock of cron expression matching
        // In a real framework, we'd use a cron expression parser library.
        // For Nemesis, we'll check if it's "every minute" or do simple time checks.
        if ($this->expression === '* * * * *') return true;

        $currentTime = date('i H d m w');
        $parts = explode(' ', $this->expression);
        $currentParts = explode(' ', $currentTime);

        for ($i = 0; $i < 5; $i++) {
            if ($parts[$i] === '*') continue;
            if (str_starts_with($parts[$i], '*/')) {
                $step = (int) substr($parts[$i], 2);
                if ((int)$currentParts[$i] % $step !== 0) return false;
                continue;
            }
            if ($parts[$i] !== $currentParts[$i]) return false;
        }

        return true;
    }

    public function run() {
        if ($this->callback instanceof \Closure) {
            ($this->callback)();
        }
    }
}
