<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RestaurantSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_manager_have_explicitly_different_permissions(): void
    {
        $this->seed(RestaurantSetupSeeder::class);

        $admin = Role::query()->where('key', 'admin')->firstOrFail();
        $manager = Role::query()->where('key', 'manager')->firstOrFail();

        $this->assertTrue($admin->permissions()->where('key', 'manage-floor')->exists());
        $this->assertTrue($manager->permissions()->where('key', 'manage-floor')->exists());
        $this->assertTrue($admin->permissions()->where('key', 'manage-users')->exists());
        $this->assertFalse($manager->permissions()->where('key', 'manage-users')->exists());
        $this->assertTrue($admin->permissions()->where('key', 'manage-security')->exists());
        $this->assertFalse($manager->permissions()->where('key', 'manage-security')->exists());
    }

    public function test_cors_allows_configured_frontend_with_credentials(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/me')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_does_not_allow_unlisted_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/me')->assertNoContent();

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
