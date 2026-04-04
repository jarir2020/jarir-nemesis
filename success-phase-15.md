# Phase 15 — E-Commerce — SUCCESS ✓

**Date:** 2026-04-04  
**Tests:** 769 / 769 passing (86 new in Phase 15)  
**Status:** COMPLETE — This is the final phase of Nemesis 5.0.0

---

## What Was Built

### Payment System (`src/Payment/`)
| File | Description |
|---|---|
| `PaymentInterface.php` | Contract: `charge / refund / webhook` |
| `ChargeResult.php` | Immutable value object; `ok()` / `fail()` factories |
| `PaymentManager.php` | Singleton; driver registry; `charge/refund` statics |
| `Drivers/ManualDriver.php` | Always-succeeds driver; static history; used in tests |
| `Drivers/StripeDriver.php` | PaymentIntents API + HMAC webhook validation |
| `Drivers/PayPalDriver.php` | Orders v2 API + OAuth2 token fetch |

### Catalog (`src/Catalog/`)
| File | Description |
|---|---|
| `Category.php` | Hierarchical categories; auto-slug; in-memory registry |
| `Attribute.php` | Named attribute with allowed values; `addValue()` deduplicates |
| `Product.php` | Full fluent API; `publish/archive`; nested variants in `toArray()` |
| `Variant.php` | SKU + attribute map + price override + stock tracking |

### Cart (`src/Cart/`)
| File | Description |
|---|---|
| `CartItem.php` | Immutable line; `key()` for deduplication; `setQty/increment/decrement` |
| `Cart.php` | Multi-instance (`Cart::instance($name)`); merges duplicate items; coupon engine; session persistence (no-op in CLI); `toArray()` |

**Coupon types:** `percent` (0–100%) and `fixed` (cents); discount capped at subtotal.

### Orders (`src/Orders/`)
| File | Description |
|---|---|
| `OrderItem.php` | Immutable snapshot; `fromCartArray()` factory |
| `Order.php` | Status machine: pending→processing→shipped→completed→refunded; `cancel` from pending/processing/shipped; `createFromCart()`; `recordPayment()` |
| `Invoice.php` | `toText()` + `toHtml()` rendering; invoice number `INV-YYYYMMDD-00001` |

### Inventory (`src/Inventory/`)
| File | Description |
|---|---|
| `StockItem.php` | Per-product/variant stock; `reserve/release/commitReservation`; `lowStockItems()` static; alert log |

### Config
- `config/ecommerce.php` — currency, payment drivers, tax, shipping, inventory, cart, orders

---

## Test Coverage (Phase 15 — 86 tests)

| Class | Tests |
|---|---|
| `ChargeResultTest` | 3 |
| `ManualDriverTest` | 5 |
| `PaymentManagerTest` | 2 |
| `CategoryTest` | 5 |
| `AttributeTest` | 3 |
| `VariantTest` | 5 |
| `ProductTest` | 6 |
| `CartItemTest` | 6 |
| `CartTest` | 15 |
| `OrderItemTest` | 2 |
| `OrderTest` | 13 |
| `InvoiceTest` | 5 |
| `StockItemTest` | 12 |

---

## Cumulative Test History

| Phase | Total Passing |
|---|---|
| Phase 10 | 457 |
| Phase 11 | 526 |
| Phase 12 | 586 |
| Phase 13 | 643 |
| Phase 14 | 683 |
| **Phase 15** | **769** |

---

## Nemesis 5.0.0 — ALL PHASES COMPLETE

| Phase | Status |
|---|---|
| Phase 10 — Asset Pipeline | ✓ DONE |
| Phase 11 — Template Engine | ✓ DONE |
| Phase 12 — Core CMS | ✓ DONE |
| Phase 13 — Admin Panel + Media Library | ✓ DONE |
| Phase 14 — Notifications + Full-Text Search | ✓ DONE |
| Phase 15 — E-Commerce | ✓ DONE |
