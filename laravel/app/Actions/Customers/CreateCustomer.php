<?php

namespace App\Actions\Customers;

use App\Models\AuditEvent;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCustomer
{
    public function handle(array $input, User $actor, ?string $requestId): Customer
    {
        return DB::transaction(function () use ($input, $actor, $requestId): Customer {
            $nationalId = $input['national_id'] ?? null;
            $nationalIdHash = $nationalId === null ? null : hash_hmac('sha256', $nationalId, (string) config('app.key'));

            if ($nationalIdHash !== null && Customer::query()->where('national_id_hash', $nationalIdHash)->exists()) {
                throw ValidationException::withMessages(['national_id' => 'این کد ملی قبلاً ثبت شده است.']);
            }

            $customer = Customer::create([
                'branch_id' => $actor->branch_id,
                'created_by' => $actor->id,
                'full_name' => $input['full_name'],
                'mobile' => $input['mobile'],
                'email' => $input['email'] ?? null,
                'national_id_encrypted' => $nationalId,
                'national_id_hash' => $nationalIdHash,
                'birth_date' => $input['birth_date'] ?? null,
            ]);

            CreditAccount::create([
                'customer_id' => $customer->id,
                'currency_code' => config('proma.currency_code'),
                'limit_toman' => 0,
                'used_toman' => 0,
                'reserved_toman' => 0,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'request_id' => $requestId,
                'event_name' => 'customer.created',
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'metadata' => ['branch_id' => $actor->branch_id],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $customer;
        }, attempts: 3);
    }
}
