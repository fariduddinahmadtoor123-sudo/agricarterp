<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_enrichment_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('error_code')->nullable()->after('message');
            $table->text('error_reason')->nullable()->after('error_code');
            $table->text('suggested_action')->nullable()->after('error_reason');
            $table->text('raw_response')->nullable()->after('suggested_action');
        });
    }

    public function down(): void
    {
        Schema::table('ai_enrichment_logs', function (Blueprint $table) {
            $table->dropColumn([
                'error_code',
                'error_reason',
                'suggested_action',
                'raw_response',
            ]);
        });
    }
};
