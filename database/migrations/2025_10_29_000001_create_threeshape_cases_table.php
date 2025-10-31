<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('three_shape_cases', function (Blueprint $table) {
            $table->id();

            // Link to the credential we used for the sync (3shape row in api_credentials)
            $table->foreignId('api_credential_id')
                  ->constrained('api_credentials')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Case identifiers & core fields
            $table->uuid('external_id')->index();              // 3Shape case UUID
            $table->string('patient_name')->nullable();
            $table->string('state', 100)->nullable();

            // Important timestamps coming from 3Shape
            $table->timestamp('created_at_3s')->nullable();     // created/createdAt from API
            $table->timestamp('delivery_date')->nullable();

            // Full raw payload (optional – for diagnostics)
            $table->json('raw')->nullable();

            $table->timestamps();

            // Don’t duplicate the same external case for the same credential
            $table->unique(['api_credential_id', 'external_id'], 'uniq_cred_case');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('three_shape_cases');
    }
};
