<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce Tests | Created: 2026-04-04

use Nemesis\Testing\TestCase;
use Nemesis\Payment\ChargeResult;
use Nemesis\Payment\Drivers\ManualDriver;
use Nemesis\Payment\PaymentManager;
use Nemesis\Catalog\Category;
use Nemesis\Catalog\Attribute;
use Nemesis\Catalog\Product;
use Nemesis\Catalog\Variant;
use Nemesis\Cart\CartItem;
use Nemesis\Cart\Cart;
use Nemesis\Orders\Order;
use Nemesis\Orders\OrderItem;
use Nemesis\Orders\Invoice;
use Nemesis\Inventory\StockItem;

// =============================================================================
// ChargeResult
// =============================================================================

class ChargeResultTest extends TestCase
{
    public function testOkFactory(): void
    {
        $r = ChargeResult::ok('txn_123', 1999, 'USD', ['raw' => true]);
        $this->assertTrue($r->success());
        $this->assertFalse($r->failed());
        $this->assertEquals('txn_123', $r->transactionId());
        $this->assertEquals(1999, $r->amountCents());
        $this->assertEquals('19.99', $r->amount());
        $this->assertEquals('USD', $r->currency());
    }

    public function testFailFactory(): void
    {
        $r = ChargeResult::fail('Card declined', 'card_declined', []);
        $this->assertFalse($r->success());
        $this->assertTrue($r->failed());
        $this->assertEquals('Card declined', $r->errorMessage());
        $this->assertEquals('card_declined', $r->errorCode());
        $this->assertNull($r->transactionId());
    }

    public function testToArray(): void
    {
        $r = ChargeResult::ok('t1', 500, 'GBP', []);
        $arr = $r->toArray();
        $this->assertTrue($arr['success']);
        $this->assertEquals('t1', $arr['transaction_id']);
        $this->assertEquals(500, $arr['amount_cents']);
        $this->assertEquals('GBP', $arr['currency']);
    }
}

// =============================================================================
// ManualDriver
// =============================================================================

class ManualDriverTest extends TestCase
{
    public function setUp(): void
    {
        ManualDriver::reset();
    }

    public function testChargeSucceeds(): void
    {
        $driver = new ManualDriver();
        $result = $driver->charge(2999, 'tok_test');
        $this->assertTrue($result->success());
        $this->assertEquals(2999, $result->amountCents());
        $this->assertTrue(str_starts_with((string)$result->transactionId(), 'manual_'));
    }

    public function testRefundSucceeds(): void
    {
        $driver = new ManualDriver();
        $charge = $driver->charge(1000, 'tok_test');
        $refund = $driver->refund($charge->transactionId());
        $this->assertTrue($refund->success());
    }

    public function testChargedHistory(): void
    {
        $driver = new ManualDriver();
        $driver->charge(100, 'tok_a');
        $driver->charge(200, 'tok_b');
        $this->assertCount(2, ManualDriver::$charged);
    }

    public function testWebhookReturnsArray(): void
    {
        $driver = new ManualDriver();
        $result = $driver->webhook('{}', 'sig');
        $this->assertIsArray($result);
    }

    public function testReset(): void
    {
        $driver = new ManualDriver();
        $driver->charge(100, 'tok');
        ManualDriver::reset();
        $this->assertEmpty(ManualDriver::$charged);
    }
}

// =============================================================================
// PaymentManager
// =============================================================================

class PaymentManagerTest extends TestCase
{
    public function setUp(): void
    {
        ManualDriver::reset();
        PaymentManager::reset();
    }

    public function testExtendAndUse(): void
    {
        $manager = PaymentManager::getInstance();
        $manager->extend('manual', new ManualDriver());
        $result = $manager->driver('manual')->charge(500, 'tok');
        $this->assertTrue($result->success());
    }

    public function testDefaultDriver(): void
    {
        $manager = PaymentManager::getInstance();
        $manager->extend('manual', new ManualDriver());
        $manager->setDefault('manual');
        $result = PaymentManager::charge(300, 'tok');
        $this->assertTrue($result->success());
    }
}

