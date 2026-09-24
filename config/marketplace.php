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

];
