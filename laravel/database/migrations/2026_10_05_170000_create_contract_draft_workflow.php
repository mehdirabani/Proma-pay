<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('prefix', 20)->default('PR');
            $table->unsignedBigInteger('serial')->nullable();
            $table->string('monthly_interest_rate', 12)->default('0');
            $table->string('interest_type', 10)->default('simple');
            $table->string('calculation_version', 80)->default('legacy-contract-finance-v1');
            $table->char('pricing_snapshot_hash', 64)->nullable();
            $table->uuid('request_uuid')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique('request_uuid', 'contracts_request_uuid_unique');
            $table->unique(['prefix', 'serial'], 'contracts_prefix_serial_unique');
        });

        Schema::create('domain_sequences', function (Blueprint $table): void {
            $table->string('sequence_name', 80)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        DB::table('domain_sequences')->insert([
            'sequence_name' => 'contracts',
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_sequences');

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropUnique('contracts_request_uuid_unique');
            $table->dropUnique('contracts_prefix_serial_unique');
            $table->dropColumn([
                'prefix', 'serial', 'monthly_interest_rate', 'interest_type',
                'calculation_version', 'pricing_snapshot_hash', 'request_uuid', 'request_hash',
            ]);
        });
    }
};
