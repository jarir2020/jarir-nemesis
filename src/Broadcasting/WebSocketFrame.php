<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: WebSocket Frame Codec (RFC 6455) | 2026-04-06

namespace Nemesis\Broadcasting;

/**
 * WebSocketFrame — RFC 6455 frame encoder and decoder.
 *
 * Opcodes:
 *   0x0  Continuation
 *   0x1  Text
 *   0x2  Binary
 *   0x8  Close
 *   0x9  Ping
 *   0xA  Pong
 *
 * Frame structure:
 *   Byte 0: FIN(1) RSV1(1) RSV2(1) RSV3(1) OPCODE(4)
 *   Byte 1: MASK(1) PAYLOAD_LEN(7)
 *   [2-9]:  Extended length (if len == 126 → 2 bytes, 127 → 8 bytes)
 *   [N-N+3]: Masking key (4 bytes, if MASK=1)
 *   [N+4…]: Payload
 */
class WebSocketFrame
{
    public const OP_CONTINUATION = 0x0;
    public const OP_TEXT         = 0x1;
    public const OP_BINARY       = 0x2;
    public const OP_CLOSE        = 0x8;
    public const OP_PING         = 0x9;
    public const OP_PONG         = 0xA;

    // -------------------------------------------------------------------------
    // Encode (server → client, no masking needed from server side)
    // -------------------------------------------------------------------------

    /**
     * Encode a text payload into a WebSocket frame.
     * Server frames are NOT masked (RFC 6455 §5.1).
     */
    public static function encode(string $payload, int $opcode = self::OP_TEXT): string
    {
        $len    = strlen($payload);
        $frame  = chr(0x80 | $opcode); // FIN=1, opcode

        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }

        return $frame . $payload;
    }

    /** Encode a JSON-serializable array as a text frame. */
    public static function encodeJson(array $data): string
    {
        return self::encode((string) json_encode($data));
    }

    /** Encode a Pong frame (response to Ping). */
    public static function pong(string $pingPayload = ''): string
    {
        return self::encode($pingPayload, self::OP_PONG);
    }

    /** Encode a Ping frame. */
    public static function ping(string $payload = ''): string
    {
        return self::encode($payload, self::OP_PING);
    }

    /**
     * Encode a Close frame.
     *
     * @param int    $code   Close code (1000 = normal close)
     * @param string $reason Human-readable reason
     */
    public static function close(int $code = 1000, string $reason = ''): string
    {
        $payload = pack('n', $code) . $reason;
        return self::encode($payload, self::OP_CLOSE);
    }

    // -------------------------------------------------------------------------
    // Decode (client → server, clients MUST mask)
    // -------------------------------------------------------------------------

    /**
     * Decode one WebSocket frame from a binary string.
     *
     * @return array{
     *   opcode:  int,
     *   fin:     bool,
     *   masked:  bool,
     *   payload: string,
     *   length:  int,
     *   raw_len: int,   // total bytes consumed from input
     * }|null   null if data is incomplete / invalid
     */
    public static function decode(string $data): ?array
    {
        if (strlen($data) < 2) return null;

        $byte0  = ord($data[0]);
        $byte1  = ord($data[1]);

        $fin    = (bool) ($byte0 & 0x80);
        $opcode = $byte0 & 0x0F;
        $masked = (bool) ($byte1 & 0x80);
        $payLen = $byte1 & 0x7F;

        $offset = 2;

        // Extended payload length
        if ($payLen === 126) {
            if (strlen($data) < $offset + 2) return null;
            $payLen  = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payLen === 127) {
            if (strlen($data) < $offset + 8) return null;
            $payLen  = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }

        // Masking key
        $maskKey = '';
        if ($masked) {
            if (strlen($data) < $offset + 4) return null;
            $maskKey = substr($data, $offset, 4);
            $offset += 4;
        }

        // Payload
        if (strlen($data) < $offset + $payLen) return null;
        $payload = substr($data, $offset, $payLen);

        // Unmask
        if ($masked && $maskKey !== '') {
            $payload = self::unmask($payload, $maskKey);
        }

        return [
            'opcode'  => $opcode,
            'fin'     => $fin,
            'masked'  => $masked,
            'payload' => $payload,
            'length'  => $payLen,
            'raw_len' => $offset + $payLen,
        ];
    }

    /** Decode payload as JSON array. Returns null on failure. */
    public static function decodeJson(string $data): ?array
    {
        $frame = self::decode($data);
        if ($frame === null || $frame['opcode'] !== self::OP_TEXT) return null;

        $decoded = json_decode($frame['payload'], true);
        return is_array($decoded) ? $decoded : null;
    }

    // -------------------------------------------------------------------------
    // Handshake
    // -------------------------------------------------------------------------

    /**
     * Parse the HTTP upgrade request headers from raw bytes.
     *
     * @return array<string, string>  Lowercase header name → value
     */
    public static function parseHandshakeHeaders(string $rawRequest): array
    {
        $headers = [];
        $lines   = explode("\r\n", $rawRequest);

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);
            if ($line === '') break;
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Build the HTTP 101 Switching Protocols response for a WebSocket handshake.
     *
     * @param string $clientKey  Value of the Sec-WebSocket-Key header
     */
    public static function buildHandshakeResponse(string $clientKey): string
    {
        $acceptKey = base64_encode(
            sha1($clientKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        return "HTTP/1.1 101 Switching Protocols\r\n"
             . "Upgrade: websocket\r\n"
             . "Connection: Upgrade\r\n"
             . "Sec-WebSocket-Accept: {$acceptKey}\r\n"
             . "\r\n";
    }

    /**
     * Compute the expected Sec-WebSocket-Accept value for a given key.
     * Used in tests to verify handshake correctness.
     */
    public static function computeAcceptKey(string $clientKey): string
    {
        return base64_encode(
            sha1($clientKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function unmask(string $payload, string $mask): string
    {
        $unmasked = '';
        $len      = strlen($payload);
        for ($i = 0; $i < $len; $i++) {
            $unmasked .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        return $unmasked;
    }
}
