<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->decimal('return_total', 14, 2)->default(0)->after('total');
            $table->decimal('refund_total', 14, 2)->default(0)->after('return_total');
            $table->decimal('credit_return_total', 14, 2)->default(0)->after('refund_total');
            $table->decimal('net_total', 14, 2)->default(0)->after('credit_return_total');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn([
                'return_total',
                'refund_total',
                'credit_return_total',
                'net_total',
            ]);
        });
    }
};
