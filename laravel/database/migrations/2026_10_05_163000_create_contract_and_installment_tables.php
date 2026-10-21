<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contract_number', 80)->unique();
            $table->char('currency_code', 3)->default('TMN');
            $table->unsignedBigInteger('total_amount_toman');
            $table->unsignedBigInteger('down_payment_toman')->default(0);
            $table->unsignedBigInteger('principal_toman');
            $table->unsignedSmallInteger('installment_count');
            $table->date('start_date');
            $table->date('first_due_date');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'created_at']);
        });

        Schema::create('installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('paid_toman')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
            $table->unique(['contract_id', 'installment_number']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('contracts');
    }
};
