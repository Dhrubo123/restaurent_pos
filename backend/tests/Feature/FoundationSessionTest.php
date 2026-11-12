<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RestaurantSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_rotation_preserves_roles_and_invalidates_sessions(): void
    {
        $this->seed(RestaurantSetupSeeder::class);
        $restaurant = Restaurant::query()->firstOrFail();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->where('key', 'manager')->firstOrFail();
        $user->roles()->attach($role);
        $oldHash = $user->password;
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $password = Str::password(32);
        $this->artisan('pos:create-manager', ['--reset-password' => true, '--name' => $user->name, '--email' => $user->email])
            ->expectsQuestion('Password (minimum 12 characters, mixed case, number, and symbol)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();
        $this->assertNotSame($oldHash, $user->fresh()->password);
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertTrue($user->roles()->where('key', 'manager')->exists());
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_unauthenticated_api_without_accept_header_returns_401(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertExactJson([
            'code' => 'unauthenticated', 'message' => 'Authentication is required.',
        ]);
    }

    public function test_login_returns_the_rotated_csrf_token(): void
    {
        $restaurant = Restaurant::query()->create(['name' => 'Session Test']);
        $password = Str::password(24);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true, 'password' => $password]);
        $previousToken = $this->getJson('/api/v1/csrf-token')->assertOk()->json('token');
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->assertNotSame($previousToken, $response->json('token'));
        $this->assertSame(session()->token(), $response->json('token'));
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_unexpected_errors_are_safe_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/test-failure', function () {
            throw new \RuntimeException('Private database credentials must never reach the client');
        });
        $this->getJson('/api/v1/test-failure')->assertInternalServerError()->assertExactJson([
            'code' => 'server_error', 'message' => 'An unexpected error occurred.',
        ]);
    }

    public function test_login_is_rate_limited_with_retry_header(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => Str::password(24)])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => Str::password(24)])
            ->assertTooManyRequests()->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
    }
}
