<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Catalog;

/**
 * Variant — a specific purchasable combination of product attributes.
 *
 * Usage:
 *   $v = new Variant([
 *       'sku'        => 'TS-001-RED-M',
 *       'attributes' => ['Color' => 'Red', 'Size' => 'M'],
 *       'price'      => 1999,   // cents; 0 = use product base price
 *       'stock'      => 10,
 *   ]);
 */
class Variant
{
    private static int $seq = 0;

    private int    $id;
    private string $sku;
    private array  $attributes;  // ['Color'=>'Red','Size'=>'M']
    private int    $priceCents;  // 0 = inherit from product
    private int    $stock;
    private bool   $trackStock;

    public function __construct(array $data = [])
    {
        $this->id         = ++static::$seq;
        $this->sku        = (string)($data['sku']        ?? '');
        $this->attributes = (array) ($data['attributes'] ?? []);
        $this->priceCents = (int)   ($data['price']      ?? 0);
        $this->stock      = (int)   ($data['stock']      ?? 0);
        $this->trackStock = (bool)  ($data['track_stock'] ?? true);
    }

    public static function resetSeq(): void { static::$seq = 0; }

    public function getId(): int          { return $this->id; }
    public function getSku(): string      { return $this->sku; }
    public function getAttributes(): array { return $this->attributes; }
    public function getAttribute(string $name): ?string { return $this->attributes[$name] ?? null; }
    public function getPriceCents(): int  { return $this->priceCents; }
    public function getStock(): int       { return $this->stock; }
    public function isInStock(): bool     { return !$this->trackStock || $this->stock > 0; }

    public function decrementStock(int $qty = 1): void
    {
        if ($this->trackStock) {
            $this->stock = max(0, $this->stock - $qty);
        }
    }

    public function incrementStock(int $qty = 1): void
    {
        $this->stock += $qty;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'attributes'  => $this->attributes,
            'price_cents' => $this->priceCents,
            'stock'       => $this->stock,
            'in_stock'    => $this->isInStock(),
        ];
    }
}
