<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name', 190);
            $table->string('mobile', 30)->unique();
            $table->string('email', 190)->nullable()->index();
            $table->text('national_id_encrypted')->nullable();
            $table->char('national_id_hash', 64)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('kyc_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('identity_provider', 80)->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_cases');
        Schema::dropIfExists('customers');
    }
};
