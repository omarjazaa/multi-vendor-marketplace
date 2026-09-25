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

];
