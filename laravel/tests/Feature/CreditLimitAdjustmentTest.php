<?php

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class CreditLimitAdjustmentTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_credit_adjustment_is_idempotent_and_keeps_an_immutable_ledger(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        Sanctum::actingAs($finance, ['proma-api']);
        $request = ['new_limit_toman' => '۵۰۰٬۰۰۰', 'reason' => 'تأیید ارزیابی اعتبار', 'request_id' => '57292927-48fe-40a5-b8fa-4505952152e1'];

        $first = $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', $request)->assertOk();
        $second = $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', $request)->assertOk();

        $this->assertSame($first->json('data.entry_id'), $second->json('data.entry_id'));
        $this->assertDatabaseCount('credit_ledger_entries', 1);
        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'limit_toman' => 500000]);
    }

    public function test_credit_limit_cannot_drop_below_used_and_reserved_amounts(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        $customer->creditAccount()->update(['used_toman' => 300000, 'reserved_toman' => 100000]);
        Sanctum::actingAs($finance, ['proma-api']);

        $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', [
            'new_limit_toman' => '۳۵۰۰۰۰',
            'reason' => 'درخواست کاهش سقف اعتبار',
            'request_id' => '8c1e8159-4935-4eef-bfa6-41f357071562',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_limit_toman');

        $this->assertDatabaseCount('credit_ledger_entries', 0);
    }

    public function test_credit_adjustment_rejects_reusing_request_id_with_changed_reason(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        Sanctum::actingAs($finance, ['proma-api']);
        $request = [
            'new_limit_toman' => '500000',
            'reason' => 'تأیید ارزیابی اعتبار',
            'request_id' => 'c103ea7f-c743-4bc1-a25d-613e03eb4584',
        ];

        $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', $request)->assertOk();
        $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', [...$request, 'reason' => 'درخواست متفاوت برای افزایش اعتبار'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_id');

        $this->assertDatabaseCount('credit_ledger_entries', 1);
        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'limit_toman' => 500000]);
    }

    public function test_branch_scoped_credit_adjuster_cannot_change_another_branch_customer(): void
    {
        $branchA = $this->createBranch('A');
        $branchB = $this->createBranch('B');
        $operator = $this->createActor('operator', $branchA);
        $operator->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'credit.adjust')->firstOrFail());
        $customer = $this->createCustomer($branchB);
        Sanctum::actingAs($operator, ['proma-api']);

        $this->postJson('/api/v1/customers/'.$customer->id.'/credit-adjustments', [
            'new_limit_toman' => '500000',
            'reason' => 'تأیید ارزیابی اعتبار',
            'request_id' => 'b18d805f-1bee-4e14-8eb2-6278bb8e07cf',
        ])->assertForbidden();

        $this->assertDatabaseCount('credit_ledger_entries', 0);
        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'limit_toman' => 0]);
    }
}
