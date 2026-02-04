<?php
namespace App\Jobs;

use Nemesis\Queue\Job;

class TestLogJob extends Job {
    public function handle() {
        file_put_contents(__DIR__ . '/../../storage/framework/queue_test.log', "Job Processed at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }
}
