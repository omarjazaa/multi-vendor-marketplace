<?php

use App\Notifications\Channels\DatabaseNotificationChannel;
use App\Notifications\Channels\MailNotificationChannel;
use App\Services\Payments\Strategies\BankTransferStrategy;
use App\Services\Payments\Strategies\CardPaymentStrategy;
use App\Services\Payments\Strategies\CashOnDeliveryStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Product Catalog & Inventory
    |--------------------------------------------------------------------------
    |
    | Catalog rules live in configuration so that upload constraints (storage
    | disk, image count and size) and inventory defaults (alert threshold and
    | maximum trackable quantity) are declared once and shared by the request
    | validation layer and the domain services without duplicating magic values.
    |
    */

    'products' => [

        'images' => [
            'disk' => env('PRODUCT_IMAGES_DISK', 'public'),
            'max_per_product' => 5,
            'max_size_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

    ],

    'inventory' => [

        'default_low_stock_threshold' => 5,
        'max_quantity' => 1_000_000,

    ],

    /*
    |--------------------------------------------------------------------------
    | Public Catalog
    |--------------------------------------------------------------------------
    |
    | The guest facing catalog reads its paging and sorting rules from here so
    | the request validation layer, the catalog service and the repository all
    | agree on a single source of truth for defaults and allowed sort keys.
    |
    */

    'catalog' => [

        'default_per_page' => 12,
        'max_per_page' => 50,

        'default_sort' => 'latest',
        'sorts' => [
            'latest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'price_asc' => ['base_price', 'asc'],
            'price_desc' => ['base_price', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Shopping Cart
    |--------------------------------------------------------------------------
    |
    | Per line quantity ceiling shared by the cart validation rules and the
    | cart service so the buy limit is declared exactly once.
    |
    */

    'cart' => [

        'max_quantity_per_item' => 99,

    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Checkout rules live here: the status a fresh order receives, the payment
    | methods the API accepts, how many cart lines a single order may hold,
    | the page size used when customers browse their order history and the
    | fraud heuristics the checkout validation chain enforces.
    |
    */

    'checkout' => [

        'default_status' => 'pending',

        /*
        | The methods the API accepts. Every entry must also be registered in
        | marketplace.payments.methods below — the payment factory throws for
        | anything missing there — and tests/Feature/PaymentMethodsTest.php
        | fails if the two lists ever drift apart.
        */

        'payment_methods' => ['cod', 'card', 'bank_transfer'],
        'max_items_per_order' => 50,
        'orders_per_page' => 15,

        /*
        | Fraud heuristics applied by the checkout validation chain before a
        | single write happens. A zero disables its rule; the ceilings are
        | environment-tunable so ops can adjust them without shipping code.
        */

        'fraud' => [

            // Reject a cart whose subtotal exceeds this ceiling.
            'max_order_total' => (float) env('MARKETPLACE_FRAUD_MAX_ORDER_TOTAL', 1000),

            // Reject the (max + 1)-th order a customer places within the window.
            'max_orders_per_window' => (int) env('MARKETPLACE_FRAUD_MAX_ORDERS_PER_WINDOW', 5),
            'window_minutes' => (int) env('MARKETPLACE_FRAUD_WINDOW_MINUTES', 60),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | The Strategy + Factory pair behind checkout: each accepted method maps
    | to the strategy that settles it and PaymentStrategyFactory resolves the
    | right one at runtime. Registering a method means adding an entry here —
    | the factory itself never names a strategy class.
    |
    */

    'payments' => [

        'methods' => [
            'cod' => CashOnDeliveryStrategy::class,
            'card' => CardPaymentStrategy::class,
            'bank_transfer' => BankTransferStrategy::class,
        ],

        /*
        | Simulated card gateway: no real provider is wired up, so the outcome
        | is driven by MARKETPLACE_CARD_PAYMENT_SUCCEEDS. Off by default, which
        | means card checkouts are declined and the order stays unpaid — the
        | pre-Day-17 lifecycle is preserved until an operator opts in.
        */

        'card' => [
            'simulate_success' => (bool) env('MARKETPLACE_CARD_PAYMENT_SUCCEEDS', false),
            'decline_message' => env(
                'MARKETPLACE_CARD_DECLINE_MESSAGE',
                'Your card was declined. Try another payment method or choose cash on delivery.',
            ),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Order Fulfilment & Management
    |--------------------------------------------------------------------------
    |
    | Order lifecycle transitions allowed in the system, plus pagination
    | settings for vendor and admin order listings.
    |
    */

    'orders' => [

        'vendor_per_page' => 15,
        'admin_per_page' => 15,

        'filter_statuses' => ['pending', 'paid', 'cancelled', 'shipped', 'delivered'],

        'transitions' => [
            'pending' => ['paid', 'cancelled'],
            'paid' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'cancelled' => [],
            'delivered' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | The pricing engine reads its tax rate and the automatic (no-code)
    | site discount here, so the Decorator pipeline and its validation rules
    | share one source of truth instead of carrying magic numbers.
    |
    */

    'pricing' => [

        'tax_rate' => (float) env('MARKETPLACE_TAX_RATE', 0.05),

        'site_discount_type' => env('MARKETPLACE_SITE_DISCOUNT_TYPE', 'percentage'),
        'site_discount_value' => (float) env('MARKETPLACE_SITE_DISCOUNT_VALUE', 0),

    ],

    /*
    |--------------------------------------------------------------------------
    | Order Notifications
    |--------------------------------------------------------------------------
    |
    | The channel Strategy + Factory behind order alerts mirrors the payment
    | pair: each key maps to the channel that delivers it and
    | NotificationChannelFactory resolves the right one at runtime.
    | Registering a channel means adding an entry here — the factory itself
    | never names a channel class.
    |
    */

    'notifications' => [

        'channels' => [
            'database' => DatabaseNotificationChannel::class,
            'mail' => MailNotificationChannel::class,
        ],

        // Which configured channel carries the customer confirmation and
        // which carries the per-vendor new-order alerts.
        'customer_channel' => env('MARKETPLACE_CUSTOMER_CHANNEL', 'database'),
        'vendor_channel' => env('MARKETPLACE_VENDOR_CHANNEL', 'database'),

        // Queue the order Mailable instead of sending it inline.
        'queue_mail' => (bool) env('MARKETPLACE_QUEUE_MAIL', true),

    ],

];
