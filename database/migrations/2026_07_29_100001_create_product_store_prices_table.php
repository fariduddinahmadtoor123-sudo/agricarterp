<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_store_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('store_key', 50);
            $table->decimal('purchase_rate', 16, 4)->default(0);
            $table->decimal('landing_cost', 16, 4)->nullable();
            $table->decimal('sale_rate', 16, 4)->nullable();
            $table->decimal('wholesale_rate', 16, 4)->nullable();
            $table->decimal('super_wholesale_rate', 16, 4)->nullable();
            $table->decimal('distributor_rate', 16, 4)->nullable();
            $table->uuid('source_purchase_sheet_id')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'store_key']);
            $table->index('store_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_prices');
    }
};
