<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medit_cases', function (Blueprint $table) {
            // Case UUIDs are classic 36-char GUIDs
            $table->string('uuid', 36)->primary();

            $table->foreignId('credential_id')
                ->constrained('api_credentials')
                ->cascadeOnDelete();

            $table->string('group_uuid', 191)->nullable();
            $table->foreign('group_uuid')
                ->references('uuid')->on('medit_groups')
                ->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('status', 32)->nullable();

            $table->timestampTz('date_created')->nullable();
            $table->timestampTz('date_updated')->nullable();
            $table->timestampTz('date_scanned')->nullable();

            $table->string('patient_name')->nullable();
            $table->string('patient_code', 191)->nullable();

            $table->json('tags')->nullable();
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['date_created', 'date_updated']);
            $table->index('group_uuid');
            $table->index('patient_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medit_cases');
    }
};