// =============================================================================
// Category
// =============================================================================

class CategoryTest extends TestCase
{
    public function setUp(): void { Category::reset(); }

    public function testMakeAndFind(): void
    {
        $cat = Category::make('Clothing');
        $this->assertEquals('Clothing', $cat->getName());
        $this->assertEquals($cat->getId(), Category::find($cat->getId())->getId());
    }

    public function testAutoSlug(): void
    {
        $cat = Category::make('T-Shirts & Tops');
        $this->assertTrue((bool)preg_match('/^[a-z0-9\-]+$/', $cat->getSlug()));
    }

    public function testCustomSlug(): void
    {
        $cat = Category::make('Jeans', null, ['slug' => 'my-jeans']);
        $this->assertEquals('my-jeans', $cat->getSlug());
    }

    public function testHierarchy(): void
    {
        $parent = Category::make('Clothing');
        $child  = Category::make('T-Shirts', $parent->getId());
        $this->assertTrue($parent->isRoot());
        $this->assertFalse($child->isRoot());
        $this->assertCount(1, $parent->children());
        $this->assertEquals($child->getId(), $parent->children()[0]->getId());
    }

    public function testAll(): void
    {
        Category::make('A');
        Category::make('B');
        $this->assertCount(2, Category::all());
    }
}

// =============================================================================
// Attribute
// =============================================================================

class AttributeTest extends TestCase
{
    public function setUp(): void { Attribute::reset(); }

    public function testMakeAndFind(): void
    {
        $a = Attribute::make('Color', ['Red', 'Blue']);
        $this->assertEquals('Color', $a->getName());
        $this->assertContains('Red', $a->getValues());
        $this->assertSame($a, Attribute::find($a->getId()));
    }

    public function testAddValue(): void
    {
        $a = Attribute::make('Size', ['S', 'M']);
        $a->addValue('L');
        $a->addValue('M');  // duplicate — should not add
        $this->assertCount(3, $a->getValues());
    }

    public function testToArray(): void
    {
        $a   = Attribute::make('Material', ['Cotton']);
        $arr = $a->toArray();
        $this->assertArrayHasKey('id', $arr);
        $this->assertEquals('Material', $arr['name']);
    }
}

// =============================================================================
// Variant
// =============================================================================

class VariantTest extends TestCase
{
    public function setUp(): void { Variant::resetSeq(); }

    public function testConstruct(): void
    {
        $v = new Variant(['sku' => 'V-001', 'attributes' => ['Color' => 'Red'], 'price' => 1999, 'stock' => 10]);
        $this->assertEquals('V-001', $v->getSku());
        $this->assertEquals('Red', $v->getAttribute('Color'));
        $this->assertEquals(1999, $v->getPriceCents());
        $this->assertEquals(10, $v->getStock());
        $this->assertTrue($v->isInStock());
    }

    public function testDecrementAndIncrementStock(): void
    {
        $v = new Variant(['stock' => 5, 'track_stock' => true]);
        $v->decrementStock(3);
        $this->assertEquals(2, $v->getStock());
        $v->incrementStock(10);
        $this->assertEquals(12, $v->getStock());
    }

    public function testDecrementStockFloorAtZero(): void
    {
        $v = new Variant(['stock' => 2]);
        $v->decrementStock(10);
        $this->assertEquals(0, $v->getStock());
    }

    public function testNoTrackStock(): void
    {
        $v = new Variant(['stock' => 0, 'track_stock' => false]);
        $this->assertTrue($v->isInStock());
    }

    public function testToArray(): void
    {
        $v   = new Variant(['sku' => 'X', 'price' => 500, 'stock' => 1]);
        $arr = $v->toArray();
        $this->assertArrayHasKey('in_stock', $arr);
        $this->assertTrue($arr['in_stock']);
    }
}

// =============================================================================
// Product
// =============================================================================

class ProductTest extends TestCase
{
    public function setUp(): void
    {
        Product::reset();
        Category::reset();
        Variant::resetSeq();
    }

