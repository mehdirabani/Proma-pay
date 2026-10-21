<?php

namespace App\Actions\Kyc;

use App\Enums\KycStatus;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitKycCase
{
    public function handle(Customer $customer, User $actor, ?string $identityProvider, ?string $requestId, ?string $ipAddress): KycCase
    {
        return DB::transaction(function () use ($customer, $actor, $identityProvider, $requestId, $ipAddress): KycCase {
            $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $case = $lockedCustomer->kycCases()->create([
                'status' => KycStatus::Pending,
                'identity_provider' => $identityProvider ?: 'manual',
                'submitted_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'request_id' => $requestId,
                'event_name' => 'kyc.submitted',
                'subject_type' => KycCase::class,
                'subject_id' => $case->id,
                'metadata' => ['customer_id' => $lockedCustomer->id],
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $case;
        });
    }
}
