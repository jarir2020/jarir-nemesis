<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Inventory;

/**
 * StockItem — tracks inventory for a product/variant, with low-stock alerting.
 *
 * Usage:
 *   $stock = StockItem::for(productId: 1, variantId: 0);
 *   $stock->setQty(50)->setLowStockThreshold(5);
 *   $stock->decrement(3);
 *   if ($stock->isLowStock()) { ... }
 */
class StockItem
{
    /** @var array<string, static>  key→StockItem */
    private static array $registry = [];

    /** @var list<array{productId:int,variantId:int,qty:int,threshold:int}> */
    private static array $alerts = [];

    private int $qty       = 0;
    private int $threshold = 0;   // low-stock alert threshold (0 = disabled)
    private int $reserved  = 0;   // qty held by pending orders
    private array $meta    = [];

    private function __construct(
        private readonly int $productId,
        private readonly int $variantId = 0,
    ) {}

    // -------------------------------------------------------------------------
    // Factory / registry
    // -------------------------------------------------------------------------

    /** Retrieve (or create) the StockItem for a product+variant combination. */
    public static function for(int $productId, int $variantId = 0): static
    {
        $key = $productId . '_' . $variantId;
        if (!isset(static::$registry[$key])) {
            static::$registry[$key] = new static($productId, $variantId);
        }
        return static::$registry[$key];
    }

    public static function find(int $productId, int $variantId = 0): ?static
    {
        return static::$registry[$productId . '_' . $variantId] ?? null;
    }

    public static function all(): array { return array_values(static::$registry); }

    public static function reset(): void
    {
        static::$registry = [];
        static::$alerts   = [];
    }

    // -------------------------------------------------------------------------
    // Setters (fluent)
    // -------------------------------------------------------------------------

    public function setQty(int $qty): static
    {
        $this->qty = max(0, $qty);
        return $this;
    }

    public function setLowStockThreshold(int $threshold): static
    {
        $this->threshold = max(0, $threshold);
        return $this;
    }

    public function meta(string $k, mixed $v): static
    {
        $this->meta[$k] = $v;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Stock operations
    // -------------------------------------------------------------------------

    public function increment(int $qty = 1): static
    {
        $this->qty += max(0, $qty);
        return $this;
    }

    /** Decrement available stock. Throws if insufficient and $strict=true. */
    public function decrement(int $qty = 1, bool $strict = false): static
    {
        $qty = max(0, $qty);
        if ($strict && $this->available() < $qty) {
            throw new \UnderflowException(
                "Insufficient stock for product {$this->productId} variant {$this->variantId}. "
                . "Available: {$this->available()}, requested: {$qty}."
            );
        }
        $this->qty = max(0, $this->qty - $qty);
        $this->checkLowStock();
        return $this;
    }

    /** Reserve qty (pending order hold). */
    public function reserve(int $qty): static
    {
        $this->reserved = max(0, $this->reserved + $qty);
        return $this;
    }

    /** Release previously reserved qty. */
    public function release(int $qty): static
    {
        $this->reserved = max(0, $this->reserved - $qty);
        return $this;
    }

    /** Commit reserved qty to actual decrement (order confirmed). */
    public function commitReservation(int $qty): static
    {
        $this->release($qty);
        $this->decrement($qty);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function getQty(): int         { return $this->qty; }
    public function getReserved(): int    { return $this->reserved; }
    public function getThreshold(): int   { return $this->threshold; }
    public function getProductId(): int   { return $this->productId; }
    public function getVariantId(): int   { return $this->variantId; }

    /** Available = on-hand minus reserved. */
    public function available(): int      { return max(0, $this->qty - $this->reserved); }

    public function isInStock(): bool     { return $this->available() > 0; }
    public function isOutOfStock(): bool  { return !$this->isInStock(); }

    public function isLowStock(): bool
    {
        return $this->threshold > 0 && $this->available() <= $this->threshold;
    }

    public function canFulfill(int $qty): bool
    {
        return $this->available() >= $qty;
    }

    // -------------------------------------------------------------------------
    // Low-stock alerts
    // -------------------------------------------------------------------------

    private function checkLowStock(): void
    {
        if ($this->isLowStock()) {
            static::$alerts[] = [
                'productId' => $this->productId,
                'variantId' => $this->variantId,
                'qty'       => $this->qty,
                'threshold' => $this->threshold,
            ];
        }
    }

    public static function getAlerts(): array   { return static::$alerts; }
    public static function clearAlerts(): void  { static::$alerts = []; }

    /** @return list<static> items at or below their low-stock threshold. */
    public static function lowStockItems(): array
    {
        return array_values(array_filter(
            static::$registry,
            fn(self $s) => $s->isLowStock()
        ));
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    public function toArray(): array
    {
        return [
            'product_id'  => $this->productId,
            'variant_id'  => $this->variantId,
            'qty'         => $this->qty,
            'reserved'    => $this->reserved,
            'available'   => $this->available(),
            'threshold'   => $this->threshold,
            'in_stock'    => $this->isInStock(),
            'low_stock'   => $this->isLowStock(),
        ];
    }
}
