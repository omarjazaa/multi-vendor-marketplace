<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Reviews & Ratings (Day 22 — PRD §5 Week 4).
     *
     * Creates the reviews table linking verified buyer orders to products,
     * and adds cached average rating and reviews count to the products table.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('average_rating', 3, 2)->default(0.00)->after('base_price');
            $table->unsignedInteger('reviews_count')->default(0)->after('average_rating');
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->timestamps();

            // A customer can review a given product once
            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['average_rating', 'reviews_count']);
        });
    }
};
