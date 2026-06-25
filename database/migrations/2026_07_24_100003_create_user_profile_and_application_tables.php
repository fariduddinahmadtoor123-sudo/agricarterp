<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone_number', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_title');
            $table->string('iban_account_number', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('profile_photo_path')->nullable();
            $table->string('card_front_path')->nullable();
            $table->string('card_back_path')->nullable();
            $table->timestamps();
        });

        Schema::create('user_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 16)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->text('full_address')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('assigned_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('email');
            $table->index('created_at');
        });

        Schema::create('user_application_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_application_id')->constrained('user_applications')->cascadeOnDelete();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone_number', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_application_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_application_id')->constrained('user_applications')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_title');
            $table->string('iban_account_number', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_application_id')->unique()->constrained('user_applications')->cascadeOnDelete();
            $table->string('profile_photo_path')->nullable();
            $table->string('card_front_path')->nullable();
            $table->string('card_back_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_application_documents');
        Schema::dropIfExists('user_application_bank_accounts');
        Schema::dropIfExists('user_application_phones');
        Schema::dropIfExists('user_applications');
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('user_bank_accounts');
        Schema::dropIfExists('user_phones');
    }
};
