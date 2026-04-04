<?php
declare(strict_types=1);

namespace Nemesis\Testing\Concerns;

use Nemesis\Core\Config;

trait MakesHttpRequests {
    
    protected function get($uri, $headers = []) {
        return $this->call('GET', $uri, [], $headers);
    }

    protected function post($uri, $data = [], $headers = []) {
        return $this->call('POST', $uri, $data, $headers);
    }

    protected function put($uri, $data = [], $headers = []) {
        return $this->call('PUT', $uri, $data, $headers);
    }

    protected function delete($uri, $data = [], $headers = []) {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    protected function call($method, $uri, $parameters = [], $headers = []) {
        // This simulates a request against the local application
        // In a real framework, this would mock the Request object and run through the Kernel/Router
        
        $url = Config::get('APP_URL', 'http://localhost') . $uri;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADER, true); // We want headers
        
        if (!empty($parameters)) {
             if ($method === 'GET') {
                $url .= '?' . http_build_query($parameters);
                curl_setopt($ch, CURLOPT_URL, $url);
             } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
             }
        }

        $httpHeaders = [];
        foreach ($headers as $key => $value) {
            $httpHeaders[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        curl_close($ch);

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return new TestResponse($httpCode, $body, $headerText);
    }
}

class TestResponse {
    public $status;
    public $content;
    public $headers;

    public function __construct($status, $content, $headers) {
        $this->status = $status;
        $this->content = $content;
        $this->headers = $headers;
    }

    public function assertStatus($status) {
        if ($this->status !== $status) {
            throw new \Exception("Expected status $status but received {$this->status}. Content: " . substr($this->content, 0, 100));
        }
        return $this;
    }

    public function assertOk() {
        return $this->assertStatus(200);
    }

    public function assertJson($data) {
        $json = json_decode($this->content, true);
        if ($json === null) {
            throw new \Exception("Response was not JSON.");
        }

        foreach ($data as $key => $value) {
            if (!isset($json[$key]) || $json[$key] != $value) {
                throw new \Exception("JSON response does not match expected key '$key'.");
            }
        }
        return $this;
    }
}
