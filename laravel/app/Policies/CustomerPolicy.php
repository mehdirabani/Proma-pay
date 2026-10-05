<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('customers.view') || $user->hasPermissionTo('customers.view_all');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customers.view_all')
            || ($user->hasPermissionTo('customers.view') && $user->branch_id !== null && (int) $customer->branch_id === (int) $user->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customers.create')
            && ($user->branch_id !== null || $user->hasPermissionTo('customers.view_all'));
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customers.update') && $this->view($user, $customer);
    }
}
