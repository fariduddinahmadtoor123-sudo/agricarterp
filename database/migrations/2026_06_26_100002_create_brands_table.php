<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('brand_number', 12)->unique();
            $table->string('name_en');
            $table->string('name_ur');
            $table->text('short_note');
            $table->string('logo_path', 500)->nullable();
            $table->text('short_description_en')->nullable();
            $table->text('short_description_ur')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ur')->nullable();
            $table->text('brand_overview_en')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('website', 500)->nullable();
            $table->string('ai_status', 20)->default('pending');
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('ai_version', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('categories_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('name_en');
            $table->index('name_ur');
            $table->index('categories_count');
            $table->index('ai_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
