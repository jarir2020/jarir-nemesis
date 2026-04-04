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
    public static function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
}
