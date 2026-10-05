<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->full_name),
            'contract_id' => $this->contract_id,
            'amount_toman' => (string) $this->amount_toman,
            'currency_code' => $this->currency_code,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'requested_at' => $this->requested_at?->toISOString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_type' => $this->payment_type,
            'calculation_version' => $this->calculation_version,
            'financial_rules_version' => $this->financial_rules_version,
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
