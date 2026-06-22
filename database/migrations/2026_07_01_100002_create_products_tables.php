<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_number', 12)->unique();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->foreignId('brand_id')
                ->constrained('brands')
                ->restrictOnDelete();
            $table->foreignId('base_unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->foreignId('packing_unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('packing_value', 12, 4);
            $table->string('name_en', 500);
            $table->string('name_ur', 500)->default('');
            $table->decimal('required_quantity', 12, 4)->default(0);
            $table->decimal('alert_quantity', 12, 4)->default(0);
            $table->string('status', 20)->default('active');
            $table->text('short_description_en')->nullable();
            $table->text('short_description_ur')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ur')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('seo_focus_keyword', 255)->nullable();
            $table->json('search_terms')->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->text('usage_en')->nullable();
            $table->text('usage_ur')->nullable();
            $table->string('ai_status', 20)->default('pending');
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('ai_version', 50)->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('brand_id');
            $table->index('base_unit_id');
            $table->index('packing_unit_id');
            $table->index('status');
            $table->index('name_en');
            $table->index('ai_status');
            $table->index(['brand_id', 'name_en']);
            $table->index('created_at');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_main']);
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->restrictOnDelete();
            $table->string('value', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
            $table->index('attribute_id');
        });

        Schema::create('product_category_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('product_product_control_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('control_group_id')
                ->constrained('product_control_groups')
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'control_group_id']);
        });

        Schema::create('product_product_control', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('control_id')
                ->constrained('product_controls')
                ->restrictOnDelete();
            $table->string('assignment_source', 20)->default('individual');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'control_id']);
            $table->index('control_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_control');
        Schema::dropIfExists('product_product_control_group');
        Schema::dropIfExists('product_category_tags');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
