<?php
declare(strict_types=1);

namespace Nemesis\Queue\Drivers;

use Nemesis\Queue\QueueDriver;
use Nemesis\Queue\Job;

class RedisDriver implements QueueDriver {
    protected $redis;
    protected $queue = 'nemesis_queue';

    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', getenv('REDIS_PORT') ?: 6379);
    }

    public function push(Job $job) {
        return $this->redis->lPush($this->queue, serialize($job));
    }

    public function pop() {
        $payload = $this->redis->rPop($this->queue);
        if (!$payload) return null;

        return [
            'id' => uniqid(),
            'payload' => $payload
        ];
    }

    public function delete($id) {} // Redis rPop effectively deletes
    public function release($id, $delay = 0) {
        // Simple release implementation for Redis
        // Ideally we'd use a sorted set for delayed jobs
    }
}
