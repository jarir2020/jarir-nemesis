<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// ChargeResult — immutable value object for payment operation results.

namespace Nemesis\Payment;

/**
 * ChargeResult — result of a charge() or refund() call.
 *
 * Usage:
 *   $result = $gateway->charge(1099, $token);
 *   if ($result->success()) {
 *       $txId = $result->transactionId();
 *   } else {
 *       $error = $result->errorMessage();
 *   }
 */
final class ChargeResult
{
    private function __construct(
        private readonly bool   $success,
        private readonly string $transactionId,
        private readonly int    $amountCents,
        private readonly string $currency,
        private readonly string $errorMessage,
        private readonly string $errorCode,
        private readonly array  $raw,
    ) {}

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    public static function ok(
        string $transactionId,
        int    $amountCents = 0,
        string $currency    = 'USD',
        array  $raw         = []
    ): self {
        return new self(
            success:       true,
            transactionId: $transactionId,
            amountCents:   $amountCents,
            currency:      strtoupper($currency),
            errorMessage:  '',
            errorCode:     '',
            raw:           $raw,
        );
    }

    public static function fail(
        string $errorMessage,
        string $errorCode = '',
        array  $raw       = []
    ): self {
        return new self(
            success:       false,
            transactionId: '',
            amountCents:   0,
            currency:      '',
            errorMessage:  $errorMessage,
            errorCode:     $errorCode,
            raw:           $raw,
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function success(): bool        { return $this->success; }
    public function failed(): bool         { return !$this->success; }
    public function transactionId(): ?string { return $this->transactionId === '' ? null : $this->transactionId; }
    public function amountCents(): int     { return $this->amountCents; }
    public function currency(): string     { return $this->currency; }
    public function errorMessage(): string { return $this->errorMessage; }
    public function errorCode(): string    { return $this->errorCode; }
    public function raw(): array           { return $this->raw; }

    /** Amount as a decimal string (e.g. "10.99"). */
    public function amount(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }

    public function toArray(): array
    {
        return [
            'success'        => $this->success,
            'transaction_id' => $this->transactionId,
            'amount_cents'   => $this->amountCents,
            'amount'         => $this->amount(),
            'currency'       => $this->currency,
            'error_message'  => $this->errorMessage,
            'error_code'     => $this->errorCode,
        ];
    }
}
