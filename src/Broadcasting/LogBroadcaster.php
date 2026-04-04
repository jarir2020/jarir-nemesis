<?php
declare(strict_types=1);

namespace Nemesis\Broadcasting;

class LogBroadcaster implements Broadcaster {
    protected $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../../storage/logs/broadcast.log';
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public function broadcast(array $channels, $event, array $payload = []) {
        $data = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload
        ]);
        
        file_put_contents($this->logFile, $data . PHP_EOL, FILE_APPEND);
    }
}
