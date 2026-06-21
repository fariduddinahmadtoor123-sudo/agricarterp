<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_mobile_numbers', function (Blueprint $table) {
            $table->dropUnique(['mobile_normalized']);
            $table->unique(
                ['mobile_normalized', 'contactable_type'],
                'contact_mobile_numbers_normalized_type_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('contact_mobile_numbers', function (Blueprint $table) {
            $table->dropUnique('contact_mobile_numbers_normalized_type_unique');
            $table->unique('mobile_normalized');
        });
    }
};
