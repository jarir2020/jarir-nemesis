<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Cart;

use Nemesis\Catalog\Product;
use Nemesis\Catalog\Variant;

/**
 * Cart — session-backed shopping cart with coupon support.
 *
 * Usage:
 *   $cart = Cart::instance();          // or Cart::instance('wishlist')
 *   $cart->add($product, 2, $variant);
 *   $cart->applyCoupon('SAVE10');
 *   echo $cart->totalCents();          // subtotal - discount
 */
class Cart
{
    /** @var array<string, static> */
    private static array $instances = [];

    /** @var array<string, CartItem> key → CartItem */
    private array $items = [];

    /** Applied coupon codes: code → ['type'=>'percent'|'fixed', 'value'=>int] */
    private array $coupons = [];

    /** Registered available coupons: code → config */
    private static array $couponRegistry = [];

    private function __construct(private readonly string $name = 'default') {}

    // -------------------------------------------------------------------------
    // Factory / session
    // -------------------------------------------------------------------------

    public static function instance(string $name = 'default'): static
    {
        if (!isset(static::$instances[$name])) {
            $cart = new static($name);
            $cart->restoreFromSession($name);
            static::$instances[$name] = $cart;
        }
        return static::$instances[$name];
    }

    /** Destroy a specific cart instance (clears session too). */
    public static function destroy(string $name = 'default'): void
    {
        unset(static::$instances[$name]);
        static::clearSession($name);
    }

    /** Wipe all in-memory instances (used in tests). */
    public static function reset(): void
    {
        static::$instances    = [];
        static::$couponRegistry = [];
    }

    // -------------------------------------------------------------------------
    // Coupon registry (app-level)
    // -------------------------------------------------------------------------

    /**
     * Register a coupon code.
     *   type  = 'percent' (0–100) | 'fixed' (cents)
     *   value = percentage points or cent amount
     */
    public static function registerCoupon(string $code, string $type, int $value): void
    {
        static::$couponRegistry[strtoupper($code)] = compact('type', 'value');
    }

    public static function getRegisteredCoupon(string $code): ?array
    {
        return static::$couponRegistry[strtoupper($code)] ?? null;
    }

    public static function getCouponRegistry(): array
    {
        return static::$couponRegistry;
    }

    // -------------------------------------------------------------------------
    // Item management
    // -------------------------------------------------------------------------

    /**
     * Add a product (optionally with variant) to the cart.
     * If the same key already exists the qty is merged.
     */
    public function add(
        Product $product,
        int     $qty      = 1,
        ?Variant $variant = null,
        array   $extra    = [],  // extra attributes to attach to the line
    ): CartItem {
        $unitPrice  = $variant && $variant->getPriceCents() > 0
            ? $variant->getPriceCents()
            : $product->getPriceCents();
        $variantId  = $variant ? $variant->getId() : 0;
        $sku        = $variant ? $variant->getSku() : $product->getSku();
        $attributes = $variant ? $variant->getAttributes() : $extra;

        $item = new CartItem(
            productId:      $product->getId(),
            productName:    $product->getName(),
            unitPriceCents: $unitPrice,
            qty:            max(1, $qty),
            sku:            $sku,
            attributes:     $attributes,
            variantId:      $variantId,
        );

        $key = $item->key();
        if (isset($this->items[$key])) {
            $this->items[$key]->incrementQty(max(1, $qty));
        } else {
            $this->items[$key] = $item;
        }

        $this->persistToSession();
        return $this->items[$key];
    }

    /**
     * Add a raw CartItem directly (e.g. restored from DB).
     */
    public function addItem(CartItem $item): void
    {
        $key = $item->key();
        if (isset($this->items[$key])) {
            $this->items[$key]->incrementQty($item->getQty());
        } else {
            $this->items[$key] = $item;
        }
        $this->persistToSession();
    }

    /** Update quantity for a given item key. qty=0 removes the item. */
    public function update(string $key, int $qty): void
    {
        if (!isset($this->items[$key])) {
            return;
        }
        if ($qty <= 0) {
            $this->remove($key);
            return;
        }
        $this->items[$key]->setQty($qty);
        $this->persistToSession();
    }

    public function remove(string $key): void
    {
        unset($this->items[$key]);
        $this->persistToSession();
    }

    public function clear(): void
    {
        $this->items   = [];
        $this->coupons = [];
        $this->persistToSession();
    }

    // -------------------------------------------------------------------------
    // Coupon application
    // -------------------------------------------------------------------------

