<?php
declare(strict_types=1);

namespace Nemesis\Support;

class WebHook {
    public static function dispatch($url, array $data, array $headers = []) {
        $ch = curl_init($url);
        $payload = json_encode($data);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type:application/json'], $headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $status,
            'response' => $response,
            'success' => ($status >= 200 && $status < 300),
            'error' => $error
        ];
    }
}
