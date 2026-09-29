<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Day 16: a checkout carries a cart-wide discount and tax; each
            // vendor's split order stores its proportional share (largest-
            // remainder allocation) so per-order money always sums back to
            // the cart summary. Default 0 keeps pre-Day-16 rows and factory
            // orders valid without a backfill.
            $table->decimal('discount', 12, 2)->default(0)->after('payment_method');
            $table->decimal('tax', 12, 2)->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount', 'tax']);
        });
    }
};
