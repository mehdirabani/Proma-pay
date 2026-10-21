<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'settlement_quote_id', 'contract_id', 'installment_id', 'allocated_amount_toman',
        'principal_applied_toman', 'normal_penalty_applied_toman', 'legal_penalty_applied_toman',
        'reward_applied_toman', 'principal_before_toman', 'principal_after_toman',
        'normal_penalty_before_toman', 'normal_penalty_after_toman', 'legal_penalty_before_toman',
        'legal_penalty_after_toman', 'status_after', 'calculation_snapshot', 'is_reversal', 'reversal_of_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount_toman' => 'string',
            'principal_applied_toman' => 'string',
            'normal_penalty_applied_toman' => 'string',
            'legal_penalty_applied_toman' => 'string',
            'reward_applied_toman' => 'string',
            'principal_before_toman' => 'string',
            'principal_after_toman' => 'string',
            'normal_penalty_before_toman' => 'string',
            'normal_penalty_after_toman' => 'string',
            'legal_penalty_before_toman' => 'string',
            'legal_penalty_after_toman' => 'string',
            'calculation_snapshot' => 'array',
            'is_reversal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment allocations are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment allocations are immutable.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SettlementQuote::class, 'settlement_quote_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
