<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = ['customer_id', 'merchant_id', 'branch_id', 'created_by', 'contract_number', 'currency_code', 'total_amount_toman', 'down_payment_toman', 'principal_toman', 'installment_count', 'start_date', 'first_due_date', 'status'];

    protected function casts(): array
    {
        return ['total_amount_toman' => 'string', 'down_payment_toman' => 'string', 'principal_toman' => 'string', 'start_date' => 'date', 'first_due_date' => 'date'];
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
