<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SettlementQuote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quote_uuid', 'request_id', 'request_hash', 'actor_id', 'customer_id', 'contract_id', 'scope',
        'requested_installment_ids', 'installment_ids', 'amount_toman', 'full_settlement_toman',
        'calculation_date', 'calculation_version', 'financial_rules_version', 'financial_rules_snapshot',
        'plan_snapshot', 'snapshot_hash', 'expires_at', 'consumed_at', 'consumed_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_installment_ids' => 'array',
            'installment_ids' => 'array',
            'amount_toman' => 'string',
            'full_settlement_toman' => 'string',
            'calculation_date' => 'date',
            'financial_rules_version' => 'integer',
            'financial_rules_snapshot' => 'array',
            'plan_snapshot' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SettlementQuote $quote): void {
            if (array_diff(array_keys($quote->getDirty()), ['consumed_at', 'consumed_payment_id']) !== []) {
                throw new LogicException('Settlement quote snapshots are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Settlement quotes are immutable.'));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'consumed_payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isOpen(): bool
    {
        return $this->consumed_at === null;
    }

    public function getRouteKeyName(): string
    {
        return 'quote_uuid';
    }
}
