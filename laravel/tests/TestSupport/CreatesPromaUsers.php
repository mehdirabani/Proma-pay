<?php

namespace Tests\TestSupport;

use App\Models\Branch;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesPromaUsers
{
    protected function createActor(string $roleSlug = 'administrator', ?Branch $branch = null): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::create([
            'name' => 'کاربر آزمایشی',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('a-strong-test-password'),
            'branch_id' => $branch?->id,
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail());

        return $user;
    }

    protected function createBranch(string $code = 'TEST'): Branch
    {
        return Branch::create(['name' => 'شعبهٔ آزمایشی', 'code' => $code.'-'.Str::lower(Str::random(6)), 'status' => 'active']);
    }

    protected function createCustomer(Branch $branch, string $mobile = '09123456789'): Customer
    {
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'full_name' => 'مشتری آزمایشی',
            'mobile' => $mobile,
            'status' => 'active',
        ]);
        CreditAccount::create([
            'customer_id' => $customer->id,
            'currency_code' => config('proma.currency_code'),
            'limit_toman' => 0,
            'used_toman' => 0,
            'reserved_toman' => 0,
        ]);

        return $customer;
    }
}
