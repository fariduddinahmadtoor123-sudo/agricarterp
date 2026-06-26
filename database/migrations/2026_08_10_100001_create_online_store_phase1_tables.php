<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title_en');
            $table->string('title_ur');
            $table->string('slug', 120)->unique();
            $table->longText('content_en')->nullable();
            $table->longText('content_ur')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'slug']);
        });

        Schema::create('store_front_settings', function (Blueprint $table) {
            $table->id();
            $table->string('top_bar_left')->default('یا حی');
            $table->string('top_bar_center')->default('بسم اللہ الرحمن الرحیم');
            $table->string('top_bar_right')->default('یا قیوم');
            $table->text('ticker_en')->nullable();
            $table->text('ticker_ur')->nullable();
            $table->unsignedTinyInteger('homepage_categories_per_row')->default(5);
            $table->json('social_links')->nullable();
            $table->json('header_navigation')->nullable();
            $table->string('footer_logo_path')->nullable();
            $table->boolean('footer_logo_removed')->default(false);
            $table->text('footer_about_en')->nullable();
            $table->text('footer_about_ur')->nullable();
            $table->json('footer_quick_links')->nullable();
            $table->json('footer_legal_links')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->text('map_embed_url')->nullable();
            $table->string('copyright_line')->nullable();
            $table->timestamps();
        });

        Schema::create('store_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_contact_messages');
        Schema::dropIfExists('store_front_settings');
        Schema::dropIfExists('store_pages');
    }
};
