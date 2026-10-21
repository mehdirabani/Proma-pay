<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
        });

        Schema::table('credit_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('request_hash', 64)->nullable();
            $table->bigInteger('used_delta_toman')->default(0);
            $table->bigInteger('reserved_delta_toman')->default(0);
            $table->index(['credit_account_id', 'contract_id', 'created_at'], 'credit_ledger_contract_balance_index');
        });

        Schema::create('contract_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->string('reason', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['contract_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_status_history');

        Schema::table('credit_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('credit_ledger_contract_balance_index');
            $table->dropForeign(['contract_id']);
            $table->dropColumn(['contract_id', 'request_hash', 'used_delta_toman', 'reserved_delta_toman']);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'activated_at']);
        });
    }
};
