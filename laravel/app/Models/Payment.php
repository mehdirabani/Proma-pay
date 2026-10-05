<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'customer_id', 'contract_id', 'settlement_quote_id', 'created_by', 'idempotency_key', 'request_hash',
        'currency_code', 'amount_toman', 'method', 'status', 'provider_reference', 'requested_at', 'completed_at',
        'payment_date', 'payment_time', 'payment_type', 'calculation_version', 'financial_rules_version',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'string',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'payment_date' => 'date',
            'financial_rules_version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function correction(): HasOne
    {
        return $this->hasOne(PaymentCorrection::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
