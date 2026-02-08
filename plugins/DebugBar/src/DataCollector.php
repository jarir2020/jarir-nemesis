<?php
namespace Nemesis\Plugins\DebugBar;

use Nemesis\Core\Database;

class DataCollector {
    protected $startTime;
    protected $startMemory;

    public function __construct() {
        $this->startTime = defined('NEMESIS_START') ? NEMESIS_START : microtime(true);
        $this->startMemory = defined('NEMESIS_START_MEMORY') ? NEMESIS_START_MEMORY : memory_get_usage();
    }

    public function collect() {
        return [
            'time' => [
                'start' => $this->startTime,
                'end' => microtime(true),
                'duration' => microtime(true) - $this->startTime,
            ],
            'memory' => [
                'start' => $this->startMemory,
                'end' => memory_get_usage(),
                'peak' => memory_get_peak_usage(),
            ],
            'queries' => Database::getQueryLog(),
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            ],
            'php_version' => PHP_VERSION,
        ];
    }
}
