<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_id' => $this->installment_id,
            'allocated_amount_toman' => (string) $this->allocated_amount_toman,
            'principal_applied_toman' => (string) $this->principal_applied_toman,
            'normal_penalty_applied_toman' => (string) $this->normal_penalty_applied_toman,
            'legal_penalty_applied_toman' => (string) $this->legal_penalty_applied_toman,
            'reward_applied_toman' => (string) $this->reward_applied_toman,
            'status_after' => $this->status_after,
            'is_reversal' => (bool) $this->is_reversal,
        ];
    }
}
