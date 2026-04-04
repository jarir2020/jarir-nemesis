<?php
declare(strict_types=1);

namespace Nemesis\Console;

class Schedule {
    protected $events = [];

    public function call($callback) {
        $event = new Event($callback);
        $this->events[] = $event;
        return $event;
    }

    public function command($signature, $arguments = []) {
        $event = new Event(function() use ($signature, $arguments) {
            // Logic to run existing CLI commands
            global $argv;
            $oldArgv = $argv;
            $argv = array_merge(['nemesis', $signature], $arguments);
            require __DIR__ . '/../../nemesis';
            $argv = $oldArgv;
        });
        $this->events[] = $event;
        return $event;
    }

    public function run() {
        foreach ($this->events as $event) {
            if ($event->isDue()) {
                $event->run();
            }
        }
    }
}
