<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\KycCase;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class ContractDraftTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_contract_draft_creates_a_versioned_legacy_schedule_and_replays_idempotently(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'identity_provider' => 'manual',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $administrator->id,
        ]);
        Sanctum::actingAs($administrator, ['proma-api']);
        $request = [
            'customer_id' => $customer->id,
            'principal_toman' => '۱٬۰۰۰٬۰۰۰',
            'down_payment_toman' => '۱۰۰٬۰۰۰',
            'monthly_interest_rate' => '۰',
            'interest_type' => 'simple',
            'installment_count' => '۳',
            'start_date' => '2026-01-01',
            'first_due_date' => '2026-01-31',
            'request_uuid' => '8877d809-0fb4-4f21-871a-099956f91544',
        ];

        $first = $this->postJson('/api/v1/contracts', $request)->assertCreated()
            ->assertJsonPath('data.contract_number', 'PR-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_amount_toman', '1000000')
            ->assertJsonPath('data.calculation_version', 'legacy-contract-finance-v1');
        $second = $this->postJson('/api/v1/contracts', $request)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $contract = Contract::query()->findOrFail($first->json('data.id'));
        $this->assertSame('PR', $contract->prefix);
        $this->assertSame(1, $contract->serial);
        $this->assertSame('100000', (string) $contract->down_payment_toman);
        $this->assertSame('300000', (string) $contract->installments()->firstOrFail()->amount_toman);
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $contract->installments()->pluck('due_date')->map(fn ($date) => substr((string) $date, 0, 10))->all());
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('installments', 3);
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'reserved_toman' => 0]);
    }

    public function test_idempotency_key_cannot_create_another_draft_when_terms_change(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        Sanctum::actingAs($administrator, ['proma-api']);
        $request = $this->validRequest($customer->id);

        $this->postJson('/api/v1/contracts', $request)->assertCreated();
        $this->postJson('/api/v1/contracts', [...$request, 'principal_toman' => '۱٬۱۰۰٬۰۰۰'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_uuid');

        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('installments', 2);
    }

    public function test_contract_draft_requires_the_latest_kyc_case_to_be_approved(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'rejected', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        Sanctum::actingAs($administrator, ['proma-api']);

        $this->postJson('/api/v1/contracts', $this->validRequest($customer->id))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('installments', 0);
    }

    public function test_down_payment_cannot_exceed_principal(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        Sanctum::actingAs($administrator, ['proma-api']);

        $this->postJson('/api/v1/contracts', [...$this->validRequest($customer->id), 'down_payment_toman' => '۱٬۱۰۰٬۰۰۰'])
            ->assertUnprocessable()->assertJsonValidationErrors('down_payment_toman');

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_contract_creation_is_restricted_to_authorized_roles(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        Sanctum::actingAs($finance, ['proma-api']);

        $this->postJson('/api/v1/contracts', $this->validRequest($customer->id))->assertForbidden();
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_branch_scoped_contract_creator_cannot_create_for_another_branch_customer(): void
    {
        $branchA = $this->createBranch('A');
        $branchB = $this->createBranch('B');
        $operator = $this->createActor('operator', $branchA);
        $operator->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'contracts.create')->firstOrFail());
        $customer = $this->createCustomer($branchB);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        Sanctum::actingAs($operator, ['proma-api']);

        $this->postJson('/api/v1/contracts', $this->validRequest($customer->id))->assertNotFound();
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_authorized_persian_rtl_form_creates_a_draft_without_posting_money(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);
        $input = [...$this->validRequest($customer->id), 'request_uuid' => 'a18e5fa0-d248-48f0-bab7-ded96f283b2b'];

        $this->actingAs($administrator)->post('/customers/'.$customer->id.'/contracts', $input)
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('installments', 2);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('contracts', ['customer_id' => $customer->id, 'status' => 'draft']);
    }

    public function test_persian_contract_draft_form_is_rtl_and_explains_that_money_is_not_posted(): void
    {
        $branch = $this->createBranch();
        $administrator = $this->createActor('administrator');
        $customer = $this->createCustomer($branch);
        KycCase::create(['customer_id' => $customer->id, 'status' => 'approved', 'identity_provider' => 'manual', 'submitted_at' => now()]);

        $this->actingAs($administrator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('ذخیرهٔ پیش‌نویس قرارداد')
            ->assertSee('اعتبار رزرو یا پرداختی ثبت نمی‌شود')
            ->assertSee('dir="rtl"', false);
    }

    private function validRequest(int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'principal_toman' => '1,000,000',
            'down_payment_toman' => '0',
            'monthly_interest_rate' => '5',
            'interest_type' => 'compound',
            'installment_count' => '2',
            'start_date' => '2026-10-05',
            'first_due_date' => '2026-11-05',
            'request_uuid' => 'c9e53688-3817-4b8b-b06f-7a8b7a638b52',
        ];
    }
}
