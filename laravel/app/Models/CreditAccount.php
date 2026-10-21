<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends Model
{
    protected $fillable = ['customer_id', 'currency_code', 'limit_toman', 'used_toman', 'reserved_toman', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['limit_toman' => 'string', 'used_toman' => 'string', 'reserved_toman' => 'string', 'expires_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    public function availableToman(): string
    {
        return bcsub((string) $this->limit_toman, bcadd((string) $this->used_toman, (string) $this->reserved_toman, 0), 0);
    }
}