    public function testMakeAndFind(): void
    {
        $p = Product::make('T-Shirt', 1999);
        $this->assertEquals('T-Shirt', $p->getName());
        $this->assertEquals(1999, $p->getPriceCents());
        $this->assertEquals('19.99', $p->getPrice());
        $this->assertSame($p, Product::find($p->getId()));
    }

    public function testFluentApi(): void
    {
        $cat = Category::make('Apparel');
        $p   = Product::make('Hoodie', 4999)
            ->sku('HDY-001')
            ->description('Warm hoodie')
            ->category($cat)
            ->publish()
            ->image('https://example.com/img.jpg')
            ->meta('weight', 500);

        $this->assertEquals('HDY-001', $p->getSku());
        $this->assertEquals('Warm hoodie', $p->getDescription());
        $this->assertEquals($cat->getId(), $p->getCategoryId());
        $this->assertTrue($p->isPublished());
        $this->assertCount(1, $p->getImages());
        $this->assertEquals(500, $p->getMeta('weight'));
    }

    public function testPublished(): void
    {
        Product::make('Draft', 999)->status('draft');
        Product::make('Live', 999)->publish();
        $this->assertCount(1, Product::published());
    }

    public function testAddVariant(): void
    {
        $p = Product::make('T-Shirt', 1999);
        $v = new Variant(['sku' => 'TS-RED-M', 'attributes' => ['Color' => 'Red', 'Size' => 'M'], 'stock' => 5]);
        $p->addVariant($v);
        $this->assertTrue($p->hasVariants());
        $this->assertCount(1, $p->getVariants());
    }

    public function testToArray(): void
    {
        $p = Product::make('Cap', 999)->sku('CAP-1');
        $p->addVariant(new Variant(['sku' => 'CAP-BLK']));
        $arr = $p->toArray();
        $this->assertEquals('Cap', $arr['name']);
        $this->assertCount(1, $arr['variants']);
    }
}

// =============================================================================
// CartItem
// =============================================================================

class CartItemTest extends TestCase
{
    private function makeItem(int $price = 1000, int $qty = 2): CartItem
    {
        return new CartItem(1, 'Widget', $price, $qty, 'WGT-001', ['Color' => 'Blue'], 0);
    }

    public function testSubtotal(): void
    {
        $item = $this->makeItem(1000, 3);
        $this->assertEquals(3000, $item->subtotalCents());
        $this->assertEquals('30.00', $item->subtotal());
        $this->assertEquals('10.00', $item->unitPrice());
    }

    public function testKey(): void
    {
        $item = new CartItem(5, 'X', 100, 1, '', ['Red'], 3);
        $this->assertStringContainsString('5_3_', $item->key());
    }

    public function testSetQtyMinimumOne(): void
    {
        $item = $this->makeItem(100, 5);
        $item->setQty(0);
        $this->assertEquals(1, $item->getQty());
    }

    public function testIncrementDecrement(): void
    {
        $item = $this->makeItem(100, 5);
        $item->incrementQty(3);
        $this->assertEquals(8, $item->getQty());
        $item->decrementQty(2);
        $this->assertEquals(6, $item->getQty());
    }

    public function testDecrementMinimumOne(): void
    {
        $item = $this->makeItem(100, 1);
        $item->decrementQty(10);
        $this->assertEquals(1, $item->getQty());
    }

    public function testToArray(): void
    {
        $item = $this->makeItem(500, 2);
        $arr  = $item->toArray();
        $this->assertEquals(1, $arr['product_id']);
        $this->assertEquals(2, $arr['qty']);
        $this->assertEquals(1000, $arr['subtotal_cents']);
    }
}

// =============================================================================
// Cart
// =============================================================================

class CartTest extends TestCase
{
    public function setUp(): void
    {
        Cart::reset();
        Product::reset();
        Variant::resetSeq();
    }

    private function makeProduct(string $name = 'Widget', int $price = 1000): Product
    {
        return Product::make($name, $price)->publish();
    }

