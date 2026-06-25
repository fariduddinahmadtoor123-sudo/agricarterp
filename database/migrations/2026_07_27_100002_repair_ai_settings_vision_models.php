<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ai_settings')) {
            return;
        }

        $invalidModels = [
            'google/gemini-2.0-flash-001',
            'google/gemini-flash-1.5',
        ];

        DB::table('ai_settings')
            ->whereIn('vision_model', $invalidModels)
            ->update(['vision_model' => 'google/gemini-2.5-flash']);
    }

    public function down(): void
    {
        //
    }
};
