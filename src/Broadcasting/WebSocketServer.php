<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: WebSocketServer (RFC 6455, Pusher protocol) | 2026-04-06

namespace Nemesis\Broadcasting;

/**
 * WebSocketServer — event-driven WebSocket server.
 *
 * Features:
 *   - RFC 6455 compliant handshake + frame codec
 *   - Pusher-compatible wire protocol (works with laravel-echo + pusher-js)
 *   - Public, private, presence channel types
 *   - Channel auth via HMAC-SHA256 (Pusher-style)
 *   - Ping/Pong keep-alive
 *   - Clean client disconnect handling
 *
 * Start via CLI:
 *   php nemesis websocket:start
 *   php nemesis websocket:start --host=0.0.0.0 --port=6001
 *
 * Frontend (JavaScript with pusher-js):
 *   const pusher = new Pusher('your-app-key', {
 *       wsHost: 'localhost',
 *       wsPort: 6001,
 *       forceTLS: false,
 *       cluster: 'mt1',
 *       authEndpoint: '/broadcasting/auth',
 *   });
 *   const channel = pusher.subscribe('private-orders');
 *   channel.bind('OrderUpdated', data => console.log(data));
 */
class WebSocketServer implements Broadcaster
{
    /** @var resource|null Server socket */
    private mixed $serverSocket = null;

    /** @var array<string, resource> socketId → stream resource */
    private array $connections = [];

    /** @var array<string, bool> socketId → handshake complete? */
    private array $handshaked = [];

    /** @var array<string, string> socketId → read buffer */
    private array $buffers = [];

    private ChannelManager $channels;

    private string $appKey;
    private string $appSecret;

    public function __construct(
        private string $host      = '127.0.0.1',
        private int    $port      = 6001,
        string         $appKey    = '',
        string         $appSecret = '',
    ) {
        $this->channels  = new ChannelManager();
        $this->appKey    = $appKey    ?: (string) (getenv('WS_APP_KEY')    ?: 'nemesis-key');
        $this->appSecret = $appSecret ?: (string) (getenv('WS_APP_SECRET') ?: 'nemesis-secret');
    }

    // -------------------------------------------------------------------------
    // BroadcasterContract
    // -------------------------------------------------------------------------

