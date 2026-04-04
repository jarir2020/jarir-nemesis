<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    | ISO 4217 currency code used for display and payment processing.
    */
    'currency' => env('SHOP_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    | Default payment driver: manual | stripe | paypal
    */
    'payment' => [
        'default' => env('PAYMENT_DRIVER', 'manual'),

        'drivers' => [
            'stripe' => [
                'secret_key'        => env('STRIPE_SECRET_KEY', ''),
                'publishable_key'   => env('STRIPE_PUBLISHABLE_KEY', ''),
                'webhook_secret'    => env('STRIPE_WEBHOOK_SECRET', ''),
            ],
            'paypal' => [
                'client_id'     => env('PAYPAL_CLIENT_ID', ''),
                'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
                'sandbox'       => (bool) env('PAYPAL_SANDBOX', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    | tax_rate: decimal (e.g. 0.10 = 10%). Set to 0 to disable.
    */
    'tax' => [
        'enabled'  => (bool) env('SHOP_TAX_ENABLED', false),
        'rate'     => (float) env('SHOP_TAX_RATE', 0.0),
        'included' => (bool) env('SHOP_TAX_INCLUDED', false), // price already includes tax?
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    | flat_rate: cents. 0 = free shipping.
    */
    'shipping' => [
        'flat_rate'         => (int) env('SHOP_SHIPPING_RATE', 0),
        'free_over_cents'   => (int) env('SHOP_FREE_SHIPPING_OVER', 0), // 0 = never free
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */
    'inventory' => [
        'track_stock'          => (bool) env('SHOP_TRACK_STOCK', true),
        'low_stock_threshold'  => (int) env('SHOP_LOW_STOCK_THRESHOLD', 5),
        'allow_backorders'     => (bool) env('SHOP_ALLOW_BACKORDERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'session_key'    => 'nemesis_cart',
        'max_qty_per_item' => (int) env('SHOP_MAX_QTY_PER_ITEM', 99),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    'orders' => [
        'number_prefix' => env('SHOP_ORDER_PREFIX', 'ORD'),
    ],

];
