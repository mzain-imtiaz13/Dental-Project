<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('api_name'); // medit_link, ds_core, 3shape
            $table->string('client_id');
            $table->text('client_secret'); // encrypted
            $table->text('access_token')->nullable(); // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->timestamp('token_expiry')->nullable();
            $table->string('base_url')->nullable(); // API base URL
            $table->json('additional_config')->nullable(); // for API-specific settings
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['api_name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
