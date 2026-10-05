<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentReceipt extends Model
{
    protected $fillable = [
        'request_id', 'request_hash', 'customer_id', 'contract_id', 'installment_id', 'payment_id',
        'amount_toman', 'approved_amount_toman', 'file_disk', 'file_path', 'file_mime', 'file_size', 'file_sha256',
        'status', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note', 'review_request_id', 'review_request_hash',
    ];

    protected function casts(): array
    {
        return [
            'amount_toman' => 'string',
            'approved_amount_toman' => 'string',
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentReceipt $receipt): void {
            $allowed = [
                'payment_id', 'approved_amount_toman', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
                'review_request_id', 'review_request_hash', 'updated_at',
            ];
            if (array_diff(array_keys($receipt->getDirty()), $allowed) !== []) {
                throw new LogicException('Payment receipt evidence and submission details are immutable.');
            }
            if ($receipt->getOriginal('status') !== 'pending'
                || ! in_array($receipt->status, ['approved', 'rejected'], true)) {
                throw new LogicException('A payment receipt can receive one terminal review decision only.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payment receipt evidence is retained as financial history.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