    public function testAddProduct(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct();
        $item = $cart->add($p, 2);
        $this->assertEquals(2, $item->getQty());
        $this->assertFalse($cart->isEmpty());
        $this->assertEquals(1, $cart->items() ? 1 : 0);
    }

    public function testAddMergesQty(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct();
        $cart->add($p, 2);
        $cart->add($p, 3);
        $this->assertEquals(5, array_values($cart->items())[0]->getQty());
    }

    public function testSubtotalAndItemCount(): void
    {
        $cart = Cart::instance('test');
        $p1   = $this->makeProduct('A', 1000);
        $p2   = $this->makeProduct('B', 2000);
        $cart->add($p1, 2);
        $cart->add($p2, 1);
        $this->assertEquals(4000, $cart->subtotalCents());
        $this->assertEquals('40.00', $cart->subtotal());
        $this->assertEquals(3, $cart->itemCount());
    }

    public function testUpdateQty(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct();
        $item = $cart->add($p, 5);
        $cart->update($item->key(), 2);
        $this->assertEquals(2, $cart->getItem($item->key())->getQty());
    }

    public function testUpdateQtyZeroRemovesItem(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct();
        $item = $cart->add($p);
        $cart->update($item->key(), 0);
        $this->assertNull($cart->getItem($item->key()));
    }

    public function testRemoveItem(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct();
        $item = $cart->add($p);
        $cart->remove($item->key());
        $this->assertTrue($cart->isEmpty());
    }

    public function testClear(): void
    {
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct(), 3);
        $cart->clear();
        $this->assertTrue($cart->isEmpty());
        $this->assertEquals(0, $cart->totalCents());
    }

    public function testAddWithVariant(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct('T-Shirt', 999);
        $v    = new Variant(['sku' => 'TS-RED-M', 'attributes' => ['Color' => 'Red', 'Size' => 'M'], 'price' => 1500, 'stock' => 10]);
        $item = $cart->add($p, 1, $v);
        // variant price override
        $this->assertEquals(1500, $item->getUnitPriceCents());
    }

    public function testAddWithVariantZeroPriceUsesProductPrice(): void
    {
        $cart = Cart::instance('test');
        $p    = $this->makeProduct('Shirt', 2000);
        $v    = new Variant(['sku' => 'S-BLU', 'price' => 0]);   // 0 = inherit
        $item = $cart->add($p, 1, $v);
        $this->assertEquals(2000, $item->getUnitPriceCents());
    }

    // --- Coupons ---

    public function testPercentCoupon(): void
    {
        Cart::registerCoupon('SAVE10', 'percent', 10);
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct('X', 1000), 1);   // subtotal = 1000 (£10)
        $this->assertTrue($cart->applyCoupon('SAVE10'));
        $this->assertEquals(100, $cart->discountCents());  // 10% of 1000
        $this->assertEquals(900, $cart->totalCents());
    }

    public function testFixedCoupon(): void
    {
        Cart::registerCoupon('FLAT200', 'fixed', 200);
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct('Y', 1000), 1);
        $cart->applyCoupon('FLAT200');
        $this->assertEquals(200, $cart->discountCents());
        $this->assertEquals(800, $cart->totalCents());
    }

    public function testDiscountCannotExceedSubtotal(): void
    {
        Cart::registerCoupon('BIG', 'fixed', 9999);
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct('Z', 500), 1);
        $cart->applyCoupon('BIG');
        $this->assertEquals(0, $cart->totalCents());
    }

    public function testInvalidCouponReturnsFalse(): void
    {
        $cart = Cart::instance('test');
        $this->assertFalse($cart->applyCoupon('FAKE'));
    }

    public function testRemoveCoupon(): void
    {
        Cart::registerCoupon('OFF5', 'percent', 5);
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct('W', 1000), 1);
        $cart->applyCoupon('OFF5');
        $cart->removeCoupon('OFF5');
        $this->assertEquals(0, $cart->discountCents());
    }

    public function testCasInsensitiveCoupon(): void
    {
        Cart::registerCoupon('UPPER', 'fixed', 100);
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct(), 1);
        $this->assertTrue($cart->applyCoupon('upper'));
        $this->assertTrue($cart->hasCoupon('UPPER'));
    }

    public function testToArray(): void
    {
        $cart = Cart::instance('test');
        $cart->add($this->makeProduct('P', 500), 2);
        $arr = $cart->toArray();
        $this->assertArrayHasKey('items', $arr);
        $this->assertArrayHasKey('total_cents', $arr);
        $this->assertCount(1, $arr['items']);
    }

    public function testMultipleInstances(): void
    {
        $cart1 = Cart::instance('default');
        $cart2 = Cart::instance('wishlist');
        $cart1->add($this->makeProduct('A', 100));
        $this->assertTrue($cart2->isEmpty());
    }
}

