<?php

namespace App\Actions\Kyc;

use App\Enums\KycStatus;
use App\Models\AuditEvent;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewKycCase
{
    public function handle(KycCase $kycCase, KycStatus $status, ?string $reviewNote, User $actor, ?string $requestId, ?string $ipAddress): KycCase
    {
        return DB::transaction(function () use ($kycCase, $status, $reviewNote, $actor, $requestId, $ipAddress): KycCase {
            $lockedCase = KycCase::query()->whereKey($kycCase->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedCase->status, [KycStatus::Pending, KycStatus::UnderReview], true)) {
                throw ValidationException::withMessages(['status' => 'این پروندهٔ احراز هویت قبلاً بررسی شده است.']);
            }

            $lockedCase->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'request_id' => $requestId,
                'event_name' => 'kyc.reviewed',
                'subject_type' => KycCase::class,
                'subject_id' => $lockedCase->id,
                'metadata' => ['status' => $status->value, 'review_note' => $reviewNote],
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $lockedCase;
        });
    }
}
