<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        DB::table('brand_number_sequences')->insert([
            'id' => 1,
            'last_number' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_number_sequences');
    }
};
