<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_controls', function (Blueprint $table) {
            $table->id();
            $table->string('control_number', 12)->unique();
            $table->string('name', 500);
            $table->string('control_type', 30);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('control_type');
            $table->index('name');
            $table->index('created_at');
        });

        Schema::create('product_control_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_number', 12)->unique();
            $table->string('name', 200);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('controls_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('name');
            $table->index('controls_count');
            $table->index('created_at');
        });

        Schema::create('product_control_group_control', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_group_id')
                ->constrained('product_control_groups')
                ->restrictOnDelete();
            $table->foreignId('control_id')
                ->constrained('product_controls')
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['control_group_id', 'control_id']);
            $table->index('control_id');
            $table->index(['control_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_control_group_control');
        Schema::dropIfExists('product_control_groups');
        Schema::dropIfExists('product_controls');
    }
};
