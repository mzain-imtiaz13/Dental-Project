<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medit_orders', function (Blueprint $table) {
            // orderNumber is globally unique in Medit; use as PK
            $table->unsignedBigInteger('order_number')->primary();

            $table->foreignId('credential_id')
                ->constrained('api_credentials')
                ->cascadeOnDelete();

            // Link to case (nullable—if case not synced yet we still keep the order)
            $table->string('case_uuid', 36)->nullable();
            $table->foreign('case_uuid')
                ->references('uuid')->on('medit_cases')
                ->nullOnDelete();

            // Buyer/Seller groups
            $table->string('buyer_group_uuid', 191)->nullable();
            $table->foreign('buyer_group_uuid')
                ->references('uuid')->on('medit_groups')
                ->nullOnDelete();

            $table->string('seller_group_uuid', 191)->nullable();
            $table->foreign('seller_group_uuid')
                ->references('uuid')->on('medit_groups')
                ->nullOnDelete();

            $table->string('buyer_name')->nullable();
            $table->string('buyer_type', 32)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_type', 32)->nullable();

            $table->string('status', 32)->nullable();

            $table->timestampTz('date_created')->nullable();
            $table->timestampTz('date_updated')->nullable();
            $table->timestampTz('date_desired_delivery')->nullable();

            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['date_created', 'date_updated']);
            $table->index(['buyer_group_uuid', 'seller_group_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medit_orders');
    }
};