// =============================================================================
// OrderItem
// =============================================================================

class OrderItemTest extends TestCase
{
    public function testFromCartArray(): void
    {
        $data = [
            'product_id'       => 1,
            'product_name'     => 'Widget',
            'sku'              => 'WGT',
            'variant_id'       => 0,
            'attributes'       => [],
            'unit_price_cents' => 999,
            'qty'              => 3,
        ];
        $item = OrderItem::fromCartArray($data);
        $this->assertEquals(1, $item->getProductId());
        $this->assertEquals(999, $item->getUnitPriceCents());
        $this->assertEquals(2997, $item->subtotalCents());
        $this->assertEquals('29.97', $item->subtotal());
    }

    public function testToArray(): void
    {
        $item = OrderItem::fromCartArray([
            'product_id'       => 2,
            'product_name'     => 'Cap',
            'sku'              => 'CAP',
            'variant_id'       => 0,
            'attributes'       => [],
            'unit_price_cents' => 500,
            'qty'              => 2,
        ]);
        $arr = $item->toArray();
        $this->assertEquals(1000, $arr['subtotal_cents']);
        $this->assertEquals('10.00', $arr['subtotal']);
    }
}

// =============================================================================
// Order
// =============================================================================

class OrderTest extends TestCase
{
    public function setUp(): void
    {
        Order::reset();
        Cart::reset();
        Product::reset();
        Variant::resetSeq();
    }

    public function testMake(): void
    {
        $order = Order::make(42);
        $this->assertEquals(42, $order->getCustomerId());
        $this->assertEquals(Order::STATUS_PENDING, $order->getStatus());
        $this->assertTrue($order->isPending());
        $this->assertTrue(str_starts_with($order->getOrderNumber(), 'ORD-'));
    }

    public function testStatusMachine(): void
    {
        $order = Order::make();
        $order->process();
        $this->assertTrue($order->isProcessing());
        $order->ship('TRK-999');
        $this->assertTrue($order->isShipped());
        $this->assertEquals('TRK-999', $order->getTrackingNumber());
        $order->complete();
        $this->assertTrue($order->isCompleted());
    }

    public function testCancel(): void
    {
        $order = Order::make();
        $order->cancel();
        $this->assertTrue($order->isCancelled());
    }

    public function testRefund(): void
    {
        $order = Order::make();
        $order->process()->ship()->complete()->refund();
        $this->assertTrue($order->isRefunded());
    }

    public function testInvalidTransitionThrows(): void
    {
        $order = Order::make();
        $order->cancel();
        $this->expectException(\LogicException::class);
        $order->process();
    }

    public function testCanTransitionTo(): void
    {
        $order = Order::make();
        $this->assertTrue($order->canTransitionTo(Order::STATUS_PROCESSING));
        $this->assertFalse($order->canTransitionTo(Order::STATUS_COMPLETED));
    }

    public function testCreateFromCart(): void
    {
        $p    = Product::make('Widget', 1000)->publish();
        $cart = Cart::instance('order_test');
        $cart->add($p, 3);
        $order = Order::createFromCart($cart, 99);
        $this->assertEquals(3000, $order->subtotalCents());
        $this->assertCount(1, $order->getItems());
    }

