<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('attribute_number', 12)->unique();
            $table->string('name', 100);
            $table->string('attribute_type', 20);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('values_count')->default(0);
            $table->boolean('is_standard')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('attribute_type');
            $table->index('name');
            $table->index('values_count');
            $table->index('is_standard');
            $table->index('sort_order');
            $table->index('created_at');
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->restrictOnDelete();
            $table->string('value', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['attribute_id', 'sort_order']);
            $table->index('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
