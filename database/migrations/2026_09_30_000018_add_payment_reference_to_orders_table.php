<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Day 17: the reference the payment strategy issued for this
            // order (COD-000042, CARD-000042, ...). It has to be a column
            // rather than response-only data because support has to be able
            // to trace an order to its payment attempt long after the
            // checkout response is gone. Nullable: orders placed without a
            // payment method, and ones whose payment was declined, have none.
            $table->string('payment_reference', 64)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('payment_reference');
        });
    }
};
