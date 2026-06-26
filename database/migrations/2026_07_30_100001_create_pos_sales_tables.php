<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sale_number', 32)->unique();
            $table->string('status', 20)->default('draft');
            $table->date('sale_date');
            $table->string('name_lang', 10)->default('both');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->default('');
            $table->string('customer_mobile', 32)->nullable();
            $table->string('store_key', 64);
            $table->string('store_name')->default('');
            $table->string('payment_method', 32)->default('cash');
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('held_label')->nullable();
            $table->boolean('stock_applied')->default(false);
            $table->string('print_paper_size', 20)->default('80mm');
            $table->boolean('print_controls')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('pos_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['sale_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_lines');
        Schema::dropIfExists('pos_sales');
    }
};
