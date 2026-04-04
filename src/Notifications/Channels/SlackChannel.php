<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Notifications | Created: 2026-04-03

namespace Nemesis\Notifications\Channels;

use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationChannelInterface;

/**
 * SlackChannel — posts notification to a Slack Incoming Webhook URL.
 *
 * The notification must implement toSlack() returning:
 *   ['text' => '...', 'channel' => '#general', 'username' => 'Nemesis', 'icon_emoji' => ':bell:']
 *
 * The notifiable should expose a webhook URL via:
 *   - $notifiable->slack_webhook_url property
 *   - $notifiable->routeNotificationForSlack() method
 */
class SlackChannel implements NotificationChannelInterface
{
    private static array $sent = [];
    private static bool  $fake = false;

    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toSlack($notifiable);
        if (empty($payload) || empty($payload['text'])) return;

        $url = $this->resolveWebhookUrl($notifiable);
        if ($url === '') return;

        $body = array_filter([
            'text'       => $payload['text'],
            'channel'    => $payload['channel']    ?? null,
            'username'   => $payload['username']   ?? null,
            'icon_emoji' => $payload['icon_emoji'] ?? null,
            'attachments'=> $payload['attachments'] ?? null,
        ]);

        static::$sent[] = ['url' => $url, 'payload' => $body];

        if (static::$fake) return;

        $this->httpPost($url, $body);
    }

    private function resolveWebhookUrl(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForSlack')) {
            return (string) $notifiable->routeNotificationForSlack();
        }
        return (string) ($notifiable->slack_webhook_url ?? '');
    }

    private function httpPost(string $url, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
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
