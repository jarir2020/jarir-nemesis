<?php
namespace Nemesis\Helpers;

class Helpers {
    /**
     * Returns the hostname.
     */
    public static function host() {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /**
     * Returns the absolute path to the public directory or a subdirectory.
     *
     * @param string $subdirectory Optional subdirectory within the public folder.
     * @return string Absolute path.
     */
    public static function publicPath($subdirectory = '') {
        $basePath = realpath(__DIR__ . '/../../public');
        return $subdirectory ? $basePath . DIRECTORY_SEPARATOR . trim($subdirectory, DIRECTORY_SEPARATOR) : $basePath;
    }

    // Get the absolute path to the storage directory
    public static function storagePath(string $path = ''): string {
        return __DIR__ . '/../../storage/' . ltrim($path, '/');
    }
    
    // Get the absolute path to the root directory
    public static function basePath(string $path = ''): string {
        return __DIR__ . '/../../' . ltrim($path, '/');
    }

    // Generate a full URL for a given path
    public static function url(string $path = ''): string {
        $host = self::host();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        return 'http://' . $host . $base . '/' . ltrim($path, '/');
    }
    
    // Redirect to a given URL
    public static function redirect(string $url) {
        header("Location: " . $url);
        exit();
    }

    // Get the current request method (GET, POST, etc.)
    public static function requestMethod(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    // Get a specific query parameter from the URL
    public static function query(string $key, string $default = null): ?string {
        return $_GET[$key] ?? $default;
    }
    
    // Get all query parameters
    public static function allQuery(): array {
        return $_GET;
    }
    
    // Get the raw POST data
    public static function rawInput(): string {
        return file_get_contents('php://input');
    }

    // Convert a string to snake_case
    public static function toSnakeCase(string $str): string {
          return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($str)));
    }
    
     // Convert a string to camelCase
    public static function toCamelCase(string $str): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }
    
    // Generate a random string of specified length
    public static function randomString(int $length = 16): string {
         return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }
    public static function json(array $data, int $statusCode = 200, bool $pretty = false): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $options = $pretty ? JSON_PRETTY_PRINT : 0;
        echo json_encode($data, $options);
        exit;
    }

    // Return a plain text response
    public static function text(string $message, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: text/plain');
        echo $message;
        exit();
    }
    // Check if a key exists in an array
    public static function arrayKeyExists($key, array $array): bool {
        return array_key_exists($key, $array);
    }

    // Flatten a multi-dimensional array
    public static function flattenArray(array $array): array {
        $result = [];
        array_walk_recursive($array, function($a) use (&$result) {
            $result[] = $a;
        });
        return $result;
    }
    public static function getInput(): ?array {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? null;
    }

    public static function passwordVerify(string $password, string $hashedPassword): bool {
        return password_verify($password, $hashedPassword);
    }

    public static function passwordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT);
    }

}
