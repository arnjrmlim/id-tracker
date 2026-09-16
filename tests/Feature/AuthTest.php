<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'      => 'Test Admin',
            'username'  => 'testadmin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('password'),
            'role'      => UserRole::ADMINISTRATOR,
            'is_active' => true,
        ], $overrides));
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'      => 'Test User',
            'username'  => 'testuser',
            'email'     => 'user@test.com',
            'password'  => Hash::make('password'),
            'role'      => UserRole::USER,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function admin_can_login_with_correct_credentials(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->post(route('login.post'), [
            'username' => 'testadmin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function regular_user_can_login(): void
    {
        $user = $this->makeUser();

        $response = $this->post(route('login.post'), [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_fails_with_wrong_password(): void
    {
        $this->makeAdmin();

        $response = $this->post(route('login.post'), [
            'username' => 'testadmin',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    #[Test]
    public function deactivated_user_cannot_login(): void
    {
        $this->makeUser(['is_active' => false, 'username' => 'inactive', 'email' => 'inactive@test.com']);

        $response = $this->post(route('login.post'), [
            'username' => 'inactive',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }
}
