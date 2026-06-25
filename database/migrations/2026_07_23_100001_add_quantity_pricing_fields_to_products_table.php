<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_from_qty', 12, 4)->default(0)->after('alert_quantity');
            $table->decimal('super_wholesale_from_qty', 12, 4)->default(0)->after('wholesale_from_qty');
            $table->decimal('distributor_from_qty', 12, 4)->default(0)->after('super_wholesale_from_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'wholesale_from_qty',
                'super_wholesale_from_qty',
                'distributor_from_qty',
            ]);
        });
    }
};
