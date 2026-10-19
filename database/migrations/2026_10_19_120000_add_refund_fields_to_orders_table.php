<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Day 27: polymorphic gateway refund reference and dispute/admin refund reason
            $table->string('refund_reference', 64)->nullable()->after('payment_reference');
            $table->text('refund_reason')->nullable()->after('refund_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refund_reference', 'refund_reason']);
        });
    }
};
