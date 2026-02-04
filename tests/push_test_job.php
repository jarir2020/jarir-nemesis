<?php
require_once __DIR__ . '/index.php';

use Nemesis\Queue\Queue;
use App\Jobs\TestLogJob;

echo "Pushing job to queue...\n";
Queue::push(new TestLogJob());
echo "Job pushed successfully.\n";
