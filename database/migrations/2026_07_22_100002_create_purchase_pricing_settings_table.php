<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('update_product_prices_from_purchases')->default(false);
            $table->decimal('wholesale_markup_pct', 6, 2)->default(10);
            $table->decimal('super_wholesale_markup_pct', 6, 2)->default(8);
            $table->decimal('distributor_markup_pct', 6, 2)->default(12);
            $table->json('price_code_wording');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_pricing_settings');
    }
};
