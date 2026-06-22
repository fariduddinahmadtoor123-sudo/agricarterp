<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_number', 12)->unique();
            $table->string('name_en', 100);
            $table->string('abbreviation_en', 20);
            $table->string('name_ur', 100)->nullable();
            $table->string('abbreviation_ur', 20)->nullable();
            $table->text('usage_notes')->nullable();
            $table->string('unit_type', 20);
            $table->string('ai_status', 20)->default('pending');
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('ai_version', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_standard')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('unit_type');
            $table->index('name_en');
            $table->index('abbreviation_en');
            $table->index('ai_status');
            $table->index('is_standard');
            $table->index('sort_order');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
