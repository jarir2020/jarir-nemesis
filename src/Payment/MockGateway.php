<?php
declare(strict_types=1);

namespace Nemesis\Payment;

class MockGateway implements PaymentGateway {
    public function charge($amount, $token, array $options = []) {
        return [
            'success' => true,
            'transaction_id' => 'ch_' . bin2hex(random_bytes(10)),
            'amount' => $amount,
            'currency' => $options['currency'] ?? 'USD'
        ];
    }

    public function refund($transactionId, array $options = []) {
        return [
            'success' => true,
            'refund_id' => 're_' . bin2hex(random_bytes(10)),
            'original_transaction' => $transactionId
        ];
    }
}
