<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create vendor onboarding profiles.
     */
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove vendor onboarding profiles.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};