    /** Apply a registered coupon to this cart. Returns true on success. */
    public function applyCoupon(string $code): bool
    {
        $code   = strtoupper($code);
        $config = static::$couponRegistry[$code] ?? null;
        if ($config === null) {
            return false;
        }
        $this->coupons[$code] = $config;
        $this->persistToSession();
        return true;
    }

    public function removeCoupon(string $code): void
    {
        unset($this->coupons[strtoupper($code)]);
        $this->persistToSession();
    }

    public function appliedCoupons(): array
    {
        return $this->coupons;
    }

    public function hasCoupon(string $code): bool
    {
        return isset($this->coupons[strtoupper($code)]);
    }

    // -------------------------------------------------------------------------
    // Totals
    // -------------------------------------------------------------------------

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn(CartItem $i) => $i->subtotalCents(), $this->items));
    }

    /** Total discount from all applied coupons (in cents). */
    public function discountCents(): int
    {
        $subtotal = $this->subtotalCents();
        $discount = 0;
        foreach ($this->coupons as $config) {
            if ($config['type'] === 'percent') {
                $discount += (int) round($subtotal * $config['value'] / 100);
            } elseif ($config['type'] === 'fixed') {
                $discount += $config['value'];
            }
        }
        return min($discount, $subtotal);  // discount cannot exceed subtotal
    }

    public function totalCents(): int
    {
        return max(0, $this->subtotalCents() - $this->discountCents());
    }

    public function subtotal(): string { return number_format($this->subtotalCents() / 100, 2); }
    public function discount(): string { return number_format($this->discountCents() / 100, 2); }
    public function total(): string    { return number_format($this->totalCents()    / 100, 2); }

    public function itemCount(): int
    {
        return array_sum(array_map(fn(CartItem $i) => $i->getQty(), $this->items));
    }

    public function isEmpty(): bool { return empty($this->items); }

    // -------------------------------------------------------------------------
    // Items access
    // -------------------------------------------------------------------------

    /** @return array<string, CartItem> */
    public function items(): array { return $this->items; }

    public function hasItem(string $key): bool { return isset($this->items[$key]); }

    public function getItem(string $key): ?CartItem { return $this->items[$key] ?? null; }

    public function getName(): string { return $this->name; }

    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'items'          => array_map(fn(CartItem $i) => $i->toArray(), $this->items),
            'coupons'        => array_keys($this->coupons),
            'subtotal_cents' => $this->subtotalCents(),
            'discount_cents' => $this->discountCents(),
            'total_cents'    => $this->totalCents(),
            'subtotal'       => $this->subtotal(),
            'discount'       => $this->discount(),
            'total'          => $this->total(),
            'item_count'     => $this->itemCount(),
        ];
    }

    // -------------------------------------------------------------------------
    // Session persistence (graceful — no-op when sessions unavailable)
    // -------------------------------------------------------------------------

    private function persistToSession(): void
    {
        if (!$this->sessionAvailable()) {
            return;
        }
        $_SESSION['nemesis_cart_' . $this->name] = serialize($this->toSerializable());
    }

    private function restoreFromSession(string $name): void
    {
        if (!$this->sessionAvailable()) {
            return;
        }
        $key = 'nemesis_cart_' . $name;
        if (empty($_SESSION[$key])) {
            return;
        }
        try {
            $data = unserialize($_SESSION[$key]);
            foreach ($data['items'] ?? [] as $itemData) {
                $this->items[$itemData['key']] = new CartItem(
                    productId:      $itemData['product_id'],
                    productName:    $itemData['product_name'],
                    unitPriceCents: $itemData['unit_price_cents'],
                    qty:            $itemData['qty'],
                    sku:            $itemData['sku'],
                    attributes:     $itemData['attributes'],
                    variantId:      $itemData['variant_id'],
                );
            }
            foreach ($data['coupons'] ?? [] as $code => $config) {
                $this->coupons[$code] = $config;
            }
        } catch (\Throwable) {
            // Corrupted session — start fresh
        }
    }

    private function toSerializable(): array
    {
        $items = [];
        foreach ($this->items as $key => $item) {
            $arr        = $item->toArray();
            $arr['key'] = $key;
            $items[]    = $arr;
        }
        return ['items' => $items, 'coupons' => $this->coupons];
    }

    private static function clearSession(string $name): void
    {
        if (isset($_SESSION)) {
            unset($_SESSION['nemesis_cart_' . $name]);
        }
    }

    private function sessionAvailable(): bool
    {
        return PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE;
    }
}
