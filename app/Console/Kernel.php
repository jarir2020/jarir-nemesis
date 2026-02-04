<?php

namespace App\Console;

use Nemesis\Console\Schedule;

class Kernel {
    public function schedule(Schedule $schedule) {
        $schedule->call(function() {
            error_log("Task Scheduler Heartbeat: " . date('Y-m-d H:i:s'));
        })->everyMinute();

        // Add more tasks here
    }
}
