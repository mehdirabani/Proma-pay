<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = ['customer_id', 'merchant_id', 'branch_id', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'activated_at', 'contract_number', 'prefix', 'serial', 'currency_code', 'total_amount_toman', 'down_payment_toman', 'principal_toman', 'monthly_interest_rate', 'interest_type', 'calculation_version', 'pricing_snapshot_hash', 'request_uuid', 'request_hash', 'installment_count', 'start_date', 'first_due_date', 'status'];

    protected function casts(): array
    {
        return ['serial' => 'integer', 'total_amount_toman' => 'string', 'down_payment_toman' => 'string', 'principal_toman' => 'string', 'start_date' => 'date', 'first_due_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'activated_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number');
    }
}
