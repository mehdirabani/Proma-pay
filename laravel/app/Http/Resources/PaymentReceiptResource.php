<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'customer_id' => $this->customer_id,
            'contract_id' => $this->contract_id,
            'installment_id' => $this->installment_id,
            'payment_id' => $this->payment_id,
            'amount_toman' => $this->amount_toman,
            'approved_amount_toman' => $this->approved_amount_toman,
            'status' => $this->status,
            'file' => [
                'mime' => $this->file_mime,
                'size_bytes' => $this->file_size,
                'download_url' => route('api.v1.payment-receipts.file', $this->id),
                'temporary_url_endpoint' => route('api.v1.payment-receipts.temporary-url', $this->id),
            ],
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
        ];
    }
}