    public function testGrandTotal(): void
    {
        $order = Order::make();
        $order->shippingCost(500)->tax(200);
        // subtotal=0, shipping=500, tax=200
        $this->assertEquals(700, $order->grandTotalCents());
    }

    public function testGrandTotalWithDiscount(): void
    {
        $p    = Product::make('Item', 2000)->publish();
        Cart::registerCoupon('HALF', 'percent', 50);
        $cart = Cart::instance('discount_test');
        $cart->add($p, 1);
        $cart->applyCoupon('HALF');
        $order = Order::createFromCart($cart);
        $this->assertEquals(2000, $order->subtotalCents());
        $this->assertEquals(1000, $order->discountCents());
        $this->assertEquals(1000, $order->grandTotalCents());
    }

    public function testRecordPayment(): void
    {
        $order  = Order::make();
        $result = ChargeResult::ok('txn_abc', 5000, 'USD', []);
        $order->recordPayment($result);
        $this->assertEquals('txn_abc', $order->getTransactionId());
    }

    public function testBillingAndShipping(): void
    {
        $order = Order::make();
        $order->billing(['name' => 'Alice', 'city' => 'NYC']);
        $order->shipping(['name' => 'Bob', 'city' => 'LA']);
        $this->assertEquals('Alice', $order->getBillingAddress()['name']);
        $this->assertEquals('LA',    $order->getShippingAddress()['city']);
    }

    public function testStatusHistory(): void
    {
        $order = Order::make();
        $order->process()->cancel();
        $history = $order->getStatusHistory();
        $this->assertCount(3, $history);  // pending, processing, cancelled
        $this->assertEquals(Order::STATUS_PENDING,    $history[0]['status']);
        $this->assertEquals(Order::STATUS_PROCESSING, $history[1]['status']);
        $this->assertEquals(Order::STATUS_CANCELLED,  $history[2]['status']);
    }

    public function testFind(): void
    {
        $o = Order::make(1);
        $this->assertSame($o, Order::find($o->getId()));
    }

    public function testForCustomer(): void
    {
        Order::make(10);
        Order::make(10);
        Order::make(20);
        $this->assertCount(2, Order::forCustomer(10));
    }

    public function testToArray(): void
    {
        $order = Order::make(5);
        $arr   = $order->toArray();
        $this->assertArrayHasKey('order_number', $arr);
        $this->assertArrayHasKey('grand_total', $arr);
        $this->assertArrayHasKey('status_history', $arr);
    }
}

// =============================================================================
// Invoice
// =============================================================================

class InvoiceTest extends TestCase
{
    public function setUp(): void
    {
        Order::reset();
        Invoice::resetSeq();
        Cart::reset();
        Product::reset();
    }

    private function makeOrder(): Order
    {
        $p    = Product::make('Widget', 1999)->publish();
        $cart = Cart::instance('inv_test');
        $cart->add($p, 2);
        $order = Order::createFromCart($cart, 1);
        $order->billing(['name' => 'Alice', 'line1' => '1 Main St', 'city' => 'NYC', 'zip' => '10001', 'country' => 'US']);
        return $order;
    }

    public function testNumberFormat(): void
    {
        $order   = $this->makeOrder();
        $invoice = Invoice::for($order);
        $this->assertTrue(str_starts_with($invoice->number(), 'INV-'));
    }

    public function testToTextContainsOrderInfo(): void
    {
        $order   = $this->makeOrder();
        $invoice = Invoice::for($order);
        $text    = $invoice->toText();
        $this->assertStringContainsString('INVOICE:', $text);
        $this->assertStringContainsString('Widget', $text);
        $this->assertStringContainsString('39.98', $text);  // 2 × 19.99
    }

    public function testToHtmlContainsOrderInfo(): void
    {
        $order   = $this->makeOrder();
        $invoice = Invoice::for($order);
        $html    = $invoice->toHtml();
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('INV-', $html);
    }

    public function testToArray(): void
    {
        $order   = $this->makeOrder();
        $invoice = Invoice::for($order);
        $arr     = $invoice->toArray();
        $this->assertArrayHasKey('number', $arr);
        $this->assertArrayHasKey('order', $arr);
    }

