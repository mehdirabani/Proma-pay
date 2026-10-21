<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class AuthenticationTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_login_screen_is_persian_and_right_to_left(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('lang="fa-IR"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('خوش آمدید');
    }

    public function test_active_administrator_can_login_and_logout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::create([
            'name' => 'مدیر آزمایشی',
            'email' => 'admin@example.test',
            'password' => Hash::make('a-strong-test-password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'administrator')->firstOrFail());

        $this->post('/login', ['email' => $user->email, 'password' => 'a-strong-test-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('نمای کلی سامانه');
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operator_cannot_list_customers_without_a_branch(): void
    {
        $operator = $this->createActor('operator');

        $this->actingAs($operator)->get('/customers')->assertOk()->assertSee('هنوز مشتری‌ای ثبت نشده است');
    }

    public function test_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $operator = $this->createActor('operator', $this->createBranch());
        $this->actingAs($operator);
        $operator->update(['is_active' => false]);

        $this->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }
}
