<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class CustomerManagementTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_customer_create_accepts_persian_digits_and_encrypts_national_id(): void
    {
        $branch = $this->createBranch();
        $operator = $this->createActor('operator', $branch);
        Sanctum::actingAs($operator, ['proma-api']);

        $response = $this->postJson('/api/v1/customers', [
            'full_name' => 'نگار رضایی',
            'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
            'national_id' => '۱۲۳۴۵۶۷۸۹۰',
        ]);

        $response->assertCreated()->assertJsonPath('data.mobile', '09123456789')
            ->assertJsonMissingPath('data.national_id_encrypted');
        $customer = Customer::query()->firstOrFail();
        $this->assertSame('1234567890', $customer->national_id_encrypted);
        $this->assertNotSame('1234567890', $customer->getRawOriginal('national_id_encrypted'));
        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'limit_toman' => 0]);
    }

    public function test_customer_lists_are_limited_to_the_actors_branch(): void
    {
        $branch = $this->createBranch('ONE');
        $otherBranch = $this->createBranch('TWO');
        $operator = $this->createActor('operator', $branch);
        $visible = $this->createCustomer($branch, '09111111111');
        $hidden = $this->createCustomer($otherBranch, '09222222222');
        Sanctum::actingAs($operator, ['proma-api']);

        $this->getJson('/api/v1/customers')->assertOk()
            ->assertJsonFragment(['id' => $visible->id])
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    public function test_kyc_review_requires_a_reason_when_rejecting(): void
    {
        $branch = $this->createBranch();
        $operator = $this->createActor('operator', $branch);
        $customer = $this->createCustomer($branch);
        Sanctum::actingAs($operator, ['proma-api']);
        $caseId = $this->postJson('/api/v1/customers/'.$customer->id.'/kyc-cases', ['identity_provider' => 'manual'])
            ->assertCreated()->json('data.id');

        $this->putJson('/api/v1/kyc-cases/'.$caseId.'/review', ['status' => 'rejected'])
            ->assertUnprocessable()->assertJsonValidationErrors('review_note');
    }

    public function test_customer_detail_page_renders_kyc_credit_and_payment_sections(): void
    {
        $branch = $this->createBranch();
        $operator = $this->createActor('operator', $branch);
        $customer = $this->createCustomer($branch);

        $this->actingAs($operator)->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('احراز هویت')
            ->assertSee('حساب اعتبار')
            ->assertSee('درخواست‌های پرداخت')
            ->assertSee('dir="rtl"', false);
    }

    public function test_credit_and_payment_workflows_are_available_from_customer_page(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        $this->actingAs($finance);

        $this->post('/customers/'.$customer->id.'/credit-adjustments', [
            'new_limit_toman' => '۶۰۰٬۰۰۰',
            'reason' => 'تأیید بررسی اعتباری',
            'request_id' => '57292927-48fe-40a5-b8fa-4505952152e1',
        ])->assertRedirect(route('customers.show', $customer));

        $this->post('/customers/'.$customer->id.'/payment-intents', [
            'customer_id' => $customer->id,
            'idempotency_key' => '9dd5970c-e3b2-4e90-8ddf-e0f316f073d0',
            'amount_toman' => '۱٬۲۰۰٬۰۰۰',
            'method' => 'manual',
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('credit_accounts', ['customer_id' => $customer->id, 'limit_toman' => 600000]);
        $this->assertDatabaseHas('payments', ['customer_id' => $customer->id, 'amount_toman' => 1200000, 'status' => 'pending']);
    }
}
