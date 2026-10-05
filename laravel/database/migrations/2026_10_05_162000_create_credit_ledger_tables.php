<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency_code', 3)->default('TMN');
            $table->unsignedBigInteger('limit_toman')->default(0);
            $table->unsignedBigInteger('used_toman')->default(0);
            $table->unsignedBigInteger('reserved_toman')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('entry_type', 40);
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('old_limit_toman');
            $table->unsignedBigInteger('new_limit_toman');
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['credit_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger_entries');
        Schema::dropIfExists('credit_accounts');
    }
};
