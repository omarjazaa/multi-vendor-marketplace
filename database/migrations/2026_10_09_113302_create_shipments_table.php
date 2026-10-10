<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mock shipment tracker (Day 21): records the simulated carrier's reference
     * against the order line it ships. PRD §7 columns: id, order_item_id,
     * tracking_number, status, shipped_at, delivered_at.
     *
     * Decided semantics (documented): a vendor ships the whole order they own —
     * one ship action per order, since per-vendor orders belong to one store —
     * so the tracker holds one row per order, keyed by the first order line's
     * id; delivery tracking (delivered_at) belongs to the Day 22 track
     * endpoint, not this mock.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_item_id')->unique()->index();

            $table->string('tracking_number', 40)->unique();
            $table->string('status', 20)->default('processing');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
