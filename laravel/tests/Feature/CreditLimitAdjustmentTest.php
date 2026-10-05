<?php

namespace Tests\Feature;

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
}
