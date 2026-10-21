<?php

namespace Tests\Feature;

use App\Actions\Payments\UpdateFinancialRules;
use App\Models\Contract;
use App\Models\FinancialRule;
use App\Models\KycCase;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\SettlementQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class PaymentSettlementTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_manual_payment_quote_is_recalculated_locked_and_captured_with_exact_allocations(): void
    {
        [$administrator, $customer, $contract] = $this->createActiveContract();
        Sanctum::actingAs($administrator, ['proma-api']);
        $quoteResponse = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, (int) $contract->installments()->first()->id))
            ->assertCreated()
            ->assertJsonPath('data.amount_toman', '500000')
            ->assertJsonPath('data.full_settlement_toman', '500000')
            ->assertJsonPath('data.calculation_version', 'installment-financial-state-v4-due-date-legal+payment-allocation-v2');
        $quoteUuid = $quoteResponse->json('data.quote_uuid');

        $capture = [
            'idempotency_key' => '5c6c74cf-a4e2-41c8-b705-2eacbe761315',
            'method' => 'bank_transfer',
            'provider_reference' => 'TRX-12345',
        ];
        $first = $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', $capture)
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_toman', '500000')
            ->assertJsonPath('data.payment_type', 'installment')
            ->assertJsonPath('data.allocations.0.principal_applied_toman', '500000');
        $replay = $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', $capture)->assertOk();

        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', [...$capture, 'provider_reference' => 'TRX-CHANGED'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $payment = Payment::query()->findOrFail($first->json('data.id'));
        $this->assertSame($customer->id, (int) $payment->customer_id);
        $this->assertSame($contract->id, (int) $payment->contract_id);
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame(now()->toDateString(), $payment->payment_date->toDateString());
        $this->assertSame(1, $payment->financial_rules_version);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $first->json('data.id'),
            'installment_id' => $contract->installments()->first()->id,
            'allocated_amount_toman' => 500000,
            'principal_applied_toman' => 500000,
            'status_after' => 'paid',
            'is_reversal' => false,
        ]);
        $this->assertDatabaseHas('installments', ['id' => $contract->installments()->first()->id, 'paid_toman' => 500000, 'status' => 'paid']);
        $this->assertDatabaseHas('settlement_quotes', ['quote_uuid' => $quoteUuid, 'consumed_payment_id' => $first->json('data.id')]);
        $this->assertDatabaseHas('audit_events', ['request_id' => $capture['idempotency_key'], 'event_name' => 'payment.manual_captured']);
    }

    public function test_quote_snapshot_becomes_stale_after_another_payment_changes_the_installment(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1);
        Sanctum::actingAs($administrator, ['proma-api']);
        $installmentId = (int) $contract->installments()->first()->id;
        $firstQuote = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, $installmentId, '8fe6160b-f8e7-4c1a-89e7-d38631059e3c'))->assertCreated()->json('data.quote_uuid');
        $staleQuote = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, $installmentId, '3126cfd7-6106-4ec4-9c3c-b23702963b85'))->assertCreated()->json('data.quote_uuid');

        $this->postJson('/api/v1/settlement-quotes/'.$firstQuote.'/capture', [
            'idempotency_key' => 'b043b27b-31c2-4c4e-9384-5338375f25e4',
            'method' => 'cash',
        ])->assertCreated();

        $this->postJson('/api/v1/settlement-quotes/'.$staleQuote.'/capture', [
            'idempotency_key' => 'e2cdf706-5788-45d4-9bde-9588c46251aa',
            'method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('settlement_quote');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('settlement_quotes', ['quote_uuid' => $staleQuote, 'consumed_at' => null]);
    }

    public function test_payment_quote_fails_closed_when_financial_policy_is_unconfigured(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1, configureRules: false);
        Sanctum::actingAs($administrator, ['proma-api']);

        $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, (int) $contract->installments()->first()->id))
            ->assertUnprocessable()->assertJsonValidationErrors('financial_rules');

        $this->assertFalse(FinancialRule::query()->findOrFail(1)->configured());
        $this->assertDatabaseCount('settlement_quotes', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_quote_uuid_is_idempotent_and_cannot_be_reused_for_a_different_amount(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1);
        Sanctum::actingAs($administrator, ['proma-api']);
        $installmentId = (int) $contract->installments()->first()->id;
        $input = $this->quoteInput($contract, $installmentId, 'ca1c7a51-96da-4490-932e-458ea150519c');
        $first = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $input)->assertCreated();
        $second = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $input)->assertOk();

        $this->assertSame($first->json('data.quote_uuid'), $second->json('data.quote_uuid'));
        $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', [...$input, 'amount_toman' => '400000'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('settlement_quotes', 1);
    }

    public function test_gateway_methods_are_not_misrepresented_as_manual_captures(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1);
        Sanctum::actingAs($administrator, ['proma-api']);
        $installmentId = (int) $contract->installments()->first()->id;
        $quoteUuid = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, $installmentId))->assertCreated()->json('data.quote_uuid');

        $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', [
            'idempotency_key' => 'f473a4f2-ae15-456c-bc2d-e67d48b7e6d9',
            'method' => 'online_gateway',
        ])->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('settlement_quotes', ['quote_uuid' => $quoteUuid, 'consumed_at' => null]);
    }

    public function test_changing_financial_policy_invalidates_an_open_quote(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1);
        Sanctum::actingAs($administrator, ['proma-api']);
        $installmentId = (int) $contract->installments()->first()->id;
        $quoteUuid = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, $installmentId))
            ->assertCreated()->json('data.quote_uuid');

        app(UpdateFinancialRules::class)->handle([
            'request_id' => (string) Str::uuid(),
            'monthly_penalty_rate' => '1',
            'legal_monthly_penalty_rate' => '0',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => '0',
        ], $administrator);

        $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', [
            'idempotency_key' => 'f7e75c6f-1e7c-459e-948c-4cce70ab9772',
            'method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('settlement_quote');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('settlement_quotes', ['quote_uuid' => $quoteUuid, 'consumed_at' => null]);
    }

    public function test_expired_quote_cannot_be_captured(): void
    {
        [$administrator, , $contract] = $this->createActiveContract(1);
        Sanctum::actingAs($administrator, ['proma-api']);
        $installmentId = (int) $contract->installments()->first()->id;
        $quoteUuid = $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, $installmentId))
            ->assertCreated()->json('data.quote_uuid');
        $this->travel(6)->minutes();

        $this->postJson('/api/v1/settlement-quotes/'.$quoteUuid.'/capture', [
            'idempotency_key' => '23d1b5de-ce7a-4322-a32d-624de4c35ed5',
            'method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('settlement_quote');

        $this->travelBack();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_branch_scoped_actor_cannot_quote_another_branch_contract(): void
    {
        [, , $contract, $branchB] = $this->createActiveContract(1);
        $branchA = $this->createBranch('OTHER');
        $operator = $this->createActor('operator', $branchA);
        $operator->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'payments.record')->firstOrFail());
        Sanctum::actingAs($operator, ['proma-api']);

        $this->postJson('/api/v1/contracts/'.$contract->id.'/settlement-quotes', $this->quoteInput($contract, (int) $contract->installments()->first()->id))
            ->assertForbidden();

        $this->assertDatabaseCount('settlement_quotes', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertNotSame($branchA->id, $branchB->id);
    }

    public function test_customer_payment_workflow_is_visible_in_persian_rtl_and_requires_finance_permission(): void
    {
        [$administrator, $customer, $contract] = $this->createActiveContract(1);

        $this->actingAs($administrator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('محاسبه و ثبت دریافت قسط')
            ->assertSee('کل اقساط قابل پرداخت قرارداد')
            ->assertSee('dir="rtl"', false);

        $operator = $this->createActor('operator', $customer->branch);
        $this->actingAs($operator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertDontSee('محاسبه و ثبت دریافت قسط');

        $this->assertTrue($contract->exists);
    }

    public function test_persian_web_flow_shows_the_quote_before_final_capture(): void
    {
        [$administrator, $customer, $contract] = $this->createActiveContract(2);
        $installmentId = (int) $contract->installments()->first()->id;
        $input = $this->quoteInput($contract, $installmentId);
        $quoteResponse = $this->actingAs($administrator)->post(route('customers.contracts.settlement-quotes.store', [$customer, $contract]), $input)
            ->assertRedirect();
        $quote = SettlementQuote::query()->where('request_id', $input['request_id'])->firstOrFail();
        $quoteResponse->assertRedirect(route('settlement-quotes.show', $quote));

        $this->actingAs($administrator)->get(route('settlement-quotes.show', $quote))
            ->assertOk()
            ->assertSee('بازبینی پیش‌فاکتور تسویه')
            ->assertSee('ریز تخصیص پیشنهادی')
            ->assertSee('ثبت دریافت')
            ->assertSee('dir="rtl"', false);

        $this->actingAs($administrator)->post(route('settlement-quotes.capture', $quote), [
            'idempotency_key' => 'e8fce86a-6a12-4143-8be8-516654bb6bd0',
            'method' => 'cash',
        ])->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('payments', ['contract_id' => $contract->id, 'status' => 'paid']);
    }

    private function quoteInput(Contract $contract, int $installmentId, ?string $requestId = null): array
    {
        return [
            'request_id' => $requestId ?? (string) Str::uuid(),
            'amount_toman' => '۵۰۰٬۰۰۰',
            'scope' => 'selected',
            'installment_ids' => [$installmentId],
        ];
    }

    private function createActiveContract(int $installmentCount = 2, bool $configureRules = true): array
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator', $branch);
        $finance = $this->createActor('finance', $branch);
        $customer = $this->createCustomer($branch);
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
            'installment_count' => (string) $installmentCount,
            'start_date' => now()->toDateString(),
            'first_due_date' => now()->addMonthNoOverflow()->toDateString(),
            'request_uuid' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        $contract = Contract::query()->findOrFail($contractId);

        $this->postJson('/api/v1/contracts/'.$contractId.'/submit', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'آزمون ثبت دریافت قسط',
        ])->assertOk();
        Sanctum::actingAs($finance, ['proma-api']);
        $this->putJson('/api/v1/contracts/'.$contractId.'/approve', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'آزمون کنترل دو مرحله‌ای قرارداد',
        ])->assertOk();
        Sanctum::actingAs($administrator, ['proma-api']);
        $this->postJson('/api/v1/contracts/'.$contractId.'/activate', [
            'request_id' => (string) Str::uuid(),
            'reason' => 'انتشار برنامهٔ اقساط آزمون',
        ])->assertOk();

        if ($configureRules) {
            app(UpdateFinancialRules::class)->handle([
                'request_id' => (string) Str::uuid(),
                'monthly_penalty_rate' => '0',
                'legal_monthly_penalty_rate' => '0',
                'monthly_reward_rate' => '0',
                'late_penalty_grace_days' => '0',
            ], $administrator);
        }

        return [$administrator, $customer, $contract->fresh(), $branch];
    }
}
