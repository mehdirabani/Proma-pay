<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('approved_amount_toman')->nullable();
            $table->string('file_disk', 40)->default('payment_receipts');
            $table->string('file_path', 80);
            $table->string('file_mime', 80);
            $table->unsignedInteger('file_size');
            $table->char('file_sha256', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('review_request_id')->nullable()->unique();
            $table->char('review_request_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['installment_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['contract_id', 'created_at']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'payments.receipt.review'],
            ['name' => 'بررسی مدرک انتقال بانکی', 'created_at' => $now, 'updated_at' => $now],
        );
        $permissionId = DB::table('permissions')->where('slug', 'payments.receipt.review')->value('id');
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
        Schema::dropIfExists('payment_receipts');
        DB::table('permissions')->where('slug', 'payments.receipt.review')->delete();
    }
};
