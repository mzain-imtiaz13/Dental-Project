<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medit_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Link to your stored credential row
            $table->foreignId('credential_id')
                ->constrained('api_credentials')
                ->cascadeOnDelete();

            $table->string('email', 191)->unique();
            $table->string('name')->nullable();

            $table->string('group_uuid', 191)->nullable();
            $table->foreign('group_uuid')
                ->references('uuid')->on('medit_groups')
                ->nullOnDelete();

            $table->timestampTz('date_created')->nullable();
            $table->timestampTz('date_updated')->nullable();

            $table->json('profile_image')->nullable();
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index('group_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medit_profiles');
    }
};
