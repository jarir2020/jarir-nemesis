# E-Commerce

Nemesis ships a complete e-commerce layer: payment gateway abstraction, a product catalog, a session-backed cart with coupon support, order management with a status machine, and per-product inventory tracking.

---

## Configuration

`config/ecommerce.php` (or `.env` overrides):

```dotenv
SHOP_CURRENCY=USD
PAYMENT_DRIVER=stripe        # manual | stripe | paypal
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_SANDBOX=false
SHOP_TAX_ENABLED=true
SHOP_TAX_RATE=0.10           # 10%
SHOP_SHIPPING_RATE=499       # cents (flat rate, 0 = free)
SHOP_FREE_SHIPPING_OVER=5000 # free shipping above $50.00 (0 = never)
SHOP_TRACK_STOCK=true
SHOP_LOW_STOCK_THRESHOLD=5
```

---

## Payment Gateways

### Charge & Refund

```php
use Nemesis\Payment\PaymentManager;

// Charge the default driver
$result = PaymentManager::charge(amountCents: 2999, token: $request->token);

if ($result->success()) {
    echo $result->transactionId();  // 'ch_abc123'
    echo $result->amount();         // '29.99'
    echo $result->currency();       // 'USD'
} else {
    echo $result->errorMessage();   // 'Card declined'
    echo $result->errorCode();      // 'card_declined'
}

// Refund
$refund = PaymentManager::refund($result->transactionId());
```

### Switch Drivers at Runtime

```php
$manager = PaymentManager::getInstance();
$manager->setDefault('stripe');

// Charge with a specific driver
$result = $manager->driver('paypal')->charge(4999, $token);
```

### Register a Custom Gateway

```php
use Nemesis\Payment\PaymentInterface;
use Nemesis\Payment\ChargeResult;

class BraintreeDriver implements PaymentInterface
{
    public function charge(int $amountCents, string $token, array $options = []): ChargeResult
    {
        // ...
        return ChargeResult::ok($txId, $amountCents, 'USD', $raw);
    }

    public function refund(string $transactionId, ?int $amountCents = null): ChargeResult
    {
        return ChargeResult::ok($refundId, $amountCents ?? 0);
    }

    public function webhook(string $payload, string $signature): array
    {
        return json_decode($payload, true) ?? [];
    }
}

PaymentManager::getInstance()->extend('braintree', new BraintreeDriver());
PaymentManager::getInstance()->setDefault('braintree');
```

### Manual Driver (Testing / COD)

Always succeeds. Useful for cash-on-delivery, bank transfers, and test environments.

```php
PaymentManager::getInstance()->extend('manual', new \Nemesis\Payment\Drivers\ManualDriver());
```

---

## Product Catalog

### Categories

```php
use Nemesis\Catalog\Category;

$clothing = Category::make('Clothing');
$tshirts  = Category::make('T-Shirts', parentId: $clothing->getId());

$clothing->children();  // [$tshirts]
$tshirts->isRoot();     // false
$clothing->isRoot();    // true

$clothing->getSlug();   // 'clothing' (auto-generated)
Category::make('Custom', extra: ['slug' => 'my-custom-slug']);
```

### Attributes

```php
use Nemesis\Catalog\Attribute;

$color = Attribute::make('Color', ['Red', 'Blue', 'Green']);
$size  = Attribute::make('Size',  ['S', 'M', 'L', 'XL']);

$color->addValue('Yellow');   // deduplicates automatically
$color->getValues();          // ['Red','Blue','Green','Yellow']
```

### Products

```php
use Nemesis\Catalog\Product;

$product = Product::make('Classic T-Shirt', priceCents: 1999)
    ->sku('TSH-001')
    ->description('100% cotton classic fit t-shirt.')
    ->category($tshirts)
    ->image('https://example.com/img/tshirt.jpg')
    ->meta('weight_grams', 180)
    ->publish();

// Price helpers
$product->getPriceCents();  // 1999
$product->getPrice();       // '19.99'

// Status
$product->publish();        // 'published'
$product->archive();        // 'archived'
$product->status('draft');  // any string
$product->isPublished();    // bool

// Query
Product::find($id);         // by ID
Product::all();             // all products
Product::published();       // published only
```

### Variants

```php
use Nemesis\Catalog\Variant;

$redM = new Variant([
    'sku'        => 'TSH-RED-M',
    'attributes' => ['Color' => 'Red', 'Size' => 'M'],
    'price'      => 0,       // 0 = inherit product price
    'stock'      => 25,
    'track_stock'=> true,
]);

$blueL = new Variant([
    'sku'        => 'TSH-BLU-L',
    'attributes' => ['Color' => 'Blue', 'Size' => 'L'],
    'price'      => 2199,    // variant price override
    'stock'      => 10,
]);

$product->addVariant($redM);
$product->addVariant($blueL);

$product->hasVariants();   // true
$product->getVariants();   // [$redM, $blueL]

// Stock
$redM->isInStock();          // true
$redM->decrementStock(5);    // 25 → 20
$redM->incrementStock(10);   // 20 → 30
$redM->getStock();           // 30
```

---

## Cart

```php
use Nemesis\Cart\Cart;

$cart = Cart::instance();          // default cart
$cart = Cart::instance('wishlist'); // named cart (multiple carts supported)
```

### Add Items

```php
$product = Product::find(1);

// Add product (qty defaults to 1)
$item = $cart->add($product, qty: 2);

// Add with variant
$variant = $product->getVariants()[0];
$item = $cart->add($product, qty: 1, variant: $variant);

// Adding same product+variant again merges qty
$cart->add($product, 3);   // now qty = 5
```

### Update & Remove

