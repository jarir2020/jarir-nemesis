<?php
namespace Nemesis\Http;

class Response {
    public static function json($data, $status = 200, $headers = []) {
        header('Content-Type: application/json');
        http_response_code($status);
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        echo json_encode($data);
        exit;
    }

    public static function text($content, $status = 200) {
        http_response_code($status);
        echo $content;
        exit;
    }

    public static function redirect($url) {
        header("Location: $url");
        exit;
    }
}
