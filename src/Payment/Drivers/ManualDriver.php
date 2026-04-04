<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// ManualDriver — always succeeds; useful for cash-on-delivery, bank transfer, testing.

namespace Nemesis\Payment\Drivers;

use Nemesis\Payment\ChargeResult;
use Nemesis\Payment\PaymentInterface;

class ManualDriver implements PaymentInterface
{
    public static array $charged  = [];
    public static array $refunded = [];

    public function charge(int $amountCents, string $token, array $options = []): ChargeResult
    {
        $txId = 'manual_' . bin2hex(random_bytes(6));
        static::$charged[] = compact('txId', 'amountCents', 'token', 'options');
        return ChargeResult::ok($txId, $amountCents, $options['currency'] ?? 'USD');
    }

    public function refund(string $transactionId, ?int $amountCents = null): ChargeResult
    {
        $txId = 'refund_' . bin2hex(random_bytes(6));
        static::$refunded[] = compact('txId', 'transactionId', 'amountCents');
        return ChargeResult::ok($txId, $amountCents ?? 0);
    }

    public function webhook(string $payload, string $signature): array
    {
        $data = json_decode($payload, true) ?? [];
        return ['event' => $data['event'] ?? 'manual.event', 'data' => $data];
    }

    public static function charged(): array  { return static::$charged; }
    public static function refunded(): array { return static::$refunded; }
    public static function reset(): void     { static::$charged = []; static::$refunded = []; }
}
