<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_sign_in_and_session_is_rotated(): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
        ]);
        $password = Str::password(24);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'active' => true,
            'email' => 'manager@example.test',
            'password' => Hash::make($password),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->assertJsonPath('data.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $password = Str::password(24);
        $user = User::factory()->create([
            'active' => false,
            'email' => 'inactive@example.test',
            'password' => Hash::make($password),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_incompatible_password_hash_returns_a_safe_validation_error(): void
    {
        $password = Str::password(24);
        $bcryptHash = Hash::make($password);
        $user = User::factory()->create([
            'active' => true,
            'email' => 'legacy-hash@example.test',
            'password' => '$2a$'.substr($bcryptHash, 4),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The supplied credentials are invalid.');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out_and_session_is_invalidated(): void
    {
        $user = User::factory()->create(['active' => true]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out.');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_unlock_the_pos_with_their_password(): void
    {
        $password = Str::password(24);
        $user = User::factory()->create([
            'active' => true,
            'password' => Hash::make($password),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/unlock', ['password' => $password])
            ->assertOk()
            ->assertJsonPath('message', 'POS unlocked.');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_cannot_unlock_the_pos(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'password' => Hash::make(Str::password(24)),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/unlock', ['password' => 'not-the-right-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password is incorrect.');

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_cannot_use_the_pos_unlock_endpoint(): void
    {
        $this->postJson('/api/v1/auth/unlock', ['password' => 'any-password'])
            ->assertUnauthorized();
    }
}
