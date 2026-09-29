<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Day 15: a checkout splits one cart into per-vendor orders, so each
            // order records the store it belongs to. Nullable + nullOnDelete keeps
            // receipts valid even if the store row ever disappears (mirrors
            // coupons.created_by); the vendor link on history is then simply gone.
            $table->foreignId('store_id')
                ->nullable()
                ->after('user_id')
                ->constrained('stores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
