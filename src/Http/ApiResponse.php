<?php
declare(strict_types=1);

namespace Nemesis\Http;

class ApiResponse {
    
    /**
     * Send a success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Send an error response
     */
    public static function error($message = 'Error', $code = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return self::json($response, $code);
    }

    /**
     * Send a validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        return self::error($message, 422, $errors);
    }

    /**
     * Send a not found response
     */
    public static function notFound($message = 'Resource not found') {
        return self::error($message, 404);
    }

    /**
     * Send an unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        return self::error($message, 401);
    }

    /**
     * Send a forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        return self::error($message, 403);
    }

    /**
     * Send a server error response
     */
    public static function serverError($message = 'Internal server error') {
        return self::error($message, 500);
    }

    /**
     * Send a custom JSON response
     */
    public static function json($data, $code = 200, ?bool $pretty = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (self::shouldPrettyJson($pretty)) {
            $options |= JSON_PRETTY_PRINT;
        }
        echo json_encode($data, $options);
        exit;
    }

    /**
     * Send a paginated response
     */
    public static function paginated($paginator, $message = 'Success') {
        $data = $paginator->toArray();
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data['data'],
            'meta' => $data['meta']
        ], 200);
    }

    protected static function shouldPrettyJson(?bool $override = null): bool
    {
        if ($override !== null) {
            return $override;
        }

        if (function_exists('config')) {
            return (bool) \config('api.pretty_json', \config('app.json_pretty', true));
        }

        if (function_exists('env')) {
            return (bool) \env('APP_JSON_PRETTY', true);
        }

        return true;
    }
}
