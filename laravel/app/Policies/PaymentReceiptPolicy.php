<?php

namespace App\Policies;

use App\Models\PaymentReceipt;
use App\Models\User;

class PaymentReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('payments.view');
    }

    public function view(User $user, PaymentReceipt $receipt): bool
    {
        return $user->hasPermissionTo('payments.view')
            && ($user->hasPermissionTo('customers.view_all')
                || ($user->hasPermissionTo('customers.view')
                    && $user->branch_id !== null
                    && (int) $receipt->customer?->branch_id === (int) $user->branch_id));
    }

    public function review(User $user, PaymentReceipt $receipt): bool
    {
        return $user->hasPermissionTo('payments.receipt.review')
            && ($user->hasPermissionTo('customers.view_all')
                || ($user->hasPermissionTo('customers.view')
                    && $user->branch_id !== null
                    && (int) $receipt->customer?->branch_id === (int) $user->branch_id));
    }
}
