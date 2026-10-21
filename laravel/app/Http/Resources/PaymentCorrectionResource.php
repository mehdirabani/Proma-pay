<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'contract_id' => $this->contract_id,
            'customer_id' => $this->customer_id,
            'amount_toman' => (string) $this->amount_toman,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
            'bank_refund_performed' => false,
        ];
    }
}
