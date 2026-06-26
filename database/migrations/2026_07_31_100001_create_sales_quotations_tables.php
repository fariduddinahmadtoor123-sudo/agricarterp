<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quotation_number', 32)->unique();
            $table->string('status', 20)->default('draft');
            $table->date('quotation_date');
            $table->string('name_lang', 10)->default('both');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->default('');
            $table->string('customer_mobile', 32)->nullable();
            $table->string('store_key', 64);
            $table->string('store_name')->default('');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('held_label')->nullable();
            $table->string('print_paper_size', 20)->default('80mm');
            $table->boolean('print_controls')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('sales_quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quotation_id')->constrained('sales_quotations')->cascadeOnDelete();
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['quotation_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_lines');
        Schema::dropIfExists('sales_quotations');
    }
};
