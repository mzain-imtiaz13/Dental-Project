<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dscore_orders', function (Blueprint $table) {
            $table->id();

            // DS Core order ID (string UUID from API)
            $table->string('order_id', 64)->unique();

            $table->foreignId('credential_id')
                ->constrained('api_credentials')
                ->cascadeOnDelete();

            // Order details
            $table->string('order_number')->nullable();
            $table->string('status', 64)->nullable();
            $table->string('order_type', 64)->nullable();

            // Patient info
            $table->string('patient_name')->nullable();
            $table->string('patient_id')->nullable();

            // Practice/Lab info
            $table->string('practice_name')->nullable();
            $table->string('practice_id')->nullable();
            $table->string('lab_name')->nullable();
            $table->string('lab_id')->nullable();

            // Dates
            $table->timestampTz('order_date')->nullable();
            $table->timestampTz('due_date')->nullable();
            $table->timestampTz('shipped_date')->nullable();

            // Store full API response
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('order_date');
            $table->index(['credential_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dscore_orders');
    }
};