    public function testMeta(): void
    {
        $invoice = Invoice::for($this->makeOrder());
        $invoice->meta('note', 'Thank you!');
        $arr = $invoice->toArray();
        $this->assertEquals('Thank you!', $arr['meta']['note']);
    }
}

// =============================================================================
// StockItem
// =============================================================================

class StockItemTest extends TestCase
{
    public function setUp(): void
    {
        StockItem::reset();
    }

    public function testForCreatesOnce(): void
    {
        $s1 = StockItem::for(1);
        $s2 = StockItem::for(1);
        $this->assertSame($s1, $s2);
    }

    public function testSetAndGetQty(): void
    {
        $s = StockItem::for(1)->setQty(50);
        $this->assertEquals(50, $s->getQty());
        $this->assertTrue($s->isInStock());
    }

    public function testIncrementDecrement(): void
    {
        $s = StockItem::for(1)->setQty(10);
        $s->increment(5);
        $this->assertEquals(15, $s->getQty());
        $s->decrement(3);
        $this->assertEquals(12, $s->getQty());
    }

    public function testDecrementFloorAtZero(): void
    {
        $s = StockItem::for(1)->setQty(2);
        $s->decrement(10);
        $this->assertEquals(0, $s->getQty());
        $this->assertTrue($s->isOutOfStock());
    }

    public function testStrictDecrementThrows(): void
    {
        $s = StockItem::for(1)->setQty(1);
        $this->expectException(\UnderflowException::class);
        $s->decrement(5, true);
    }

    public function testReservation(): void
    {
        $s = StockItem::for(1)->setQty(10);
        $s->reserve(3);
        $this->assertEquals(7, $s->available());
        $this->assertTrue($s->canFulfill(7));
        $this->assertFalse($s->canFulfill(8));
        $s->release(1);
        $this->assertEquals(8, $s->available());
    }

    public function testCommitReservation(): void
    {
        $s = StockItem::for(1)->setQty(10);
        $s->reserve(3);
        $s->commitReservation(3);
        $this->assertEquals(0, $s->getReserved());
        $this->assertEquals(7, $s->getQty());
    }

    public function testLowStockThreshold(): void
    {
        $s = StockItem::for(1)->setQty(10)->setLowStockThreshold(5);
        $this->assertFalse($s->isLowStock());
        $s->decrement(6);  // qty → 4, which is ≤ 5
        $this->assertTrue($s->isLowStock());
    }

    public function testLowStockAlerts(): void
    {
        StockItem::clearAlerts();
        $s = StockItem::for(1)->setQty(5)->setLowStockThreshold(5);
        $s->decrement(1);   // 4 ≤ 5 → triggers alert
        $this->assertNotEmpty(StockItem::getAlerts());
    }

    public function testLowStockItems(): void
    {
        $s1 = StockItem::for(1)->setQty(2)->setLowStockThreshold(5);
        $s2 = StockItem::for(2)->setQty(10)->setLowStockThreshold(5);
        // s1: available=2 ≤ 5 → low; s2: available=10 > 5 → ok
        $low = StockItem::lowStockItems();
        $this->assertCount(1, $low);
        $this->assertEquals(1, $low[0]->getProductId());
    }

    public function testVariantKey(): void
    {
        $s = StockItem::for(5, 3)->setQty(7);
        $this->assertEquals(5, $s->getProductId());
        $this->assertEquals(3, $s->getVariantId());
        $this->assertNull(StockItem::find(5, 99));
    }

    public function testAll(): void
    {
        StockItem::for(1);
        StockItem::for(2);
        $this->assertCount(2, StockItem::all());
    }

    public function testToArray(): void
    {
        $s   = StockItem::for(1)->setQty(10)->setLowStockThreshold(3);
        $arr = $s->toArray();
        $this->assertArrayHasKey('available', $arr);
        $this->assertArrayHasKey('low_stock', $arr);
    }
}
