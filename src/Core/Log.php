<?php
declare(strict_types=1);

namespace Nemesis\Core;

class Log {
    protected static $logPath;

    protected static function init() {
        if (!self::$logPath) {
            self::$logPath = __DIR__ . '/../../storage/logs';
            if (!is_dir(self::$logPath)) {
                mkdir(self::$logPath, 0755, true);
            }
        }
    }

    public static function info($message, array $context = []) {
        self::write('INFO', $message, $context);
    }

    public static function error($message, array $context = []) {
        self::write('ERROR', $message, $context);
    }

    public static function warning($message, array $context = []) {
        self::write('WARNING', $message, $context);
    }

    protected static function write($level, $message, array $context = []) {
        self::init();
        
        $date = date('Y-m-d H:i:s');
        $fileDate = date('Y-m-d');
        $filename = self::$logPath . "/nemesis-{$fileDate}.log";

        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$date}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        file_put_contents($filename, $logMessage, FILE_APPEND);
    }
}
