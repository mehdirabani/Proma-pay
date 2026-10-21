<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'installment_id', 'contract_id', 'customer_id', 'request_id', 'request_hash',
        'amount_toman', 'reason', 'snapshot', 'corrected_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'string',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment corrections are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment corrections are immutable.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
