<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class PaymentIdempotencyTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_repeated_payment_intent_does_not_create_a_second_payment(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        Sanctum::actingAs($finance, ['proma-api']);
        $request = [
            'customer_id' => $customer->id,
            'idempotency_key' => '9dd5970c-e3b2-4e90-8ddf-e0f316f073d0',
            'amount_toman' => '۱٬۲۳۴٬۵۶۷',
            'method' => 'manual',
        ];

        $first = $this->postJson('/api/v1/payment-intents', $request)->assertCreated();
        $second = $this->postJson('/api/v1/payment-intents', $request)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('1234567', $first->json('data.amount_toman'));
        $this->assertSame('TMN', $first->json('data.currency_code'));
        $this->assertSame('pending', $first->json('data.status'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_idempotency_key_cannot_be_reused_with_a_different_amount(): void
    {
        $branch = $this->createBranch();
        $finance = $this->createActor('finance');
        $customer = $this->createCustomer($branch);
        Sanctum::actingAs($finance, ['proma-api']);
        $request = [
            'customer_id' => $customer->id,
            'idempotency_key' => 'cc1706c2-2e04-4f5a-95fd-bf85dfe21a7b',
            'amount_toman' => '250000',
            'method' => 'cash',
        ];
        $this->postJson('/api/v1/payment-intents', $request)->assertCreated();

        $this->postJson('/api/v1/payment-intents', [...$request, 'amount_toman' => '260000'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('payments', 1);
    }
}
