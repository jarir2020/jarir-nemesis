<?php

namespace Nemesis\Queue;

class Queue {
    protected static $driver;

    protected static function driver() {
        if (self::$driver) return self::$driver;

        $driverName = getenv('QUEUE_DRIVER') ?: 'sync';
        
        switch ($driverName) {
            case 'redis':
                self::$driver = new \Nemesis\Queue\Drivers\RedisDriver();
                break;
            case 'database':
                self::$driver = new \Nemesis\Queue\Drivers\DatabaseDriver();
                break;
            case 'sync':
            default:
                self::$driver = new \Nemesis\Queue\Drivers\SyncDriver();
                break;
        }

        return self::$driver;
    }

    public static function push(Job $job) {
        return self::driver()->push($job);
    }

    public static function pop() {
        return self::driver()->pop();
    }
}
