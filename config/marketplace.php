<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product Catalog
    |--------------------------------------------------------------------------
    |
    | Catalog rules live in configuration so that upload constraints (storage
    | disk, image count and size) are declared once and shared by the request
    | validation layer and the image service without duplicating magic values.
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

];
