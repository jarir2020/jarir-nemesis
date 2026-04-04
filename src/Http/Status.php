<?php
declare(strict_types=1);

namespace Nemesis\Http;

class Status {
    public static function ok() { http_response_code(200); }
    public static function created() { http_response_code(201); }
    public static function noContent() { http_response_code(204); }
    public static function badRequest() { http_response_code(400); }
    public static function unauthorized() { http_response_code(401); }
    public static function forbidden() { http_response_code(403); }
    public static function notFound() { http_response_code(404); }
    public static function methodNotAllowed() { http_response_code(405); }
    public static function unprocessable() { http_response_code(422); }
    public static function internalError() { http_response_code(500); }
    public static function teapot() { http_response_code(418); }
    // ... Any other status can be set via http_response_code($code)
    public static function set($code) { http_response_code($code); }
}
