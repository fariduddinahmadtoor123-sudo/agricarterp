<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
            $table->string('category_number', 12)->unique();
            $table->string('visual_mapping_code', 128);
            $table->string('full_path', 1000);
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('is_leaf')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name_en');
            $table->string('name_ur');
            $table->string('image_path', 500)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ur')->nullable();
            $table->text('short_description_en')->nullable();
            $table->text('short_description_ur')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->text('usage_en')->nullable();
            $table->text('usage_ur')->nullable();
            $table->text('benefits_en')->nullable();
            $table->text('benefits_ur')->nullable();
            $table->text('warnings_en')->nullable();
            $table->text('warnings_ur')->nullable();
            $table->text('import_export_notes_en')->nullable();
            $table->text('import_export_notes_ur')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('products_count')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index(['parent_id', 'sort_order']);
            $table->index('status');
            $table->index('level');
            $table->index('is_leaf');
            $table->index('products_count');
            $table->index('name_en');
            $table->index('name_ur');
            $table->index('visual_mapping_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
