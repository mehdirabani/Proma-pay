<?php

namespace App\Actions\Payments;

use App\Models\AuditEvent;
use App\Models\FinancialRule;
use App\Models\FinancialRuleHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateFinancialRules
{
    public function handle(array $input, User $actor): FinancialRule
    {
        $requestHash = hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'monthly_penalty_rate' => (string) $input['monthly_penalty_rate'],
            'legal_monthly_penalty_rate' => (string) $input['legal_monthly_penalty_rate'],
            'monthly_reward_rate' => (string) $input['monthly_reward_rate'],
            'late_penalty_grace_days' => (int) $input['late_penalty_grace_days'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($input, $actor, $requestHash): FinancialRule {
                $existing = FinancialRuleHistory::query()->where('request_id', $input['request_id'])->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return FinancialRule::query()->findOrFail(1);
                }

                $rules = FinancialRule::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                $oldValues = $rules->configured() ? $rules->snapshot() : null;
                $rules->update([
                    'monthly_penalty_rate' => $input['monthly_penalty_rate'],
                    'legal_monthly_penalty_rate' => $input['legal_monthly_penalty_rate'],
                    'monthly_reward_rate' => $input['monthly_reward_rate'],
                    'late_penalty_grace_days' => $input['late_penalty_grace_days'],
                    'version' => $rules->version + 1,
                    'updated_by' => $actor->id,
                ]);

                $newValues = $rules->fresh()->snapshot();
                FinancialRuleHistory::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_id'],
                    'request_hash' => $requestHash,
                    'rule_version' => $rules->version,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'created_at' => now(),
                ]);

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_id'],
                    'event_name' => 'financial_rules.updated',
                    'subject_type' => FinancialRule::class,
                    'subject_id' => 1,
                    'metadata' => ['old_values' => $oldValues, 'new_values' => $newValues],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                return $rules->fresh();
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = FinancialRuleHistory::query()->where('request_id', $input['request_id'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $requestHash);

                return FinancialRule::query()->findOrFail(1);
            }

            throw $exception;
        }
    }

    private function assertSameRequest(FinancialRuleHistory $history, string $requestHash): void
    {
        if (! hash_equals($history->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسهٔ درخواست قبلاً برای تنظیم دیگری استفاده شده است.']);
        }
    }
}
