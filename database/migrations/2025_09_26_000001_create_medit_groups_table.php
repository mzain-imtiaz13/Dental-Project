<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medit_groups', function (Blueprint $table) {
            // Group UUIDs in Medit are base64-like strings (can include '='), so give room.
            $table->string('uuid', 191)->primary();

            $table->string('name')->nullable();
            $table->string('type', 32)->nullable(); // LAB | CLINIC | ...
            $table->text('description')->nullable();

            $table->timestampTz('date_created')->nullable();
            $table->timestampTz('date_updated')->nullable();

            // Keep the raw blob for debugging/version drifts
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index(['date_created', 'date_updated']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medit_groups');
    }
};
