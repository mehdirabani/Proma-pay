<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->plan_snapshot;

        return [
            'id' => $this->id,
            'quote_uuid' => $this->quote_uuid,
            'contract_id' => $this->contract_id,
            'scope' => $this->scope,
            'requested_installment_ids' => $this->requested_installment_ids,
            'installment_ids' => $this->installment_ids,
            'amount_toman' => (string) $this->amount_toman,
            'full_settlement_toman' => (string) $this->full_settlement_toman,
            'calculation_date' => $this->calculation_date?->toDateString(),
            'calculation_version' => $this->calculation_version,
            'financial_rules_version' => $this->financial_rules_version,
            'expires_at' => $this->expires_at?->toISOString(),
            'consumed_at' => $this->consumed_at?->toISOString(),
            'allocation_plan' => $plan['allocations'] ?? [],
            'financial_totals' => [
                'principal_toman' => (string) ($plan['principal_total'] ?? '0'),
                'normal_penalty_toman' => (string) ($plan['normal_penalty_total'] ?? '0'),
                'legal_penalty_toman' => (string) ($plan['legal_penalty_total'] ?? '0'),
                'reward_toman' => (string) ($plan['reward_total'] ?? '0'),
            ],
        ];
    }
}
