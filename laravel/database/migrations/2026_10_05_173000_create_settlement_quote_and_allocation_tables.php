<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('quote_uuid')->unique();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('scope', 20);
            $table->json('requested_installment_ids');
            $table->json('installment_ids');
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('full_settlement_toman');
            $table->date('calculation_date');
            $table->string('calculation_version', 120);
            $table->unsignedInteger('financial_rules_version');
            $table->json('financial_rules_snapshot');
            $table->json('plan_snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_id', 'expires_at']);
            $table->index(['contract_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->char('request_hash', 64)->nullable();
            $table->foreignId('settlement_quote_id')->nullable()->unique()->after('contract_id')
                ->constrained('settlement_quotes')->restrictOnDelete();
            $table->date('payment_date')->nullable();
            $table->time('payment_time')->nullable();
            $table->string('payment_type', 30)->nullable();
            $table->string('calculation_version', 160)->nullable();
            $table->unsignedInteger('financial_rules_version')->nullable();
            $table->index(['contract_id', 'status', 'payment_date'], 'payments_settlement_replay_index');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_quote_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('allocated_amount_toman');
            $table->unsignedBigInteger('principal_applied_toman');
            $table->unsignedBigInteger('normal_penalty_applied_toman');
            $table->unsignedBigInteger('legal_penalty_applied_toman');
            $table->unsignedBigInteger('reward_applied_toman');
            $table->unsignedBigInteger('principal_before_toman');
            $table->unsignedBigInteger('principal_after_toman');
            $table->unsignedBigInteger('normal_penalty_before_toman');
            $table->unsignedBigInteger('normal_penalty_after_toman');
            $table->unsignedBigInteger('legal_penalty_before_toman');
            $table->unsignedBigInteger('legal_penalty_after_toman');
            $table->string('status_after', 30);
            $table->json('calculation_snapshot');
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('payment_allocations')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payment_id', 'installment_id', 'is_reversal'], 'payment_installment_capture_unique');
            $table->index(['installment_id', 'is_reversal', 'created_at'], 'allocation_installment_replay_index');
            $table->index(['contract_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['contract_id']);
            $table->dropIndex('payments_settlement_replay_index');
            $table->dropForeign(['settlement_quote_id']);
            $table->dropUnique(['settlement_quote_id']);
            $table->dropColumn([
                'request_hash', 'settlement_quote_id', 'payment_date', 'payment_time', 'payment_type',
                'calculation_version', 'financial_rules_version',
            ]);
            $table->foreign('contract_id')->references('id')->on('contracts')->restrictOnDelete();
        });

        Schema::dropIfExists('settlement_quotes');
    }
};
