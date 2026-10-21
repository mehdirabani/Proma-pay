<?php

namespace Tests\Feature;

use App\Actions\Payments\CaptureManualPayment;
use App\Actions\Payments\CorrectCapturedPayment;
use App\Actions\Payments\CreateSettlementQuote;
use App\Actions\Payments\UpdateFinancialRules;
use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\KycCase;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class PaymentCorrectionTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_admin_can_append_an_internal_correction_and_replay_the_balance(): void
    {
        [$administrator, $customer, $contract, $installment, $payment] = $this->createCapturedPayment();
        Sanctum::actingAs($administrator, ['proma-api']);
        $input = ['request_id' => (string) Str::uuid(), 'reason' => 'ثبت اشتباه دریافت'];

        $first = $this->postJson('/api/v1/payments/'.$payment->id.'/correction', $input)
            ->assertCreated()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.amount_toman', '100000')
            ->assertJsonPath('data.bank_refund_performed', false);
        $replay = $this->postJson('/api/v1/payments/'.$payment->id.'/correction', $input)->assertOk();
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->postJson('/api/v1/payments/'.$payment->id.'/correction', [...$input, 'reason' => 'دلیل متفاوت'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_id');

        $this->assertSame(PaymentStatus::Corrected, $payment->fresh()->status);
        $this->assertDatabaseCount('payment_corrections', 1);
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'installment_id' => $installment->id,
            'allocated_amount_toman' => 100000,
            'is_reversal' => true,
            'reversal_of_id' => PaymentAllocation::query()->where('payment_id', $payment->id)->where('is_reversal', false)->value('id'),
        ]);
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_toman' => 0, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_events', ['request_id' => $input['request_id'], 'event_name' => 'payment.corrected']);

        $newQuote = app(CreateSettlementQuote::class)->handle($contract, [
            'request_id' => (string) Str::uuid(),
            'scope' => 'selected',
            'installment_ids' => [$installment->id],
            'amount_toman' => '100000',
        ], $administrator);
        $this->assertSame('100000', (string) $newQuote->full_settlement_toman);
        $this->assertSame($customer->id, (int) $newQuote->customer_id);
    }

    public function test_pending_and_down_payment_records_cannot_use_installment_correction(): void
    {
        [$administrator, , $contract, , $payment] = $this->createCapturedPayment();
        Sanctum::actingAs($administrator, ['proma-api']);
        $pending = Payment::create([
            'customer_id' => $payment->customer_id,
            'contract_id' => $contract->id,
            'created_by' => $administrator->id,
            'idempotency_key' => (string) Str::uuid(),
            'currency_code' => 'TMN',
            'amount_toman' => '100000',
            'method' => 'cash',
            'status' => PaymentStatus::Pending,
            'requested_at' => now(),
            'payment_type' => 'installment',
        ]);
        $downPayment = Payment::create([
            'customer_id' => $payment->customer_id,
            'contract_id' => $contract->id,
            'created_by' => $administrator->id,
            'idempotency_key' => (string) Str::uuid(),
            'currency_code' => 'TMN',
            'amount_toman' => '100000',
            'method' => 'cash',
            'status' => PaymentStatus::Paid,
            'requested_at' => now(),
            'completed_at' => now(),
            'payment_date' => now()->toDateString(),
            'payment_type' => 'down_payment',
        ]);

        $this->postJson('/api/v1/payments/'.$pending->id.'/correction', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'پرداخت هنوز نهایی نیست',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->postJson('/api/v1/payments/'.$downPayment->id.'/correction', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'پیش‌پرداخت قرارداد',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertDatabaseCount('payment_corrections', 0);
        $this->assertSame(PaymentStatus::Pending, $pending->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $downPayment->fresh()->status);
    }

    public function test_correction_is_branch_scoped_and_persian_web_control_is_rtl(): void
    {
        [$administrator, $customer, , , $payment] = $this->createCapturedPayment();
        $this->actingAs($administrator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('ثبت اصلاحیهٔ داخلی')
            ->assertSee('برگشت وجه بانکی انجام نمی‌دهد')
            ->assertSee('dir="rtl"', false);

        $finance = $this->createActor('finance', $customer->branch);
        $this->actingAs($finance)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertDontSee('ثبت اصلاحیهٔ داخلی');
        Sanctum::actingAs($finance, ['proma-api']);
        $this->postJson('/api/v1/payments/'.$payment->id.'/correction', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'تلاش کاربر مالی',
        ])->assertForbidden();

        $otherBranch = $this->createBranch('OTHER');
        $operator = $this->createActor('operator', $otherBranch);
        $operator->roles()->firstOrFail()->permissions()->syncWithoutDetaching([
            Permission::query()->where('slug', 'payments.correct')->firstOrFail()->id,
            Permission::query()->where('slug', 'customers.view')->firstOrFail()->id,
        ]);
        Sanctum::actingAs($operator, ['proma-api']);
        $this->postJson('/api/v1/payments/'.$payment->id.'/correction', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'تلاش شعبهٔ دیگر',
        ])->assertForbidden();
        $this->assertDatabaseCount('payment_corrections', 0);
    }

    public function test_correction_reverses_all_installments_in_a_multi_installment_capture(): void
    {
        [$administrator, , $contract, , $payment] = $this->createCapturedPayment(2);
        $installments = $contract->installments()->orderBy('installment_number')->get();
        $this->assertSame(2, PaymentAllocation::query()->where('payment_id', $payment->id)->where('is_reversal', false)->count());
        Sanctum::actingAs($administrator, ['proma-api']);

        $this->postJson('/api/v1/payments/'.$payment->id.'/correction', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'اصلاح دریافت دو قسط',
        ])->assertCreated()->assertJsonPath('data.amount_toman', '200000');

        $this->assertSame(4, PaymentAllocation::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(2, PaymentAllocation::query()->where('payment_id', $payment->id)->where('is_reversal', true)->count());
        $this->assertDatabaseHas('payment_corrections', ['payment_id' => $payment->id, 'installment_id' => null]);
        foreach ($installments as $installment) {
            $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_toman' => 0, 'status' => 'pending']);
            $this->assertSame(1, PaymentAllocation::query()->where('payment_id', $payment->id)->where('installment_id', $installment->id)->where('is_reversal', true)->count());
        }
    }

    public function test_web_correction_records_a_reason_and_warns_that_no_bank_refund_was_sent(): void
    {
        [$administrator, $customer, , , $payment] = $this->createCapturedPayment();
        $this->actingAs($administrator)->post(route('payments.correction.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => 'اشتباه در ثبت کارت‌خوان',
        ])->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('success', 'اصلاحیهٔ داخلی پرداخت ثبت شد؛ برگشت وجه بانکی انجام نشده است.');
        $this->assertDatabaseHas('payment_corrections', [
            'payment_id' => $payment->id,
            'reason' => 'اشتباه در ثبت کارت‌خوان',
        ]);
    }

    public function test_payment_correction_history_rejects_updates_and_deletes(): void
    {
        [$administrator, , , , $payment] = $this->createCapturedPayment();
        $correction = app(CorrectCapturedPayment::class)->handle($payment, [
            'request_id' => (string) Str::uuid(),
            'reason' => 'خطای ثبت آزمایشی',
        ], $administrator);

        try {
            $correction->update(['reason' => 'تغییر غیرمجاز']);
            $this->fail('An immutable payment correction accepted an update.');
        } catch (LogicException $exception) {
            $this->assertSame('Payment corrections are immutable.', $exception->getMessage());
        }

        try {
            $correction->delete();
            $this->fail('An immutable payment correction accepted a delete.');
        } catch (LogicException $exception) {
            $this->assertSame('Payment corrections are immutable.', $exception->getMessage());
        }
    }

    private function createCapturedPayment(int $installmentCount = 1): array
    {
        $branch = $this->createBranch('CORRECTION');
        $administrator = $this->createActor('administrator', $branch);
        $customer = $this->createCustomer($branch);
        $customer->creditAccount()->update(['limit_toman' => 100000 * $installmentCount]);
        KycCase::create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'identity_provider' => 'manual',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $administrator->id,
        ]);
        app(UpdateFinancialRules::class)->handle([
            'request_id' => (string) Str::uuid(),
            'monthly_penalty_rate' => '0',
            'legal_monthly_penalty_rate' => '0',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => 0,
        ], $administrator);
        $contract = Contract::create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'created_by' => $administrator->id,
            'contract_number' => 'CORR-'.Str::uuid(),
            'prefix' => 'CORR',
            'serial' => 1,
            'currency_code' => 'TMN',
            'total_amount_toman' => 100000 * $installmentCount,
            'down_payment_toman' => 0,
            'principal_toman' => 100000 * $installmentCount,
            'monthly_interest_rate' => '0',
            'interest_type' => 'simple',
            'calculation_version' => 'legacy-contract-finance-v1',
            'pricing_snapshot_hash' => hash('sha256', 'payment-correction'),
            'request_uuid' => (string) Str::uuid(),
            'request_hash' => hash('sha256', 'payment-correction'),
            'installment_count' => $installmentCount,
            'start_date' => now()->toDateString(),
            'first_due_date' => now()->addMonthNoOverflow()->toDateString(),
            'status' => 'active',
        ]);
        $installments = collect(range(1, $installmentCount))->map(fn (int $number): Installment => Installment::create([
            'contract_id' => $contract->id,
            'installment_number' => $number,
            'due_date' => now()->copy()->addMonthsNoOverflow($number)->toDateString(),
            'amount_toman' => 100000,
            'paid_toman' => 0,
            'status' => 'pending',
        ]));
        $quote = app(CreateSettlementQuote::class)->handle($contract, [
            'request_id' => (string) Str::uuid(),
            'scope' => $installmentCount === 1 ? 'selected' : 'schedule',
            'installment_ids' => $installments->pluck('id')->all(),
            'amount_toman' => (string) (100000 * $installmentCount),
        ], $administrator);
        $payment = app(CaptureManualPayment::class)->handle($quote, [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
        ], $administrator);

        return [$administrator, $customer, $contract->fresh(), $installments->first()->fresh(), $payment->fresh()];
    }
}
