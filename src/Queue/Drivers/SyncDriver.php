<?php

namespace Nemesis\Queue\Drivers;

use Nemesis\Queue\QueueDriver;
use Nemesis\Queue\Job;

class SyncDriver implements QueueDriver {
    public function push(Job $job) {
        return $job->handle();
    }

    public function pop() {
        return null;
    }

    public function delete($id) {}
    public function release($id, $delay = 0) {}
}
