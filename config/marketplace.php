<?php

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

];
