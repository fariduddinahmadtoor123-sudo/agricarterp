<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->unique()->after('name_ur');
            $table->string('seo_focus_keyword', 255)->nullable()->after('seo_keywords');
            $table->json('search_terms')->nullable()->after('seo_focus_keyword');
            $table->json('faqs_en')->nullable()->after('import_export_notes_ur');
            $table->json('faqs_ur')->nullable()->after('faqs_en');
            $table->text('buying_guide_en')->nullable()->after('faqs_ur');
            $table->text('buying_guide_ur')->nullable()->after('buying_guide_en');
            $table->text('common_applications_en')->nullable()->after('buying_guide_ur');
            $table->text('common_applications_ur')->nullable()->after('common_applications_en');
            $table->string('ai_status', 20)->default('pending')->after('common_applications_ur');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_status');
            $table->string('ai_version', 50)->nullable()->after('ai_generated_at');
            $table->text('customs_notes_en')->nullable()->after('ai_version');
            $table->text('customs_notes_ur')->nullable()->after('customs_notes_en');
            $table->text('import_notes_en')->nullable()->after('customs_notes_ur');
            $table->text('import_notes_ur')->nullable()->after('import_notes_en');
            $table->text('export_notes_en')->nullable()->after('import_notes_ur');
            $table->text('export_notes_ur')->nullable()->after('export_notes_en');

            $table->index('slug');
            $table->index('ai_status');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['ai_status']);

            $table->dropColumn([
                'slug',
                'seo_focus_keyword',
                'search_terms',
                'faqs_en',
                'faqs_ur',
                'buying_guide_en',
                'buying_guide_ur',
                'common_applications_en',
                'common_applications_ur',
                'ai_status',
                'ai_generated_at',
                'ai_version',
                'customs_notes_en',
                'customs_notes_ur',
                'import_notes_en',
                'import_notes_ur',
                'export_notes_en',
                'export_notes_ur',
            ]);
        });
    }
};
