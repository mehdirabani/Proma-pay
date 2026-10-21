<?php

namespace Tests\Feature;

use App\Actions\Payments\CaptureManualPayment;
use App\Actions\Payments\CreateSettlementQuote;
use App\Actions\Payments\UpdateFinancialRules;
use App\Models\Contract;
use App\Models\KycCase;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class PaymentReceiptTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_receipt_upload_is_private_pending_and_download_requires_branch_authorization(): void
    {
        Storage::fake('payment_receipts');
        Storage::fake('public');
        [$administrator, $customer, $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);

        $response = $this->upload($contract, $installment->id, '14899ead-0360-43bd-9a82-30fb93d05b09');

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount_toman', '500000')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.file_sha256');
        $receipt = PaymentReceipt::query()->findOrFail($response->json('data.id'));
        $this->assertSame($customer->id, (int) $receipt->customer_id);
        $this->assertSame('payment_receipts', $receipt->file_disk);
        $this->assertMatchesRegularExpression('/\A[a-f0-9-]{36}\.png\z/', $receipt->file_path);
        Storage::disk('payment_receipts')->assertExists($receipt->file_path);
        Storage::disk('public')->assertMissing($receipt->file_path);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_toman' => 0]);

        $download = $this->get('/api/v1/payment-receipts/'.$receipt->id.'/file')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_events', ['event_name' => 'payment_receipt.file_downloaded', 'subject_id' => $receipt->id]);

        $temporaryUrl = $this->getJson('/api/v1/payment-receipts/'.$receipt->id.'/temporary-url')
            ->assertOk()
            ->json('data.url');
        $this->app['auth']->forgetGuards();
        $this->get($temporaryUrl)->assertOk();
        $this->travel(3)->minutes();
        $this->get($temporaryUrl)->assertForbidden();
        $this->travelBack();

        $otherBranch = $this->createBranch('OTHER');
        $otherActor = $this->createActor('operator', $otherBranch);
        $otherActor->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'payments.view')->firstOrFail());
        $otherActor->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'payments.receipt.review')->firstOrFail());
        Sanctum::actingAs($otherActor, ['proma-api']);
        $this->get('/api/v1/payment-receipts/'.$receipt->id.'/file')->assertForbidden();
        $this->postJson('/api/v1/payment-receipts/'.$receipt->id.'/review', [
            'request_id' => (string) Str::uuid(),
            'decision' => 'rejected',
            'review_note' => 'آزمون جداسازی شعبه',
        ])->assertForbidden();
        $this->assertDatabaseHas('payment_receipts', ['id' => $receipt->id, 'status' => 'pending']);
    }

    public function test_approval_recalculates_and_atomically_creates_one_bank_transfer_payment(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, , $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);
        $receiptId = $this->upload($contract, $installment->id, 'd31ab07a-079b-46a1-b168-c9d442f9dba7')->json('data.id');
        $decision = [
            'request_id' => '77a263f7-c6d9-4402-9c59-1548e97e2cbe',
            'decision' => 'approved',
            'review_note' => 'شماره پیگیری با گزارش بانکی تطبیق شد',
            'approved_amount_toman' => '۳۰۰٬۰۰۰',
        ];

        $first = $this->postJson('/api/v1/payment-receipts/'.$receiptId.'/review', $decision)
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount_toman', '300000')
            ->assertJsonMissingPath('data.file_path');
        $replay = $this->postJson('/api/v1/payment-receipts/'.$receiptId.'/review', $decision)->assertOk();

        $receipt = PaymentReceipt::query()->findOrFail($receiptId);
        $payment = Payment::query()->findOrFail($receipt->payment_id);
        $this->assertSame($first->json('data.payment_id'), $replay->json('data.payment_id'));
        $this->assertSame('bank_transfer', $payment->method->value);
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame('300000', (string) $payment->amount_toman);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('payment_allocations', ['payment_id' => $payment->id, 'allocated_amount_toman' => 300000]);
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_toman' => 300000, 'status' => 'partial']);
        $this->assertDatabaseHas('audit_events', ['request_id' => $decision['request_id'], 'event_name' => 'payment_receipt.approved']);
        Storage::disk('payment_receipts')->assertExists($receipt->file_path);
    }

    public function test_rejection_keeps_evidence_without_creating_a_payment_and_is_audited(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, , $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);
        $receiptId = $this->upload($contract, $installment->id, '9f56bf3f-741e-4425-87c1-995212bd051c')->json('data.id');
        $decision = [
            'request_id' => '30b8b63c-c41f-4bc5-9ca4-983bdb151a4d',
            'decision' => 'rejected',
            'review_note' => 'تصویر رسید ناخوانا است',
        ];

        $this->postJson('/api/v1/payment-receipts/'.$receiptId.'/review', $decision)
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.payment_id', null);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseHas('payment_receipts', ['id' => $receiptId, 'status' => 'rejected', 'review_note' => $decision['review_note']]);
        $this->assertDatabaseHas('audit_events', ['request_id' => $decision['request_id'], 'event_name' => 'payment_receipt.rejected']);
        Storage::disk('payment_receipts')->assertExists(PaymentReceipt::query()->findOrFail($receiptId)->file_path);
    }

    public function test_upload_request_id_is_idempotent_and_conflicting_reuse_removes_the_new_file(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, , $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);
        $requestId = '78601ef0-eaf5-4af5-aa14-c86d6d0e4315';

        $first = $this->upload($contract, $installment->id, $requestId);
        $replay = $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => $requestId,
            'amount_toman' => '۵۰۰٬۰۰۰',
            'receipt' => $this->pngReceipt(),
        ])->assertOk();
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertCount(1, Storage::disk('payment_receipts')->allFiles());

        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => $requestId,
            'amount_toman' => '400000',
            'receipt' => $this->pngReceipt(),
        ])->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertCount(1, Storage::disk('payment_receipts')->allFiles());
    }

    public function test_overpayment_and_second_pending_submission_are_rejected_and_orphan_upload_is_removed(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, , $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);

        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => '734de7a8-212e-468f-9302-b7e3be6a7f12',
            'amount_toman' => '500000',
            'receipt' => $this->pngReceipt(),
        ])->assertCreated();
        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => '608d63b4-a8a3-4f93-aa5c-89cb2bcae8fd',
            'amount_toman' => '1000',
            'receipt' => $this->pngReceipt(),
        ])->assertUnprocessable()->assertJsonValidationErrors('receipt');

        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertCount(1, Storage::disk('payment_receipts')->allFiles());
        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => 'ff6c4f4a-b3b1-4ad3-b837-c7197fac01ef',
            'amount_toman' => '500001',
            'receipt' => $this->pngReceipt(),
        ])->assertUnprocessable()->assertJsonValidationErrors('amount_toman');

        $freshContract = $this->createActiveContract();
        $secondContract = $freshContract[2];
        $secondInstallment = $secondContract->installments()->firstOrFail();
        $this->postJson('/api/v1/contracts/'.$secondContract->id.'/installments/'.$secondInstallment->id.'/payment-receipts', [
            'request_id' => 'e9854cdb-5616-45fb-8382-2d1a810c3c68',
            'amount_toman' => '500001',
            'receipt' => $this->pngReceipt(),
        ])->assertUnprocessable()->assertJsonValidationErrors('amount_toman');
        $this->assertCount(1, Storage::disk('payment_receipts')->allFiles());
        $this->assertDatabaseCount('payment_receipts', 1);
    }

    public function test_receipt_file_validation_and_rtl_staff_pages_are_explicit(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, $customer, $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);
        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => (string) Str::uuid(),
            'amount_toman' => '500000',
            'receipt' => UploadedFile::fake()->create('receipt.php', 10, 'application/x-php'),
        ])->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installment->id.'/payment-receipts', [
            'request_id' => (string) Str::uuid(),
            'amount_toman' => '500000',
            'receipt' => UploadedFile::fake()->create('oversized.png', 5121, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->assertCount(0, Storage::disk('payment_receipts')->allFiles());

        $this->actingAs($administrator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('ثبت مدرک انتقال بانکی مشتری')
            ->assertSee('فایل خصوصی نگهداری')
            ->assertSee('dir="rtl"', false);
        $this->get('/payment-receipts')
            ->assertOk()
            ->assertSee('مدارک انتقال بانکی')
            ->assertSee('dir="rtl"', false);
    }

    public function test_review_cannot_capture_a_receipt_against_a_stale_balance(): void
    {
        Storage::fake('payment_receipts');
        [$administrator, , $contract] = $this->createActiveContract();
        $installment = $contract->installments()->firstOrFail();
        Sanctum::actingAs($administrator, ['proma-api']);
        $receiptId = $this->upload($contract, $installment->id, 'f45b3b03-8911-43af-8d1c-14419c6c5295')->json('data.id');

        $quote = app(CreateSettlementQuote::class)->handle($contract, [
            'request_id' => (string) Str::uuid(),
            'amount_toman' => '500000',
            'scope' => 'selected',
            'installment_ids' => [$installment->id],
        ], $administrator);
        app(CaptureManualPayment::class)->handle($quote, [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
        ], $administrator);

        $this->postJson('/api/v1/payment-receipts/'.$receiptId.'/review', [
            'request_id' => (string) Str::uuid(),
            'decision' => 'approved',
            'review_note' => 'رسید بررسی شد ولی مانده تغییر کرده',
        ])->assertUnprocessable()->assertJsonValidationErrors('installment_ids');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('payment_receipts', ['id' => $receiptId, 'status' => 'pending', 'payment_id' => null]);
    }

    private function upload(Contract $contract, int $installmentId, string $requestId)
    {
        return $this->postJson('/api/v1/contracts/'.$contract->id.'/installments/'.$installmentId.'/payment-receipts', [
            'request_id' => $requestId,
            'amount_toman' => '۵۰۰٬۰۰۰',
            'receipt' => $this->pngReceipt(),
        ])->assertCreated();
    }

    private function pngReceipt(): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nm6sAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent('bank-transfer.png', (string) $content);
    }

    private function createActiveContract(): array
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator', $branch);
        $finance = $this->createActor('finance', $branch);
        $customer = $this->createCustomer($branch, '09'.random_int(100_000_000, 999_999_999));
        $customer->creditAccount()->update(['limit_toman' => 1_000_000]);
        KycCase::create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'identity_provider' => 'manual',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $administrator->id,
        ]);

        Sanctum::actingAs($administrator, ['proma-api']);
        $contractId = $this->postJson('/api/v1/contracts', [
            'customer_id' => $customer->id,
            'principal_toman' => '1000000',
            'down_payment_toman' => '0',
            'monthly_interest_rate' => '0',
            'interest_type' => 'simple',
            'installment_count' => '2',
            'start_date' => now()->toDateString(),
            'first_due_date' => now()->addMonthNoOverflow()->toDateString(),
            'request_uuid' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        $contract = Contract::query()->findOrFail($contractId);
        $this->postJson('/api/v1/contracts/'.$contractId.'/submit', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'آزمون ثبت مدرک بانکی',
        ])->assertOk();
        Sanctum::actingAs($finance, ['proma-api']);
        $this->putJson('/api/v1/contracts/'.$contractId.'/approve', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'تأیید دو مرحله‌ای برای آزمون',
        ])->assertOk();
        Sanctum::actingAs($administrator, ['proma-api']);
        $this->postJson('/api/v1/contracts/'.$contractId.'/activate', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'فعال‌سازی قرارداد برای آزمون',
        ])->assertOk();
        app(UpdateFinancialRules::class)->handle([
            'request_id' => (string) Str::uuid(),
            'monthly_penalty_rate' => '0',
            'legal_monthly_penalty_rate' => '0',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => '0',
        ], $administrator);

        return [$administrator, $customer, $contract->fresh()];
    }
}
