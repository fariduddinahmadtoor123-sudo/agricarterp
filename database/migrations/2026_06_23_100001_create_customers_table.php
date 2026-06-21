<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 12)->unique();
            $table->string('customer_name');
            $table->string('mobile_number', 20);
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('full_address')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('opening_balance_type', 10)->default('debit');
            $table->foreignId('ledger_account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->string('urdu_customer_name')->nullable();
            $table->string('urdu_city', 100)->nullable();
            $table->text('urdu_address')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_name');
            $table->index('mobile_number');
            $table->index('country');
            $table->index('city');
            $table->index('deleted_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
