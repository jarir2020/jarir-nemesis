<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// PaymentInterface — contract for all payment gateway drivers.

namespace Nemesis\Payment;

/**
 * PaymentInterface — every payment driver must implement this.
 *
 * @see ChargeResult  The value object returned by charge()
 */
interface PaymentInterface
{
    /**
     * Charge a payment method.
     *
     * @param  int    $amountCents  Amount in smallest currency unit (e.g. 1099 = $10.99)
     * @param  string $token        Payment token / card token / nonce
     * @param  array  $options      Driver-specific options (currency, description, metadata…)
     * @return ChargeResult
     */
    public function charge(int $amountCents, string $token, array $options = []): ChargeResult;

    /**
     * Refund a previous charge (fully or partially).
     *
     * @param  string   $transactionId  The ID returned by a previous charge()
     * @param  int|null $amountCents    Partial refund amount; null = full refund
     * @return ChargeResult             Success/failure + refund transaction ID
     */
    public function refund(string $transactionId, ?int $amountCents = null): ChargeResult;

    /**
     * Handle an incoming webhook payload from the gateway.
     * Validates the signature and returns a normalised event array.
     *
     * @param  string $payload    Raw request body
     * @param  string $signature  Gateway signature header value
     * @return array{event:string,data:array}
     *
     * @throws \InvalidArgumentException  On signature mismatch
     */
    public function webhook(string $payload, string $signature): array;
}
