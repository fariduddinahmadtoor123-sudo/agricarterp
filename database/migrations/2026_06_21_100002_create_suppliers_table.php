<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 12)->unique();
            $table->string('supplier_type', 30)->nullable();
            $table->string('country', 100);
            $table->string('city', 100);
            $table->text('full_address');
            $table->string('business_name');
            $table->string('contact_name', 150);
            $table->string('mobile_number', 20);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('opening_balance_type', 10)->default('credit');
            $table->foreignId('ledger_account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->string('urdu_business_name')->nullable();
            $table->string('urdu_contact_name', 150)->nullable();
            $table->string('urdu_city', 100)->nullable();
            $table->string('urdu_account_title', 150)->nullable();
            $table->text('urdu_address')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('business_name');
            $table->index('mobile_number');
            $table->index('country');
            $table->index('city');
            $table->index('supplier_type');
            $table->index('deleted_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
