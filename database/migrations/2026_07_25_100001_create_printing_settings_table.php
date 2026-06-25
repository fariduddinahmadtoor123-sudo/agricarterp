<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_document_paper', 20)->default('a4');
            $table->string('default_purchase_invoice_paper', 20)->default('a4');
            $table->string('price_tag_label_preset', 20)->default('38x25');
            $table->decimal('price_tag_width_mm', 6, 2)->default(38);
            $table->decimal('price_tag_height_mm', 6, 2)->default(25);
            $table->decimal('price_tag_gap_mm', 6, 2)->default(3);
            $table->string('price_tag_sheet_paper', 20)->default('a4');
            $table->text('barcode_printer_note')->nullable();
            $table->string('pos_receipt_profile', 20)->default('80mm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printing_settings');
    }
};
