<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04
// PaymentManager — driver registry + static facade for payment operations.

namespace Nemesis\Payment;

use Nemesis\Payment\Drivers\ManualDriver;
use Nemesis\Payment\Drivers\StripeDriver;
use Nemesis\Payment\Drivers\PayPalDriver;

/**
 * PaymentManager — resolves the active payment driver and proxies operations.
 *
 * Usage:
 *   PaymentManager::setDefault('stripe');
 *   $result = PaymentManager::charge(1099, $token, ['currency' => 'usd']);
 *   $result = PaymentManager::driver('manual')->charge(...);
 */
class PaymentManager
{
    /** @var array<string, PaymentInterface> */
    private array $drivers = [];

    private string $default = 'manual';

    private static ?self $instance = null;

    private function __construct()
    {
        $this->drivers = [
            'manual' => new ManualDriver(),
        ];
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function reset(): void
    {
        static::$instance = null;
        ManualDriver::reset();
    }

    // -------------------------------------------------------------------------
    // Driver management
    // -------------------------------------------------------------------------

    /** Register or replace a driver. */
    public function extend(string $name, PaymentInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /** Return a specific driver by name. */
    public function driver(string $name): PaymentInterface
    {
        if (!isset($this->drivers[$name])) {
            // Auto-construct built-in drivers
            $this->drivers[$name] = match ($name) {
                'stripe' => new StripeDriver(
                    (string) getenv('STRIPE_SECRET_KEY'),
                    (string) getenv('STRIPE_WEBHOOK_SECRET')
                ),
                'paypal' => new PayPalDriver(
                    (string) getenv('PAYPAL_CLIENT_ID'),
                    (string) getenv('PAYPAL_CLIENT_SECRET'),
                    (bool)   getenv('PAYPAL_SANDBOX') !== 'false'
                ),
                default  => new ManualDriver(),
            };
        }
        return $this->drivers[$name];
    }

    public function setDefault(string $name): void  { $this->default = $name; }
    public function getDefault(): string             { return $this->default; }

    // -------------------------------------------------------------------------
    // Static proxy to default driver
    // -------------------------------------------------------------------------

    public static function setDefaultDriver(string $name): void
    {
        static::getInstance()->setDefault($name);
    }

    public static function charge(int $amountCents, string $token, array $options = []): ChargeResult
    {
        return static::getInstance()->driver(static::getInstance()->default)->charge($amountCents, $token, $options);
    }

    public static function refund(string $transactionId, ?int $amountCents = null): ChargeResult
    {
        return static::getInstance()->driver(static::getInstance()->default)->refund($transactionId, $amountCents);
    }
}
