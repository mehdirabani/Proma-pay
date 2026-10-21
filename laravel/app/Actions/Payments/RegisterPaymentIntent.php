<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPaymentIntent
{
    public function handle(array $input, User $actor): Payment
    {
        try {
            return DB::transaction(function () use ($input, $actor): Payment {
                $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $input, $actor);

                    return $existing;
                }

                return Payment::create([
                    'customer_id' => $input['customer_id'],
                    'contract_id' => $input['contract_id'] ?? null,
                    'created_by' => $actor->id,
                    'idempotency_key' => $input['idempotency_key'],
                    'currency_code' => config('proma.currency_code'),
                    'amount_toman' => $input['amount_toman'],
                    'method' => $input['method'],
                    'status' => PaymentStatus::Pending,
                    'provider_reference' => $input['provider_reference'] ?? null,
                    'requested_at' => now(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $input, $actor);

                return $existing;
            }

            throw $exception;
        }
    }

    private function assertSameRequest(Payment $payment, array $input, User $actor): void
    {
        if ((int) $payment->customer_id !== (int) $input['customer_id']
            || (int) $payment->contract_id !== (int) ($input['contract_id'] ?? 0)
            || (int) $payment->created_by !== (int) $actor->id
            || (string) $payment->amount_toman !== (string) $input['amount_toman']
            || $payment->method->value !== $input['method']
            || (string) $payment->provider_reference !== (string) ($input['provider_reference'] ?? '')) {
            throw ValidationException::withMessages(['idempotency_key' => 'این شناسهٔ تکرار با اطلاعات متفاوتی استفاده شده است.']);
        }
    }
}
