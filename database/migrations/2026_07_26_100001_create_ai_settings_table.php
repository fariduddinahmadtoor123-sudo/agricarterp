<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->text('openrouter_api_key')->nullable();
            $table->string('vision_model', 120)->default('google/gemini-2.0-flash-001');
            $table->boolean('enrichment_enabled')->default(true);
            $table->unsignedInteger('batch_limit')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
