<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('frequency');
            $table->string('cron_expression')->nullable();
            $table->unsignedInteger('retention_count')->default(7);
            $table->json('destinations')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type')->default('full');
            $table->string('status')->default('pending');
            $table->string('trigger')->default('manual');
            $table->foreignId('schedule_id')->nullable()->constrained('backup_schedules')->nullOnDelete();
            $table->string('file_name')->nullable();
            $table->string('local_path')->nullable();
            $table->string('google_drive_file_id')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('checksum_sha256')->nullable();
            $table->string('manifest_version')->default('1.0');
            $table->json('modules_included')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->nullable()->constrained('backups')->cascadeOnDelete();
            $table->foreignId('restore_run_id')->nullable();
            $table->string('level')->default('info');
            $table->string('step')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('restore_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('backup_id')->nullable()->constrained('backups')->nullOnDelete();
            $table->string('source_path')->nullable();
            $table->string('mode')->default('replace');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('restore_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restore_run_id')->constrained('restore_runs')->cascadeOnDelete();
            $table->string('database_path');
            $table->string('storage_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_snapshots');
        Schema::dropIfExists('restore_runs');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('backup_schedules');
    }
};
