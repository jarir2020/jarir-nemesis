<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: Server-Sent Events fallback | 2026-04-06

namespace Nemesis\Broadcasting;

/**
 * SseStream — Server-Sent Events (SSE) broadcaster.
 *
 * Use as a fallback when WebSockets are blocked by firewalls or proxies.
 * Clients connect via EventSource (built into all modern browsers).
 *
 * Controller example:
 *   public function stream(Request $request): void
 *   {
 *       $sse = new SseStream();
 *       $sse->headers();        // send HTTP headers
 *       $sse->retry(3000);      // tell client to retry in 3s on disconnect
 *
 *       while (true) {
 *           $payload = Cache::get('broadcast_queue') ?? [];
 *           foreach ($payload as $msg) {
 *               $sse->send($msg['data'], $msg['event'], $msg['id'] ?? null);
 *           }
 *           $sse->keepAlive();
 *           sleep(1);
 *       }
 *   }
 *
 * Client-side (JavaScript):
 *   const source = new EventSource('/broadcasting/stream');
 *   source.addEventListener('OrderUpdated', e => console.log(JSON.parse(e.data)));
 */
class SseStream
{
    private bool $headersSent = false;

    // -------------------------------------------------------------------------
    // HTTP Headers
    // -------------------------------------------------------------------------

    /**
     * Send the required SSE HTTP headers.
     * Must be called before any output.
     */
    public function headers(): void
    {
        if (!$this->headersSent && !headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // disable nginx buffering
            header('Connection: keep-alive');
            $this->headersSent = true;
        }

        // Disable PHP output buffering
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
    }

    // -------------------------------------------------------------------------
    // Event formatting
    // -------------------------------------------------------------------------

    /**
     * Format an SSE event block (does NOT send — use for testing).
     *
     * @param mixed       $data   Data payload (arrays auto-encoded as JSON)
     * @param string|null $event  Event type (browser listens via addEventListener)
     * @param string|null $id     Event ID (browser sends as Last-Event-ID on reconnect)
     */
    public static function format(mixed $data, string $event = null, string $id = null): string
    {
        $output = '';

        if ($id !== null) {
            $output .= "id: {$id}\n";
        }

        if ($event !== null) {
            $output .= "event: {$event}\n";
        }

        $dataStr = is_array($data) || is_object($data)
            ? (string) json_encode($data)
            : (string) $data;

        // Multi-line data: each line gets its own "data:" prefix
        foreach (explode("\n", $dataStr) as $line) {
            $output .= "data: {$line}\n";
        }

        return $output . "\n"; // blank line = end of event block
    }

    /**
     * Send an SSE event to the client.
     */
    public function send(mixed $data, string $event = null, string $id = null): void
    {
        echo self::format($data, $event, $id);
        flush();
    }

    /**
     * Set client reconnect delay in milliseconds.
     */
    public function retry(int $ms): void
    {
        echo "retry: {$ms}\n\n";
        flush();
    }

    /**
     * Send a comment line to keep the connection alive.
     * Browsers automatically reconnect if no data arrives for ~30–45 seconds.
     */
    public function keepAlive(string $comment = 'keep-alive'): void
    {
        echo ": {$comment}\n\n";
        flush();
    }

    /**
     * Build the SSE retry line string (for testing without output).
     */
    public static function formatRetry(int $ms): string
    {
        return "retry: {$ms}\n\n";
    }

    /**
     * Build the SSE keep-alive comment string (for testing without output).
     */
    public static function formatKeepAlive(string $comment = 'keep-alive'): string
    {
        return ": {$comment}\n\n";
    }
}
