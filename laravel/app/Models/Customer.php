<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'created_by', 'full_name', 'mobile', 'email', 'national_id_encrypted', 'national_id_hash', 'birth_date', 'status'];

    protected function casts(): array
    {
        return ['national_id_encrypted' => 'encrypted', 'birth_date' => 'date'];
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasPermissionTo('customers.view_all')) {
            return $query;
        }

        if ($actor->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $actor->branch_id);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function kycCases(): HasMany
    {
        return $this->hasMany(KycCase::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CreditAccount::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
