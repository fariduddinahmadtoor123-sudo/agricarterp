<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('attribute_values');

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropIndex(['attribute_type']);
            $table->dropIndex(['values_count']);
            $table->dropIndex(['is_standard']);
            $table->dropIndex(['sort_order']);

            $table->dropColumn([
                'attribute_type',
                'values_count',
                'is_standard',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('attribute_type', 20)->after('name');
            $table->unsignedInteger('values_count')->default(0)->after('status');
            $table->boolean('is_standard')->default(false)->after('values_count');
            $table->unsignedInteger('sort_order')->default(0)->after('is_standard');

            $table->index('attribute_type');
            $table->index('values_count');
            $table->index('is_standard');
            $table->index('sort_order');
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
};
