<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * WebhookChannel — POSTs notification data to an arbitrary HTTP endpoint.
 *
 * The notification must implement toWebhook() returning:
 *   ['url' => 'https://...', 'data' => [...], 'method' => 'POST', 'headers' => [...]]
 */
class WebhookChannel implements NotificationChannelInterface
{
    private static array $sent = [];
    private static bool  $fake = false;

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toWebhook($notifiable);
        if (empty($payload) || empty($payload['url'])) return;

        $url     = $payload['url'];
        $data    = $payload['data']    ?? [];
        $method  = strtoupper($payload['method']  ?? 'POST');
        $headers = $payload['headers'] ?? [];

        static::$sent[] = compact('url', 'data', 'method', 'headers');

        if (static::$fake) return;

        $this->httpSend($url, $method, $data, $headers);
    }

    private function httpSend(string $url, string $method, array $data, array $headers): void
    {
        $json        = json_encode($data, JSON_UNESCAPED_UNICODE);
        $headerLines = ["Content-Type: application/json", "Content-Length: " . strlen($json)];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        $ctx = stream_context_create([
            'http' => [
                'method'  => $method,
                'header'  => implode("\r\n", $headerLines) . "\r\n",
                'content' => $json,
                'timeout' => 5,
            ],
        ]);
        @file_get_contents($url, false, $ctx);
    }

    public static function fake(): void   { static::$fake = true; }
    public static function real(): void   { static::$fake = false; }
    public static function sent(): array  { return static::$sent; }
    public static function reset(): void  { static::$sent = []; static::$fake = false; }
}
