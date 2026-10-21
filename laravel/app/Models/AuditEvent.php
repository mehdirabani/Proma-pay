<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'request_id', 'event_name', 'subject_type', 'subject_id', 'metadata', 'ip_address', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit events are immutable.'));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
