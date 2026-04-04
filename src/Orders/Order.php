<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Orders;

use Nemesis\Cart\Cart;
use Nemesis\Payment\ChargeResult;

/**
 * Order — a placed order with a status-machine lifecycle.
 *
 * Status flow:
 *   pending → processing → shipped → completed
 *                       ↘ cancelled  (from pending or processing)
 *
 * Usage:
 *   $order = Order::createFromCart($cart, $customerId);
 *   $order->process();
 *   $order->ship('TRK-123');
 *   $order->complete();
 */
class Order
{
    // Status constants
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED    = 'shipped';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_REFUNDED   = 'refunded';

    const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING    => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED,    self::STATUS_CANCELLED],
        self::STATUS_SHIPPED    => [self::STATUS_COMPLETED,  self::STATUS_CANCELLED],
        self::STATUS_COMPLETED  => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED  => [],
        self::STATUS_REFUNDED   => [],
    ];

    /** @var array<int, static> */
    private static array $registry = [];
    private static int   $seq      = 0;

    private int     $id;
    private string  $orderNumber;
    private string  $status  = self::STATUS_PENDING;
    private ?string $trackingNumber = null;
    private ?string $transactionId  = null;
    private array   $statusHistory  = [];  // [['status'=>..., 'at'=>timestamp], ...]
    private array   $meta           = [];

    /** @var list<OrderItem> */
    private array $items = [];

    private int  $subtotalCents  = 0;
    private int  $discountCents  = 0;
    private int  $shippingCents  = 0;
    private int  $taxCents       = 0;
    private array $billingAddress  = [];
    private array $shippingAddress = [];
    private string $currency = 'USD';

    private int $createdAt;
    private int $updatedAt;

    private function __construct(private readonly ?int $customerId = null)
    {
        $this->id          = ++static::$seq;
        $this->orderNumber = static::generateOrderNumber($this->id);
        $this->createdAt   = time();
        $this->updatedAt   = time();
        $this->recordStatus(self::STATUS_PENDING);
        static::$registry[$this->id] = $this;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(?int $customerId = null): static
    {
        return new static($customerId);
    }

    /**
     * Build an order from a Cart snapshot.
     */
    public static function createFromCart(Cart $cart, ?int $customerId = null): static
    {
        $order = new static($customerId);
        foreach ($cart->items() as $cartItem) {
            $order->items[] = OrderItem::fromCartArray($cartItem->toArray());
        }
        $order->subtotalCents = $cart->subtotalCents();
        $order->discountCents = $cart->discountCents();
        $order->updatedAt     = time();
        return $order;
    }

    public static function find(int $id): ?static { return static::$registry[$id] ?? null; }
    public static function all(): array           { return array_values(static::$registry); }

    /** @return list<static> */
    public static function forCustomer(int $customerId): array
    {
        return array_values(array_filter(
            static::$registry,
            fn(self $o) => $o->customerId === $customerId
        ));
    }

    public static function reset(): void
    {
        static::$registry = [];
        static::$seq      = 0;
    }

    // -------------------------------------------------------------------------
    // Status machine
    // -------------------------------------------------------------------------

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, static::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /** @throws \LogicException on invalid transition */
    private function transitionTo(string $status): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition order #{$this->id} from {$this->status} to {$status}."
            );
        }
        $this->status    = $status;
        $this->updatedAt = time();
        $this->recordStatus($status);
    }

    public function process(): static
    {
        $this->transitionTo(self::STATUS_PROCESSING);
        return $this;
    }

    public function ship(string $trackingNumber = ''): static
    {
        $this->transitionTo(self::STATUS_SHIPPED);
        if ($trackingNumber !== '') {
            $this->trackingNumber = $trackingNumber;
        }
        return $this;
    }

    public function complete(): static
    {
        $this->transitionTo(self::STATUS_COMPLETED);
        return $this;
    }

    public function cancel(): static
    {
        $this->transitionTo(self::STATUS_CANCELLED);
        return $this;
    }

    public function refund(): static
    {
        $this->transitionTo(self::STATUS_REFUNDED);
        return $this;
    }

    private function recordStatus(string $status): void
    {
        $this->statusHistory[] = ['status' => $status, 'at' => time()];
    }

    // -------------------------------------------------------------------------
    // Setters (fluent)
    // -------------------------------------------------------------------------

    public function billing(array $address): static  { $this->billingAddress  = $address; return $this; }
    public function shipping(array $address): static { $this->shippingAddress = $address; return $this; }
    public function currency(string $c): static      { $this->currency = strtoupper($c);  return $this; }
    public function shippingCost(int $cents): static { $this->shippingCents = $cents; $this->updatedAt = time(); return $this; }
    public function tax(int $cents): static          { $this->taxCents = $cents;         $this->updatedAt = time(); return $this; }
    public function meta(string $k, mixed $v): static { $this->meta[$k] = $v; return $this; }

    public function recordPayment(ChargeResult $result): static
    {
        if ($result->success()) {
            $this->transactionId = $result->transactionId();
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int              { return $this->id; }
    public function getOrderNumber(): string  { return $this->orderNumber; }
    public function getCustomerId(): ?int     { return $this->customerId; }
    public function getStatus(): string       { return $this->status; }
    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function getTransactionId(): ?string  { return $this->transactionId; }
    public function getStatusHistory(): array    { return $this->statusHistory; }
    public function getBillingAddress(): array   { return $this->billingAddress; }
    public function getShippingAddress(): array  { return $this->shippingAddress; }
    public function getCurrency(): string        { return $this->currency; }
    public function getMeta(string $k, mixed $default = null): mixed { return $this->meta[$k] ?? $default; }

    /** @return list<OrderItem> */
    public function getItems(): array { return $this->items; }

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isProcessing(): bool { return $this->status === self::STATUS_PROCESSING; }
    public function isShipped(): bool    { return $this->status === self::STATUS_SHIPPED; }
    public function isCompleted(): bool  { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isRefunded(): bool   { return $this->status === self::STATUS_REFUNDED; }

    public function subtotalCents(): int { return $this->subtotalCents; }
    public function discountCents(): int { return $this->discountCents; }
    public function shippingCents(): int { return $this->shippingCents; }
    public function taxCents(): int      { return $this->taxCents; }

    public function grandTotalCents(): int
    {
        return max(0, $this->subtotalCents - $this->discountCents + $this->shippingCents + $this->taxCents);
    }

    public function grandTotal(): string { return number_format($this->grandTotalCents() / 100, 2); }

    public function getCreatedAt(): int  { return $this->createdAt; }
    public function getUpdatedAt(): int  { return $this->updatedAt; }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function generateOrderNumber(int $id): string
    {
        return 'ORD-' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->orderNumber,
            'customer_id'      => $this->customerId,
            'status'           => $this->status,
            'transaction_id'   => $this->transactionId,
            'tracking_number'  => $this->trackingNumber,
            'currency'         => $this->currency,
            'subtotal_cents'   => $this->subtotalCents,
            'discount_cents'   => $this->discountCents,
            'shipping_cents'   => $this->shippingCents,
            'tax_cents'        => $this->taxCents,
            'grand_total_cents'=> $this->grandTotalCents(),
            'grand_total'      => $this->grandTotal(),
            'billing_address'  => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'items'            => array_map(fn(OrderItem $i) => $i->toArray(), $this->items),
            'status_history'   => $this->statusHistory,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }
}
