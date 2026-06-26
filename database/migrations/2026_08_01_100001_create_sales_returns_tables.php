<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number', 32)->unique();
            $table->string('status', 20)->default('draft');
            $table->date('return_date');
            $table->foreignUuid('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->string('sale_number', 32)->default('');
            $table->string('name_lang', 10)->default('both');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->default('');
            $table->string('customer_mobile', 32)->nullable();
            $table->string('store_key', 64);
            $table->string('store_name')->default('');
            $table->string('original_payment_method', 32)->nullable();
            $table->string('refund_method', 32)->default('cash');
            $table->string('refund_status', 20)->default('pending');
            $table->decimal('return_subtotal', 14, 2)->default(0);
            $table->decimal('return_total', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('refund_notes')->nullable();
            $table->boolean('stock_applied')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index('pos_sale_id');
        });

        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->uuid('line_id');
            $table->uuid('source_sale_line_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['return_id', 'line_id']);
        });

        Schema::create('customer_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('entry_type', 40);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 40)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('description')->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_entries');
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
    }
};
