<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Cart;

/**
 * CartItem — a single line in the shopping cart.
 */
class CartItem
{
    public function __construct(
        private readonly int    $productId,
        private readonly string $productName,
        private readonly int    $unitPriceCents,
        private int             $qty,
        private readonly string $sku       = '',
        private readonly array  $attributes = [],  // ['Color'=>'Red','Size'=>'M']
        private readonly int    $variantId  = 0,
    ) {}

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getProductId(): int      { return $this->productId; }
    public function getProductName(): string { return $this->productName; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function getQty(): int            { return $this->qty; }
    public function getSku(): string         { return $this->sku; }
    public function getAttributes(): array   { return $this->attributes; }
    public function getVariantId(): int      { return $this->variantId; }

    /** Total price for this line (unit × qty). */
    public function subtotalCents(): int     { return $this->unitPriceCents * $this->qty; }
    public function subtotal(): string       { return number_format($this->subtotalCents() / 100, 2); }
    public function unitPrice(): string      { return number_format($this->unitPriceCents / 100, 2); }

    /** A unique key for this line (product + variant combination). */
    public function key(): string
    {
        return $this->productId . '_' . $this->variantId . '_' . implode('_', array_values($this->attributes));
    }

    // -------------------------------------------------------------------------
    // Mutation
    // -------------------------------------------------------------------------

    public function setQty(int $qty): void
    {
        $this->qty = max(1, $qty);
    }

    public function incrementQty(int $by = 1): void
    {
        $this->qty += max(1, $by);
    }

    public function decrementQty(int $by = 1): void
    {
        $this->qty = max(1, $this->qty - $by);
    }

    public function toArray(): array
    {
        return [
            'product_id'       => $this->productId,
            'product_name'     => $this->productName,
            'sku'              => $this->sku,
            'variant_id'       => $this->variantId,
            'attributes'       => $this->attributes,
            'unit_price_cents' => $this->unitPriceCents,
            'unit_price'       => $this->unitPrice(),
            'qty'              => $this->qty,
            'subtotal_cents'   => $this->subtotalCents(),
            'subtotal'         => $this->subtotal(),
        ];
    }
}
