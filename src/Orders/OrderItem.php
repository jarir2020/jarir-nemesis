<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Orders;

/**
 * OrderItem — a single line in a placed order (immutable snapshot).
 */
class OrderItem
{
    public function __construct(
        private readonly int    $productId,
        private readonly string $productName,
        private readonly string $sku,
        private readonly int    $variantId,
        private readonly array  $attributes,   // ['Color'=>'Red','Size'=>'M']
        private readonly int    $unitPriceCents,
        private readonly int    $qty,
    ) {}

    public function getProductId(): int        { return $this->productId; }
    public function getProductName(): string   { return $this->productName; }
    public function getSku(): string           { return $this->sku; }
    public function getVariantId(): int        { return $this->variantId; }
    public function getAttributes(): array     { return $this->attributes; }
    public function getUnitPriceCents(): int   { return $this->unitPriceCents; }
    public function getQty(): int              { return $this->qty; }
    public function subtotalCents(): int       { return $this->unitPriceCents * $this->qty; }
    public function subtotal(): string         { return number_format($this->subtotalCents() / 100, 2); }
    public function unitPrice(): string        { return number_format($this->unitPriceCents / 100, 2); }

    /**
     * Build an OrderItem from a CartItem array snapshot.
     */
    public static function fromCartArray(array $item): static
    {
        return new static(
            productId:      (int)   ($item['product_id']       ?? 0),
            productName:    (string)($item['product_name']     ?? ''),
            sku:            (string)($item['sku']              ?? ''),
            variantId:      (int)   ($item['variant_id']       ?? 0),
            attributes:     (array) ($item['attributes']       ?? []),
            unitPriceCents: (int)   ($item['unit_price_cents'] ?? 0),
            qty:            (int)   ($item['qty']              ?? 1),
        );
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
