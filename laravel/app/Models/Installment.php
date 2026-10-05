<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    protected $fillable = ['contract_id', 'installment_number', 'due_date', 'amount_toman', 'paid_toman', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount_toman' => 'string', 'paid_toman' => 'string'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }
}
