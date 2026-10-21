<?php

namespace App\Actions\Credit;

use App\Models\AuditEvent;
use App\Models\CreditAccount;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustCreditLimit
{
    public function handle(int $customerId, string $newLimitToman, string $reason, string $requestId, User $actor): CreditLedgerEntry
    {
        try {
            return DB::transaction(function () use ($customerId, $newLimitToman, $reason, $requestId, $actor): CreditLedgerEntry {
                $account = CreditAccount::query()->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
                $existingEntry = CreditLedgerEntry::query()->where('request_id', $requestId)->first();

                if ($existingEntry !== null) {
                    $this->assertSameRequest($existingEntry, $account, $newLimitToman, $reason, $actor);

                    return $existingEntry;
                }

                $committedToman = bcadd((string) $account->used_toman, (string) $account->reserved_toman, 0);
                if (bccomp($newLimitToman, $committedToman, 0) < 0) {
                    throw ValidationException::withMessages(['new_limit_toman' => 'سقف جدید از اعتبار مصرف‌شده و رزروشده کمتر است.']);
                }

                $oldLimitToman = (string) $account->limit_toman;
                $amountToman = bccomp($newLimitToman, $oldLimitToman, 0) >= 0
                    ? bcsub($newLimitToman, $oldLimitToman, 0)
                    : bcsub($oldLimitToman, $newLimitToman, 0);

                $entry = CreditLedgerEntry::create([
                    'credit_account_id' => $account->id,
                    'actor_id' => $actor->id,
                    'request_id' => $requestId,
                    'entry_type' => 'limit_adjustment',
                    'amount_toman' => $amountToman,
                    'old_limit_toman' => $oldLimitToman,
                    'new_limit_toman' => $newLimitToman,
                    'reason' => $reason,
                ]);

                $account->update(['limit_toman' => $newLimitToman]);

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $requestId,
                    'event_name' => 'credit.limit_adjusted',
                    'subject_type' => CreditAccount::class,
                    'subject_id' => $account->id,
                    'metadata' => ['old_limit_toman' => $oldLimitToman, 'new_limit_toman' => $newLimitToman, 'reason' => $reason],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                return $entry;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingEntry = CreditLedgerEntry::query()->where('request_id', $requestId)->first();
            $account = CreditAccount::query()->where('customer_id', $customerId)->first();
            if ($existingEntry !== null && $account !== null) {
                $this->assertSameRequest($existingEntry, $account, $newLimitToman, $reason, $actor);

                return $existingEntry;
            }

            throw $exception;
        }
    }

    private function assertSameRequest(CreditLedgerEntry $entry, CreditAccount $account, string $newLimitToman, string $reason, User $actor): void
    {
        if ((int) $entry->credit_account_id !== (int) $account->id
            || (string) $entry->new_limit_toman !== $newLimitToman
            || (string) $entry->reason !== $reason
            || (int) $entry->actor_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['request_id' => 'شناسهٔ درخواست قبلاً با اطلاعات دیگری استفاده شده است.']);
        }
    }
}