```php
$item = $cart->add($product);
$key  = $item->key();   // unique item identifier

$cart->update($key, qty: 3);
$cart->update($key, qty: 0);  // removes the item
$cart->remove($key);
$cart->clear();               // empty cart + remove coupons
```

### Totals

```php
$cart->subtotalCents();   // 3998
$cart->subtotal();        // '39.98'
$cart->discountCents();   // 400 (if coupon applied)
$cart->discount();        // '4.00'
$cart->totalCents();      // 3598
$cart->total();           // '35.98'
$cart->itemCount();       // total quantity across all items
$cart->isEmpty();         // bool
```

### Coupon Codes

```php
// Register available coupons (app boot / ServiceProvider)
Cart::registerCoupon('SAVE10',  type: 'percent', value: 10);   // 10% off
Cart::registerCoupon('FLAT500', type: 'fixed',   value: 500);  // $5.00 off

// Apply in checkout
$cart->applyCoupon('SAVE10');  // returns true on success, false if unknown
$cart->hasCoupon('SAVE10');    // true
$cart->removeCoupon('SAVE10');
$cart->appliedCoupons();       // ['SAVE10' => ['type'=>'percent','value'=>10]]
```

Discount is capped at the subtotal — total never goes negative.

---

## Orders

### Create from Cart

```php
use Nemesis\Orders\Order;

$order = Order::createFromCart($cart, customerId: $user->id);

$order->billing([
    'name'    => 'Alice Smith',
    'line1'   => '1 Main St',
    'city'    => 'New York',
    'state'   => 'NY',
    'zip'     => '10001',
    'country' => 'US',
]);

$order->shipping(['name' => 'Alice Smith', 'line1' => '1 Main St', /* ... */]);
$order->shippingCost(499);   // $4.99 flat rate
$order->tax(200);            // $2.00 tax
$order->currency('USD');

// Record payment
$charge = PaymentManager::charge($order->grandTotalCents(), $token);
$order->recordPayment($charge);
```

### Status Machine

```
pending → processing → shipped → completed
                    ↘ cancelled  (from pending, processing, or shipped)
completed → refunded
```

```php
$order->process();           // pending → processing
$order->ship('TRK-12345');  // processing → shipped (optional tracking number)
$order->complete();          // shipped → completed
$order->refund();            // completed → refunded
$order->cancel();            // from pending/processing/shipped → cancelled
```

Invalid transitions throw `\LogicException`.

```php
$order->canTransitionTo(Order::STATUS_SHIPPED);  // bool

// Status checks
$order->isPending();    $order->isProcessing();
$order->isShipped();    $order->isCompleted();
$order->isCancelled();  $order->isRefunded();

// History
$order->getStatusHistory();  // [['status'=>'pending','at'=>1234567890], ...]
```

### Query Orders

```php
Order::find($id);
Order::all();
Order::forCustomer($userId);  // all orders for a customer
```

### Totals

```php
$order->subtotalCents();    // from cart
$order->discountCents();    // applied coupons
$order->shippingCents();
$order->taxCents();
$order->grandTotalCents();  // subtotal - discount + shipping + tax
$order->grandTotal();       // '34.98'
```

---

## Invoices

```php
use Nemesis\Orders\Invoice;

$invoice = Invoice::for($order);
$invoice->meta('payment_method', 'Visa ending 4242');

echo $invoice->number();   // 'INV-20260404-00001'

// Plain-text invoice
echo $invoice->toText();

// HTML invoice (full <!DOCTYPE html> page)
echo $invoice->toHtml();

$invoice->toArray();  // structured array
```

---

## Inventory

```php
use Nemesis\Inventory\StockItem;

// Get (or create) stock record for a product/variant
$stock = StockItem::for(productId: 1, variantId: 0);

$stock->setQty(100);
$stock->setLowStockThreshold(10);

// Increment / decrement
$stock->increment(50);           // +50
$stock->decrement(5);            // -5

// Strict decrement — throws \UnderflowException if insufficient
$stock->decrement(200, strict: true);

// Reservations (pending orders)
$stock->reserve(3);              // hold 3 units
$stock->available();             // qty - reserved
$stock->release(1);              // release 1 held unit
$stock->commitReservation(2);    // finalize: release 2 + decrement 2

// Status
$stock->isInStock();
$stock->isOutOfStock();
$stock->isLowStock();            // available ≤ threshold
$stock->canFulfill(qty: 5);      // bool

// Find
StockItem::find(productId: 1, variantId: 0);  // null if not registered
StockItem::all();                              // all StockItems

// Low-stock report
$lowItems = StockItem::lowStockItems();        // items where isLowStock() = true
$alerts   = StockItem::getAlerts();            // triggered alert log
StockItem::clearAlerts();
```

---

## Full Checkout Flow Example

```php
// 1. Build cart
$cart = Cart::instance();
$cart->add(Product::find(1), 2);
Cart::registerCoupon('WELCOME', 'percent', 15);
$cart->applyCoupon('WELCOME');

// 2. Create order
$order = Order::createFromCart($cart, auth()->id());
$order->shippingCost(499)->tax((int)($cart->totalCents() * 0.10));
$order->billing($request->billing)->shipping($request->shipping);

// 3. Charge
$result = PaymentManager::charge($order->grandTotalCents(), $request->payment_token);
if ($result->failed()) {
    return back()->withError($result->errorMessage());
}
$order->recordPayment($result)->process();

// 4. Update inventory
foreach ($order->getItems() as $item) {
    $stock = StockItem::for($item->getProductId(), $item->getVariantId());
    $stock->decrement($item->getQty());
}

// 5. Generate invoice
$invoice = Invoice::for($order);

// 6. Notify customer
$user->notify(new OrderConfirmed($order));

// 7. Clear cart
$cart->clear();

return redirect("/orders/{$order->getId()}")->with('invoice', $invoice->toArray());
```
