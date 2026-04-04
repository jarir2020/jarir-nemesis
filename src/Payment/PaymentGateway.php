<?php
declare(strict_types=1);

namespace Nemesis\Payment;

interface PaymentGateway {
    public function charge($amount, $token, array $options = []);
    public function refund($transactionId, array $options = []);
}
