<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('name_en');
            $table->string('name_ur')->default('');
            $table->text('address_en')->nullable();
            $table->text('address_ur')->nullable();
            $table->json('phones')->nullable();
            $table->json('whatsapp_numbers')->nullable();
            $table->json('emails')->nullable();
            $table->string('website_url')->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('strn', 30)->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->string('timezone', 64)->default('Asia/Karachi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
