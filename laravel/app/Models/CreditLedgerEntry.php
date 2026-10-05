<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CreditLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['credit_account_id', 'actor_id', 'request_id', 'entry_type', 'amount_toman', 'old_limit_toman', 'new_limit_toman', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['amount_toman' => 'string', 'old_limit_toman' => 'string', 'new_limit_toman' => 'string', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Credit ledger entries are immutable.'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class, 'credit_account_id');
    }
}
