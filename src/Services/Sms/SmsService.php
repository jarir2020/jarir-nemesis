<?php
declare(strict_types=1);

namespace Nemesis\Services\Sms;

class SmsService {
    protected $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../../../storage/logs/sms.log';
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public function send($to, $message) {
        // Log Driver Implementation (Mock)
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] Sending SMS to {$to}: {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        return true;
    }

    public function getLogs() {
        if (file_exists($this->logFile)) {
            return file_get_contents($this->logFile);
        }
        return '';
    }

    public function clearLogs() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
}
