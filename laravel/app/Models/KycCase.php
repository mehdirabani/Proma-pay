<?php

namespace App\Models;

use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycCase extends Model
{
    protected $fillable = ['customer_id', 'reviewed_by', 'status', 'identity_provider', 'review_note', 'submitted_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['status' => KycStatus::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
