<?php
namespace Nemesis\Http;

class Request {
    protected $data;
    protected $headers;

    public function __construct() {
        $this->data = array_merge($_GET, $_POST, $this->getJsonInput());
        $this->headers = $this->getAllHeaders();
    }

    protected function getAllHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    protected function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    public function all() {
        return $this->data;
    }

    public function input($key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    public function header($key, $default = null) {
        $key = str_replace('-', '_', strtoupper($key));
        return $this->headers[$key] ?? $this->headers[str_replace('_', '-', $key)] ?? $default;
    }

    public function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri() {
        return $_SERVER['REQUEST_URI'];
    }

    public function validate(array $rules) {
        $validator = new \Nemesis\Core\Validator();
        if (!$validator->validate($this->data, $rules)) {
            throw new \Nemesis\Core\ValidationException($validator->errors());
        }
        return $this->data;
    }
}
