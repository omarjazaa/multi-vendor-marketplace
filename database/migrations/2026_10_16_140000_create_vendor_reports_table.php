<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Background Jobs & Queues (Day 25 — PRD §5 Week 4).
     * Creates the vendor_reports table for asynchronous report generation jobs.
     */
    public function up(): void
    {
        Schema::create('vendor_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('parameters')->nullable();
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_reports');
    }
};
