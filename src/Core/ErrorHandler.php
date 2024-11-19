<?php

namespace Nemesis\Core;

use JarirAhmed\HTTPResponse\HTTPResponse;

class ErrorHandler {

    public static function handleException($exception) {
        // Log the exception to a file or monitoring system
        error_log($exception->getMessage());

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
        // Log the error to a file
        error_log("Error [$errno]: $errstr in $errfile on line $errline");

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
