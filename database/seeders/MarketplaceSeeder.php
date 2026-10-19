<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Dispute;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Users
        $admin = User::firstOrCreate(['email' => 'admin@marketplace.com'], [
            'name' => 'System Administrator',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);

        $customer = User::firstOrCreate(['email' => 'customer@marketplace.com'], [
            'name' => 'Sarah Connor',
            'password' => Hash::make('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $vendor1User = User::firstOrCreate(['email' => 'tech@marketplace.com'], [
            'name' => 'Alex Rivera',
            'password' => Hash::make('password'),
            'role' => UserRole::VENDOR,
        ]);

        $vendor2User = User::firstOrCreate(['email' => 'apparel@marketplace.com'], [
            'name' => 'Elena Rostova',
            'password' => Hash::make('password'),
            'role' => UserRole::VENDOR,
        ]);

        $vendor3User = User::firstOrCreate(['email' => 'artisan@marketplace.com'], [
            'name' => 'Marcus Vance',
            'password' => Hash::make('password'),
            'role' => UserRole::VENDOR,
        ]);

        // 2. Vendor Profiles & Stores
        $vp1 = VendorProfile::firstOrCreate(['user_id' => $vendor1User->id], [
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);
        $store1 = Store::firstOrCreate(['vendor_profile_id' => $vp1->id], [
            'name' => 'Apex Electronics',
            'slug' => 'apex-electronics',
            'description' => 'Premium tech gadgets, laptops, and professional workstation gear.',
            'status' => 'approved',
        ]);

        $vp2 = VendorProfile::firstOrCreate(['user_id' => $vendor2User->id], [
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);
        $store2 = Store::firstOrCreate(['vendor_profile_id' => $vp2->id], [
            'name' => 'Luxe Lifestyle',
            'slug' => 'luxe-lifestyle',
            'description' => 'Modern apparel, minimalist watches, and bespoke leather goods.',
            'status' => 'approved',
        ]);

        $vp3 = VendorProfile::firstOrCreate(['user_id' => $vendor3User->id], [
            'verification_status' => 'pending',
        ]);
        $store3 = Store::firstOrCreate(['vendor_profile_id' => $vp3->id], [
            'name' => 'Artisan Handcrafts',
            'slug' => 'artisan-handcrafts',
            'description' => 'Handcrafted home decor, ceramics, and sustainable goods.',
            'status' => 'pending',
        ]);

        // 3. Hierarchical Categories
        $electronics = Category::firstOrCreate(['slug' => 'electronics'], [
            'name' => 'Electronics',
        ]);
        $laptops = Category::firstOrCreate(['slug' => 'laptops'], [
            'name' => 'Laptops & Workstations',
            'parent_id' => $electronics->id,
        ]);
        $audio = Category::firstOrCreate(['slug' => 'audio'], [
            'name' => 'Audio & Headphones',
            'parent_id' => $electronics->id,
        ]);

        $fashion = Category::firstOrCreate(['slug' => 'fashion'], [
            'name' => 'Fashion & Apparel',
        ]);
        $watches = Category::firstOrCreate(['slug' => 'watches'], [
            'name' => 'Luxury Watches',
            'parent_id' => $fashion->id,
        ]);
        $outerwear = Category::firstOrCreate(['slug' => 'outerwear'], [
            'name' => 'Jackets & Outerwear',
            'parent_id' => $fashion->id,
        ]);

        // 4. Products & Inventory
        $productsData = [
            [
                'store' => $store1,
                'category' => $laptops,
                'name' => 'ProBook Ultra 16" OLED',
                'slug' => 'probook-ultra-16-oled',
                'description' => 'M3 Ultra equivalent workstation with 3.2K 120Hz OLED, 32GB RAM, 1TB SSD.',
                'base_price' => 1499.00,
                'quantity' => 25,
                'rating' => 4.90,
            ],
            [
                'store' => $store1,
                'category' => $audio,
                'name' => 'AcousticPure Noise-Cancelling Headphones',
                'slug' => 'acousticpure-nc-headphones',
                'description' => 'Hi-Res certified wireless headphones with hybrid ANC and 45-hour battery life.',
                'base_price' => 289.00,
                'quantity' => 40,
                'rating' => 4.80,
            ],
            [
                'store' => $store1,
                'category' => $electronics,
                'name' => 'Thunderbolt 4 Magnetic Docking Hub',
                'slug' => 'thunderbolt-4-docking-hub',
                'description' => '12-in-1 universal hub with dual 4K 60Hz video output and 100W Power Delivery.',
                'base_price' => 149.00,
                'quantity' => 50,
                'rating' => 4.60,
            ],
            [
                'store' => $store2,
                'category' => $watches,
                'name' => 'Chronos Minimalist Automatic Watch',
                'slug' => 'chronos-minimalist-automatic-watch',
                'description' => 'Sapphire crystal face, Japanese automatic movement, genuine Italian leather strap.',
                'base_price' => 320.00,
                'quantity' => 15,
                'rating' => 4.95,
            ],
            [
                'store' => $store2,
                'category' => $outerwear,
                'name' => 'WeatherShield Merino Technical Parka',
                'slug' => 'weathershield-merino-parka',
                'description' => 'Breathable waterproof shell lined with natural merino wool for all-season protection.',
                'base_price' => 245.00,
                'quantity' => 30,
                'rating' => 4.75,
            ],
            [
                'store' => $store2,
                'category' => $fashion,
                'name' => 'Signature Full-Grain Leather Weekender Bag',
                'slug' => 'signature-leather-weekender-bag',
                'description' => 'Handcrafted duffel bag with brass hardware and dedicated shoe compartment.',
                'base_price' => 195.00,
                'quantity' => 20,
                'rating' => 4.85,
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $data) {
            $product = Product::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'store_id' => $data['store']->id,
                    'category_id' => $data['category']->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'base_price' => $data['base_price'],
                    'is_active' => true,
                    'average_rating' => $data['rating'],
                    'reviews_count' => 12,
                ],
            );

            Inventory::firstOrCreate(
                ['product_id' => $product->id],
                [
                    'quantity' => $data['quantity'],
                    'low_stock_threshold' => 5,
                ],
            );

            $createdProducts[] = $product;
        }

        // 5. Discount Coupons
        Coupon::firstOrCreate(['code' => 'WELCOME10'], [
            'type' => 'percentage',
            'value' => 10.00,
            'expires_at' => now()->addMonths(6),
        ]);

        Coupon::firstOrCreate(['code' => 'SUPER50'], [
            'type' => 'fixed',
            'value' => 50.00,
            'min_order_amount' => 100.00,
            'expires_at' => now()->addMonths(3),
        ]);

        // 6. Sample Orders & Disputes
        // Order 1: Delivered (Has a pending customer dispute!)
        $order1 = Order::firstOrCreate(
            ['payment_reference' => 'CARD-100001'],
            [
                'user_id' => $customer->id,
                'store_id' => $store1->id,
                'status' => OrderStatus::DELIVERED,
                'payment_method' => 'card',
                'discount' => '0.00',
                'tax' => '14.45',
                'total_price' => '303.45',
            ],
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order1->id, 'product_id' => $createdProducts[1]->id],
            [
                'name' => $createdProducts[1]->name,
                'unit_price' => '289.00',
                'quantity' => 1,
            ],
        );

        Dispute::firstOrCreate(
            ['order_id' => $order1->id],
            [
                'user_id' => $customer->id,
                'reason' => 'Right ear cup has a persistent buzzing sound right out of the box.',
                'status' => 'pending',
            ],
        );

        Review::firstOrCreate(
            ['product_id' => $createdProducts[1]->id, 'user_id' => $customer->id],
            [
                'order_id' => $order1->id,
                'rating' => 5,
                'comment' => 'Outstanding build quality and fast shipping! Highly recommended.',
            ],
        );

        // Order 2: Shipped (Ready for Day 27 forced refund test!)
        $order2 = Order::firstOrCreate(
            ['payment_reference' => 'BANK-100002'],
            [
                'user_id' => $customer->id,
                'store_id' => $store2->id,
                'status' => OrderStatus::SHIPPED,
                'payment_method' => 'bank_transfer',
                'discount' => '32.00',
                'tax' => '14.40',
                'total_price' => '302.40',
            ],
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'product_id' => $createdProducts[3]->id],
            [
                'name' => $createdProducts[3]->name,
                'unit_price' => '320.00',
                'quantity' => 1,
            ],
        );

        // Order 3: Paid (Awaiting shipment by vendor)
        $order3 = Order::firstOrCreate(
            ['payment_reference' => 'COD-100003'],
            [
                'user_id' => $customer->id,
                'store_id' => $store1->id,
                'status' => OrderStatus::PAID,
                'payment_method' => 'cod',
                'discount' => '0.00',
                'tax' => '7.45',
                'total_price' => '156.45',
            ],
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order3->id, 'product_id' => $createdProducts[2]->id],
            [
                'name' => $createdProducts[2]->name,
                'unit_price' => '149.00',
                'quantity' => 1,
            ],
        );

        // Order 4: Already Refunded (Polymorphic gateway test reference)
        Order::firstOrCreate(
            ['payment_reference' => 'CARD-100004'],
            [
                'user_id' => $customer->id,
                'store_id' => $store2->id,
                'status' => OrderStatus::REFUNDED,
                'payment_method' => 'card',
                'refund_reference' => 'REF-CARD-100004',
                'refund_reason' => 'Customer changed mind before packaging.',
                'discount' => '0.00',
                'tax' => '12.25',
                'total_price' => '257.25',
            ],
        );
    }
}
