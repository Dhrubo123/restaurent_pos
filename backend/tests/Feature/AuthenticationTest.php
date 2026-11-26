<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Role;
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

    public function test_supervisor_password_unlocks_staff_session_and_records_who_unlocked_it(): void
    {
        $password = Str::password(24);
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'active' => true,
        ]);
        $supervisor = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'active' => true,
            'password' => Hash::make($password),
        ]);
        $supervisor->roles()->attach(Role::query()->firstOrCreate(['key' => 'supervisor'], ['name' => 'Supervisor']));

        $this->actingAs($user)
            ->postJson('/api/v1/auth/unlock', ['password' => $password])
            ->assertOk()
            ->assertJsonPath('message', 'POS unlocked.');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', [
            'restaurant_id' => $restaurant->id,
            'actor_id' => $supervisor->id,
            'auditable_id' => $user->id,
            'action' => 'pos.unlocked',
        ]);
    }

    public function test_staff_and_other_restaurant_supervisor_passwords_cannot_unlock_the_pos(): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
        ]);
        $otherRestaurant = Restaurant::query()->create([
            'name' => 'Other Restaurant', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
        ]);
        $staffPassword = Str::password(24);
        $otherPassword = Str::password(24);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'active' => true,
            'password' => Hash::make($staffPassword),
        ]);
        $otherSupervisor = User::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'active' => true,
            'password' => Hash::make($otherPassword),
        ]);
        $otherSupervisor->roles()->attach(Role::query()->firstOrCreate(['key' => 'supervisor'], ['name' => 'Supervisor']));

        $this->actingAs($user);
        foreach ([$staffPassword, $otherPassword] as $password) {
            $this->postJson('/api/v1/auth/unlock', ['password' => $password])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('audit_events', ['action' => 'pos.unlocked']);
    }

    public function test_guest_cannot_use_the_pos_unlock_endpoint(): void
    {
        $this->postJson('/api/v1/auth/unlock', ['password' => 'any-password'])
            ->assertUnauthorized();
    }
}
