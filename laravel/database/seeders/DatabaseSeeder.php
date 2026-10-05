<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissionNames = [
            'dashboard.view' => 'مشاهدهٔ داشبورد',
            'customers.view' => 'مشاهدهٔ مشتریان شعبه',
            'customers.view_all' => 'مشاهدهٔ همهٔ مشتریان',
            'customers.create' => 'ایجاد مشتری',
            'customers.update' => 'ویرایش مشتری',
            'kyc.review' => 'بررسی احراز هویت',
            'credit.view' => 'مشاهدهٔ اعتبار',
            'credit.adjust' => 'تغییر سقف اعتبار',
            'payments.view' => 'مشاهدهٔ پرداخت‌ها',
            'payments.record' => 'ثبت درخواست پرداخت',
            'contracts.create' => 'ایجاد پیش‌نویس قرارداد',
            'contracts.submit' => 'ارسال قرارداد برای تأیید',
            'contracts.approve' => 'تأیید قرارداد',
            'contracts.activate' => 'فعال‌سازی قرارداد تأییدشده',
            'access.manage' => 'مدیریت دسترسی‌ها',
        ];

        $permissions = collect($permissionNames)->mapWithKeys(
            fn (string $name, string $slug): array => [$slug => Permission::updateOrCreate(['slug' => $slug], ['name' => $name])],
        );

        $administrator = Role::updateOrCreate(['slug' => 'administrator'], ['name' => 'مدیر سامانه']);
        $administrator->permissions()->sync($permissions->pluck('id'));

        $operator = Role::updateOrCreate(['slug' => 'operator'], ['name' => 'اپراتور شعبه']);
        $operator->permissions()->sync($permissions->only([
            'dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'kyc.review', 'credit.view',
        ])->pluck('id'));

        $finance = Role::updateOrCreate(['slug' => 'finance'], ['name' => 'کارشناس مالی']);
        $finance->permissions()->sync($permissions->only([
            'dashboard.view', 'customers.view', 'customers.view_all', 'credit.view', 'credit.adjust', 'payments.view', 'payments.record', 'contracts.approve',
        ])->pluck('id'));
    }
}
