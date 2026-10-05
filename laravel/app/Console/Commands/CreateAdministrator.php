<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'proma:create-admin';

    protected $description = 'ساخت کاربر مدیر بدون حساب یا گذرواژهٔ پیش‌فرض';

    public function handle(): int
    {
        $name = trim((string) $this->ask('نام مدیر'));
        $email = mb_strtolower(trim((string) $this->ask('ایمیل ورود')));
        $password = (string) $this->secret('گذرواژه (حداقل ۱۲ نویسه)');
        $confirmation = (string) $this->secret('تکرار گذرواژه');
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $role = Role::query()->where('slug', 'administrator')->first();
        if ($role === null) {
            $this->error('ابتدا مهاجرت و Seeder را اجرا کنید.');

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true]);
        $user->roles()->attach($role);
        $this->info('حساب مدیر ساخته شد. گذرواژه در ترمینال نمایش داده نشده است.');

        return self::SUCCESS;
    }
}