    /**
     * Broadcast an event to a channel's subscribers.
     * Can be called externally (e.g. from BroadcastManager::send()).
     */
    public function broadcast(string|array $channels, string $event, array $payload = []): void
    {
        foreach ((array) $channels as $channel) {
            $frame = WebSocketFrame::encodeJson([
                'event'   => $event,
                'data'    => json_encode($payload),
                'channel' => $channel,
            ]);

            foreach ($this->channels->subscribers($channel) as $socketId) {
                if (isset($this->connections[$socketId])) {
                    @fwrite($this->connections[$socketId], $frame);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Server lifecycle
    // -------------------------------------------------------------------------

    public function start(): void
    {
        $address = "tcp://{$this->host}:{$this->port}";
        $ctx     = stream_context_create();

        $this->serverSocket = stream_socket_server(
            $address, $errno, $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $ctx
        );

        if (!$this->serverSocket) {
            throw new \RuntimeException("WebSocket server failed to start on {$address}: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->serverSocket, false);

        echo "[WS] Nemesis WebSocket server listening on ws://{$this->host}:{$this->port}" . PHP_EOL;
        echo "[WS] App key: {$this->appKey}" . PHP_EOL;
        echo "[WS] Press Ctrl+C to stop." . PHP_EOL;

        $this->loop();
    }

    private function loop(): void
    {
        while (true) {
            $read   = array_merge([$this->serverSocket], array_values($this->connections));
            $write  = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 50000) === false) continue;

            foreach ($read as $sock) {
                if ($sock === $this->serverSocket) {
                    $this->acceptConnection();
                } else {
                    $socketId = array_search($sock, $this->connections, true);
                    if ($socketId !== false) {
                        $this->handleRead((string) $socketId, $sock);
                    }
                }
            }
        }
    }

    private function acceptConnection(): void
    {
        $client = @stream_socket_accept($this->serverSocket, 0);
        if (!$client) return;

        stream_set_blocking($client, false);
        $socketId = $this->generateSocketId();

        $this->connections[$socketId] = $client;
        $this->handshaked[$socketId]  = false;
        $this->buffers[$socketId]     = '';

        echo "[WS] Client connected: {$socketId}" . PHP_EOL;
    }

    private function handleRead(string $socketId, mixed $sock): void
    {
        $data = @fread($sock, 8192);

        if ($data === false || $data === '') {
            $this->disconnect($socketId);
            return;
        }

        $this->buffers[$socketId] .= $data;

        if (!$this->handshaked[$socketId]) {
            $this->performHandshake($socketId);
        } else {
            $this->processFrames($socketId);
        }
    }

    private function performHandshake(string $socketId): void
    {
        $raw     = $this->buffers[$socketId];
        $headers = WebSocketFrame::parseHandshakeHeaders($raw);

        $key = $headers['sec-websocket-key'] ?? '';
        if (!$key) return; // incomplete request, wait for more data

        $response = WebSocketFrame::buildHandshakeResponse($key);
        fwrite($this->connections[$socketId], $response);

        $this->handshaked[$socketId]  = true;
        $this->buffers[$socketId]     = ''; // clear HTTP bytes

        // Send Pusher connection established event
        $this->sendToSocket($socketId, [
            'event' => 'pusher:connection_established',
            'data'  => json_encode([
                'socket_id'        => $socketId,
                'activity_timeout' => 120,
            ]),
        ]);

        echo "[WS] Handshake complete: {$socketId}" . PHP_EOL;
    }

    private function processFrames(string $socketId): void
    {
        $buffer = &$this->buffers[$socketId];

        while ($buffer !== '') {
            $frame = WebSocketFrame::decode($buffer);
            if ($frame === null) break; // incomplete frame

            $buffer = substr($buffer, $frame['raw_len']);
            $this->handleFrame($socketId, $frame);
        }
    }

    private function handleFrame(string $socketId, array $frame): void
    {
        switch ($frame['opcode']) {
            case WebSocketFrame::OP_TEXT:
                $this->handleMessage($socketId, $frame['payload']);
                break;

            case WebSocketFrame::OP_PING:
                fwrite($this->connections[$socketId], WebSocketFrame::pong($frame['payload']));
                break;

            case WebSocketFrame::OP_CLOSE:
                fwrite($this->connections[$socketId], WebSocketFrame::close());
                $this->disconnect($socketId);
                break;

            case WebSocketFrame::OP_PONG:
                break; // just acknowledge
        }
    }

    private function handleMessage(string $socketId, string $payload): void
    {
        $msg = json_decode($payload, true);
        if (!is_array($msg)) return;

        $event = $msg['event'] ?? '';
        $data  = is_string($msg['data'] ?? null)
            ? (array) json_decode($msg['data'], true)
            : (array) ($msg['data'] ?? []);

        match ($event) {
            'pusher:subscribe'   => $this->handleSubscribe($socketId, $data),
            'pusher:unsubscribe' => $this->handleUnsubscribe($socketId, $data),
            'pusher:ping'        => $this->sendToSocket($socketId, ['event' => 'pusher:pong', 'data' => '{}']),
            default              => $this->handleClientEvent($socketId, $event, $msg['channel'] ?? '', $data),
        };
    }

    private function handleSubscribe(string $socketId, array $data): void
    {
        $channel = $data['channel'] ?? '';
        if (!$channel) return;

        $ch = new Channel($channel);

        if ($ch->requiresAuth()) {
            $auth     = $data['auth'] ?? '';
            $expected = $this->computeChannelAuth($socketId, $channel);

            if (!hash_equals($expected, $auth)) {
                $this->sendToSocket($socketId, [
                    'event'   => 'pusher:error',
                    'data'    => json_encode(['code' => 4009, 'message' => 'Auth failed']),
                    'channel' => $channel,
                ]);
                return;
            }
        }

        $meta = [];
        if ($ch->isPresence() && isset($data['channel_data'])) {
            $meta = (array) json_decode($data['channel_data'], true);
        }

        $this->channels->subscribe($socketId, $channel, $meta);

        $successEvent = $ch->isPresence()
            ? 'pusher_internal:subscription_succeeded'
            : 'pusher_internal:subscription_succeeded';

        $successData = $ch->isPresence()
            ? ['presence' => ['count' => $this->channels->subscriberCount($channel), 'ids' => [], 'hash' => []]]
            : [];

        $this->sendToSocket($socketId, [
            'event'   => $successEvent,
            'data'    => json_encode($successData),
            'channel' => $channel,
        ]);

        echo "[WS] {$socketId} subscribed to {$channel}" . PHP_EOL;
    }

    private function handleUnsubscribe(string $socketId, array $data): void
    {
        $channel = $data['channel'] ?? '';
        $this->channels->unsubscribe($socketId, $channel);
    }

    private function handleClientEvent(string $socketId, string $event, string $channel, array $data): void
    {
        // Client events on private/presence channels — forward to other subscribers
        if (!str_starts_with($event, 'client-')) return; // only client- prefix allowed
        if (!$this->channels->isSubscribed($socketId, $channel)) return;

        foreach ($this->channels->subscribers($channel) as $otherId) {
            if ($otherId === $socketId) continue; // don't echo back
            $this->sendToSocket($otherId, [
                'event'   => $event,
                'data'    => json_encode($data),
                'channel' => $channel,
            ]);
        }
    }

    private function disconnect(string $socketId): void
    {
        echo "[WS] Client disconnected: {$socketId}" . PHP_EOL;

        $this->channels->unsubscribeAll($socketId);

        if (isset($this->connections[$socketId])) {
            @fclose($this->connections[$socketId]);
        }

        unset(
            $this->connections[$socketId],
            $this->handshaked[$socketId],
            $this->buffers[$socketId],
        );
    }

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    /**
     * Compute the Pusher-style channel auth token.
     * Used by clients to authenticate private/presence channels.
     *
     * Format: "app_key:HMAC-SHA256(socket_id:channel_name, app_secret)"
     */
    public function computeChannelAuth(string $socketId, string $channel, string $channelData = ''): string
    {
        $stringToSign = $channelData
            ? "{$socketId}:{$channel}:{$channelData}"
            : "{$socketId}:{$channel}";

        $signature = hash_hmac('sha256', $stringToSign, $this->appSecret);
        return "{$this->appKey}:{$signature}";
    }

    /**
     * Generate auth response for POST /broadcasting/auth endpoint.
     * Call this from your auth controller.
     */
    public function channelAuthResponse(string $socketId, string $channel, array $userData = []): array
    {
        $ch = new Channel($channel);

        if (!$ch->requiresAuth()) {
            return ['auth' => ''];
        }

        if ($ch->isPresence() && !empty($userData)) {
            $channelData = json_encode([
                'user_id'   => $userData['id'] ?? $socketId,
                'user_info' => $userData,
            ]);
            $auth = $this->computeChannelAuth($socketId, $channel, $channelData);
            return ['auth' => $auth, 'channel_data' => $channelData];
        }

        return ['auth' => $this->computeChannelAuth($socketId, $channel)];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sendToSocket(string $socketId, array $message): void
    {
        if (!isset($this->connections[$socketId])) return;
        $frame = WebSocketFrame::encodeJson($message);
        @fwrite($this->connections[$socketId], $frame);
    }

    private function generateSocketId(): string
    {
        return sprintf('%d.%d', random_int(100000, 999999), random_int(100000, 999999));
    }

    public function getChannelManager(): ChannelManager { return $this->channels; }
    public function getAppKey(): string                 { return $this->appKey; }
    public function connectedCount(): int               { return count($this->connections); }
}
