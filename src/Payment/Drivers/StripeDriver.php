<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// StripeDriver — Stripe Charges API via HTTP (no SDK dependency).

namespace Nemesis\Payment\Drivers;

use Nemesis\Payment\ChargeResult;
use Nemesis\Payment\PaymentInterface;

/**
 * StripeDriver — Stripe payment integration.
 *
 * Config (env or config/ecommerce.php):
 *   STRIPE_SECRET_KEY   sk_live_…
 *   STRIPE_WEBHOOK_SECRET  whsec_…
 *
 * Usage:
 *   $driver = new StripeDriver(env('STRIPE_SECRET_KEY'));
 *   $result = $driver->charge(1099, $paymentMethodId, ['currency' => 'usd']);
 */
class StripeDriver implements PaymentInterface
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly string $secretKey     = '',
        private readonly string $webhookSecret = '',
    ) {}

    public function charge(int $amountCents, string $token, array $options = []): ChargeResult
    {
        $params = [
            'amount'               => $amountCents,
            'currency'             => $options['currency']    ?? 'usd',
            'payment_method'       => $token,
            'confirmation_method'  => 'automatic',
            'confirm'              => 'true',
            'description'          => $options['description'] ?? '',
        ];

        if (!empty($options['customer'])) {
            $params['customer'] = $options['customer'];
        }

        $response = $this->post('/payment_intents', $params);
        if ($response === null) {
            return ChargeResult::fail('Stripe API unreachable', 'network_error');
        }

        if (!empty($response['error'])) {
            return ChargeResult::fail(
                $response['error']['message'] ?? 'Stripe error',
                $response['error']['code']    ?? '',
                $response
            );
        }

        return ChargeResult::ok(
            $response['id'],
            (int) ($response['amount'] ?? $amountCents),
            strtoupper($response['currency'] ?? 'USD'),
            $response
        );
    }

    public function refund(string $transactionId, ?int $amountCents = null): ChargeResult
    {
        $params = ['payment_intent' => $transactionId];
        if ($amountCents !== null) $params['amount'] = $amountCents;

        $response = $this->post('/refunds', $params);
        if ($response === null) {
            return ChargeResult::fail('Stripe API unreachable', 'network_error');
        }

        if (!empty($response['error'])) {
            return ChargeResult::fail(
                $response['error']['message'] ?? 'Stripe refund error',
                $response['error']['code']    ?? '',
                $response
            );
        }

        return ChargeResult::ok(
            $response['id'],
            (int) ($response['amount'] ?? $amountCents ?? 0),
            'USD',
            $response
        );
    }

    public function webhook(string $payload, string $signature): array
    {
        // Validate Stripe webhook signature (HMAC-SHA256)
        if ($this->webhookSecret !== '') {
            $parts    = [];
            foreach (explode(',', $signature) as $part) {
                [$k, $v] = explode('=', $part, 2) + ['', ''];
                $parts[$k] = $v;
            }
            $timestamp    = $parts['t']  ?? '0';
            $sigReceived  = $parts['v1'] ?? '';
            $expectedSig  = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

            if (!hash_equals($expectedSig, $sigReceived)) {
                throw new \InvalidArgumentException('Stripe webhook signature mismatch.');
            }
        }

        $data  = json_decode($payload, true) ?? [];
        $event = $data['type'] ?? 'unknown';
        return ['event' => $event, 'data' => $data['data']['object'] ?? $data];
    }

    // -------------------------------------------------------------------------

    private function post(string $path, array $params): ?array
    {
        $body    = http_build_query($params);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
            'Stripe-Version: 2023-10-16',
        ];

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(self::API_BASE . $path, false, $ctx);
        if ($response === false) return null;

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}
