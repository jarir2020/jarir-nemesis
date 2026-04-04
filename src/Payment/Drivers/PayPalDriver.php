<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// PayPalDriver — PayPal Orders v2 API (no SDK dependency).

namespace Nemesis\Payment\Drivers;

use Nemesis\Payment\ChargeResult;
use Nemesis\Payment\PaymentInterface;

/**
 * PayPalDriver — PayPal REST API v2 integration.
 *
 * Config:
 *   PAYPAL_CLIENT_ID      AX…
 *   PAYPAL_CLIENT_SECRET  EK…
 *   PAYPAL_SANDBOX        true/false
 */
class PayPalDriver implements PaymentInterface
{
    private const LIVE_URL    = 'https://api-m.paypal.com';
    private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    private ?string $accessToken = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly bool   $sandbox = true,
    ) {}

    public function charge(int $amountCents, string $token, array $options = []): ChargeResult
    {
        $currency = strtoupper($options['currency'] ?? 'USD');
        $amount   = number_format($amountCents / 100, 2, '.', '');

        // Capture an already-approved order (token = PayPal order ID)
        $response = $this->request('POST', "/v2/checkout/orders/{$token}/capture", []);
        if ($response === null) {
            return ChargeResult::fail('PayPal API unreachable', 'network_error');
        }

        $status = $response['status'] ?? '';
        if ($status !== 'COMPLETED') {
            $msg = $response['message'] ?? "PayPal order status: {$status}";
            return ChargeResult::fail($msg, $response['name'] ?? '', $response);
        }

        $captureId = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? $token;
        return ChargeResult::ok($captureId, $amountCents, $currency, $response);
    }

    public function refund(string $transactionId, ?int $amountCents = null): ChargeResult
    {
        $body = [];
        if ($amountCents !== null) {
            $body = [
                'amount' => [
                    'value'         => number_format($amountCents / 100, 2, '.', ''),
                    'currency_code' => 'USD',
                ],
            ];
        }

        $response = $this->request('POST', "/v2/payments/captures/{$transactionId}/refund", $body);
        if ($response === null) {
            return ChargeResult::fail('PayPal API unreachable', 'network_error');
        }

        if (!empty($response['name']) && isset($response['message'])) {
            return ChargeResult::fail($response['message'], $response['name'], $response);
        }

        $refundId = $response['id'] ?? 'refund_' . bin2hex(random_bytes(4));
        return ChargeResult::ok($refundId, $amountCents ?? 0, 'USD', $response);
    }

    public function webhook(string $payload, string $signature): array
    {
        // PayPal webhook verification requires a separate API call — simplified here.
        $data  = json_decode($payload, true) ?? [];
        $event = $data['event_type'] ?? 'UNKNOWN';
        return ['event' => $event, 'data' => $data['resource'] ?? $data];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    private function accessToken(): ?string
    {
        if ($this->accessToken) return $this->accessToken;

        $creds = base64_encode($this->clientId . ':' . $this->clientSecret);
        $ctx   = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: Basic {$creds}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content'       => 'grant_type=client_credentials',
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->baseUrl() . '/v1/oauth2/token', false, $ctx);
        if ($response === false) return null;

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            $this->accessToken = $data['access_token'] ?? null;
        } catch (\Throwable) {}

        return $this->accessToken;
    }

    private function request(string $method, string $path, array $body): ?array
    {
        $token = $this->accessToken();
        if ($token === null) return null;

        $json    = $body ? json_encode($body) : '';
        $headers = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];
        if ($json) $headers[] = 'Content-Length: ' . strlen($json);

        $ctx = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $json ?: null,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->baseUrl() . $path, false, $ctx);
        if ($response === false) return null;

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}
