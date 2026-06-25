<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'sequence_date']);
        });

        Schema::create('purchasers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile', 30)->nullable();
            $table->string('code', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });

        Schema::create('purchase_planning_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sheet_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('title')->default('');
            $table->date('sheet_date');
            $table->string('name_lang', 10)->default('both');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('sheet_date');
        });

        Schema::create('purchase_planning_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('sheet_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('sheet_id', 'pp_sheet_lines_sheet_fk')
                ->references('id')
                ->on('purchase_planning_sheets')
                ->cascadeOnDelete();
            $table->unique(['sheet_id', 'line_id'], 'pp_sheet_lines_sheet_line_unique');
            $table->index(['sheet_id', 'sort_order']);
        });

        Schema::create('purchase_quotation_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quotation_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('title')->default('');
            $table->date('sheet_date');
            $table->string('name_lang', 10)->default('both');
            $table->text('notes')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->default('');
            $table->string('store_key', 50)->default('main');
            $table->string('store_name')->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('store_key');
        });

        Schema::create('purchase_quotation_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('sheet_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('sheet_id', 'pq_sheet_lines_sheet_fk')
                ->references('id')
                ->on('purchase_quotation_sheets')
                ->cascadeOnDelete();
            $table->unique(['sheet_id', 'line_id'], 'pq_sheet_lines_sheet_line_unique');
            $table->index(['sheet_id', 'sort_order']);
        });

        Schema::create('purchase_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('purchase_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('title')->default('');
            $table->date('sheet_date');
            $table->string('name_lang', 10)->default('both');
            $table->text('notes')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->default('');
            $table->string('store_key', 50)->default('main');
            $table->string('store_name')->default('');
            $table->string('invoice_payment_status', 20)->default('unpaid');
            $table->string('goods_receipt_status', 20)->default('pending');
            $table->string('dispute_status', 20)->default('none');
            $table->text('dispute_notes')->nullable();
            $table->string('invoice_image_path', 500)->nullable();
            $table->uuid('linked_planning_id')->nullable();
            $table->string('linked_planning_number', 30)->default('');
            $table->uuid('linked_quotation_id')->nullable();
            $table->string('linked_quotation_number', 30)->default('');
            $table->string('payment_amount', 30)->default('');
            $table->text('payment_notes')->nullable();
            $table->string('print_paper_size', 20)->default('a4');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('store_key');
            $table->index('goods_receipt_status');
        });

        Schema::create('purchase_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('sheet_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('sheet_id', 'pu_sheet_lines_sheet_fk')
                ->references('id')
                ->on('purchase_sheets')
                ->cascadeOnDelete();
            $table->unique(['sheet_id', 'line_id'], 'pu_sheet_lines_sheet_line_unique');
            $table->index(['sheet_id', 'sort_order']);
        });

        Schema::create('purchase_payment_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sheet_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('title')->default('');
            $table->date('sheet_date');
            $table->foreignId('purchaser_id')->nullable()->constrained('purchasers')->nullOnDelete();
            $table->string('purchaser_name')->default('');
            $table->text('notes')->nullable();
            $table->json('payment_sources')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('purchaser_id');
        });

        Schema::create('purchase_payment_sheet_vendor_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('sheet_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->foreign('sheet_id', 'pps_vendor_lines_sheet_fk')
                ->references('id')
                ->on('purchase_payment_sheets')
                ->cascadeOnDelete();
            $table->index(['sheet_id', 'sort_order']);
        });

        Schema::create('reorder_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 30)->unique();
            $table->foreignId('purchaser_id')->nullable()->constrained('purchasers')->nullOnDelete();
            $table->string('purchaser_name')->default('');
            $table->string('name_lang', 10)->default('both');
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('purchaser_id');
            $table->index('sent_at');
        });

        Schema::create('reorder_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('order_id', 'ro_order_lines_order_fk')
                ->references('id')
                ->on('reorder_orders')
                ->cascadeOnDelete();
            $table->unique(['order_id', 'line_id'], 'ro_order_lines_order_line_unique');
            $table->index(['order_id', 'sort_order']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('store_key', 50);
            $table->decimal('on_hand', 16, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'store_key']);
            $table->index('store_key');
        });

        Schema::create('product_store_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('store_key', 50);
            $table->decimal('average_cost', 16, 4)->default(0);
            $table->decimal('last_purchase_cost', 16, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'store_key']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('store_key', 50);
            $table->string('movement_type', 40);
            $table->decimal('quantity', 16, 4);
            $table->decimal('balance_after', 16, 4)->default(0);
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'store_key']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_type');
            $table->index('created_at');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('adjustment_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('store_key', 50)->default('main');
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('store_key');
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('adjustment_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_delta', 16, 4);
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('adjustment_id', 'sa_lines_adjustment_fk')
                ->references('id')
                ->on('stock_adjustments')
                ->cascadeOnDelete();
            $table->index(['adjustment_id', 'sort_order']);
        });

        Schema::create('opening_stock_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number', 30)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('store_key', 50)->default('main');
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('store_key');
        });

        Schema::create('opening_stock_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('entry_id');
            $table->uuid('line_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('entry_id', 'os_entry_lines_entry_fk')
                ->references('id')
                ->on('opening_stock_entries')
                ->cascadeOnDelete();
            $table->index(['entry_id', 'sort_order']);
        });

        Schema::create('purchase_rate_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('store_key', 50);
            $table->decimal('previous_average_cost', 16, 4)->default(0);
            $table->decimal('new_average_cost', 16, 4)->default(0);
            $table->decimal('previous_last_purchase_cost', 16, 4)->default(0);
            $table->decimal('new_last_purchase_cost', 16, 4)->default(0);
            $table->decimal('purchase_rate', 16, 4)->default(0);
            $table->decimal('received_quantity', 16, 4)->default(0);
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'store_key']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rate_audits');
        Schema::dropIfExists('opening_stock_entry_lines');
        Schema::dropIfExists('opening_stock_entries');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('product_store_costs');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('reorder_order_lines');
        Schema::dropIfExists('reorder_orders');
        Schema::dropIfExists('purchase_payment_sheet_vendor_lines');
        Schema::dropIfExists('purchase_payment_sheets');
        Schema::dropIfExists('purchase_sheet_lines');
        Schema::dropIfExists('purchase_sheets');
        Schema::dropIfExists('purchase_quotation_sheet_lines');
        Schema::dropIfExists('purchase_quotation_sheets');
        Schema::dropIfExists('purchase_planning_sheet_lines');
        Schema::dropIfExists('purchase_planning_sheets');
        Schema::dropIfExists('purchasers');
        Schema::dropIfExists('document_number_sequences');
    }
};
