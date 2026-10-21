<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_rules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('monthly_penalty_rate', 12)->nullable();
            $table->string('legal_monthly_penalty_rate', 12)->nullable();
            $table->string('monthly_reward_rate', 12)->nullable();
            $table->unsignedSmallInteger('late_penalty_grace_days')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('financial_rules')->insert([
            'id' => 1,
            'monthly_penalty_rate' => null,
            'legal_monthly_penalty_rate' => null,
            'monthly_reward_rate' => null,
            'late_penalty_grace_days' => null,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('financial_rule_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->unsignedInteger('rule_version');
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['rule_version', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_rule_history');
        Schema::dropIfExists('financial_rules');
    }
};
