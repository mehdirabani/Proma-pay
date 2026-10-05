<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('amount_toman');
            $table->string('reason', 500);
            $table->json('snapshot');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['contract_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'payments.correct'],
            ['name' => 'ثبت اصلاحیهٔ داخلی پرداخت', 'created_at' => $now, 'updated_at' => $now],
        );
        $permissionId = DB::table('permissions')->where('slug', 'payments.correct')->value('id');
        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($permissionId !== null && $administratorRoleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $administratorRoleId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_corrections');
        DB::table('permissions')->where('slug', 'payments.correct')->delete();
    }
};
