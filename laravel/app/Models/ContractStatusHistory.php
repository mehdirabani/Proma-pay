<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContractStatusHistory extends Model
{
    protected $table = 'contract_status_history';

    public $timestamps = false;

    protected $fillable = ['contract_id', 'actor_id', 'request_id', 'request_hash', 'from_status', 'to_status', 'reason', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Contract status history is immutable.'));
        static::deleting(fn () => throw new LogicException('Contract status history is immutable.'));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
