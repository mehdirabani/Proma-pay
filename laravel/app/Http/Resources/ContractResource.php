<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'customer_id' => $this->customer_id,
            'currency_code' => $this->currency_code,
            'total_amount_toman' => (string) $this->total_amount_toman,
            'down_payment_toman' => (string) $this->down_payment_toman,
            'principal_toman' => (string) $this->principal_toman,
            'monthly_interest_rate' => (string) $this->monthly_interest_rate,
            'interest_type' => $this->interest_type,
            'installment_count' => (int) $this->installment_count,
            'start_date' => $this->start_date?->toDateString(),
            'first_due_date' => $this->first_due_date?->toDateString(),
            'calculation_version' => $this->calculation_version,
            'status' => $this->status,
        ];
    }
}
