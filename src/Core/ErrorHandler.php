<?php

namespace Nemesis\Core;

use JarirAhmed\HTTPResponse\HTTPResponse;

class ErrorHandler {

    public static function handleException($exception) {
        \Nemesis\Core\Log::error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        if (PHP_SAPI === 'cli') {
            echo "\nException: " . $exception->getMessage() . "\n";
            echo "Trace: " . $exception->getTraceAsString() . "\n";
            exit(1);
        }

        if ($exception instanceof \Nemesis\Core\ValidationException) {
            http_response_code(422);
            echo json_encode([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $exception->getErrors(),
            ]);
            exit;
        }

        // Send a JSON response with the error details
        HTTPResponse::internalServerError();
        echo json_encode([
            'error' => true,
            'message' => 'Internal Server Error',
            'details' => $exception->getMessage(),
        ]);
        exit;
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        \Nemesis\Core\Log::error("PHP Error [$errno]: $errstr", [
            'file' => $errfile,
            'line' => $errline
        ]);

        if (PHP_SAPI === 'cli') {
            echo "\nError [$errno]: $errstr in $errfile on line $errline\n";
            exit(1);
        }

        // Send a JSON response with the error details
        HTTPResponse::internalServerError();
        echo json_encode([
            'error' => true,
            'message' => 'An error occurred',
            'details' => $errstr,
        ]);
        exit;
    }
}
