<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['customer_id', 'contract_id', 'created_by', 'idempotency_key', 'currency_code', 'amount_toman', 'method', 'status', 'provider_reference', 'requested_at', 'completed_at'];

    protected function casts(): array
    {
        return ['amount_toman' => 'string', 'method' => PaymentMethod::class, 'status' => PaymentStatus::class, 'requested_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
