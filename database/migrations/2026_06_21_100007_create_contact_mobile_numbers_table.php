<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_mobile_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_normalized', 20)->unique();
            $table->string('contactable_type', 50);
            $table->unsignedBigInteger('contactable_id');
            $table->string('category', 30);
            $table->unsignedBigInteger('contact_person_id')->nullable();
            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_mobile_numbers');
    }
};
