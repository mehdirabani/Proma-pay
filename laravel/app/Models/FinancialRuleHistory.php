<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class FinancialRuleHistory extends Model
{
    protected $table = 'financial_rule_history';

    public $timestamps = false;

    protected $fillable = ['actor_id', 'request_id', 'request_hash', 'rule_version', 'old_values', 'new_values', 'created_at'];

    protected function casts(): array
    {
        return ['rule_version' => 'integer', 'old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Financial rule history is immutable.'));
        static::deleting(fn () => throw new LogicException('Financial rule history is immutable.'));
    }
}
