<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401_with_safe_api_error(): void
    {
        $this->getJson('/api/v1/floor')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Authentication is required.',
                'code' => 'unauthenticated',
            ]);
    }

    public function test_role_without_permission_returns_403(): void
    {
        [$user, , $zone] = $this->foundationUser(false);

        $this->actingAs($user)->postJson('/api/v1/tables', $this->validTable($zone))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_duplicate_table_code_returns_field_validation_error(): void
    {
        [$manager, $restaurant, $zone] = $this->foundationUser();
        DiningTable::query()->create([
            ...$this->validTable($zone),
            'restaurant_id' => $restaurant->id,
            'active' => true,
        ]);

        $this->actingAs($manager)->postJson('/api/v1/tables', [
            ...$this->validTable($zone),
            'display_name' => 'Patio Table',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_duplicate_display_name_returns_field_validation_error(): void
    {
        [$manager, $restaurant, $zone] = $this->foundationUser();
        DiningTable::query()->create([
            ...$this->validTable($zone),
            'restaurant_id' => $restaurant->id,
            'active' => true,
        ]);

        $this->actingAs($manager)->postJson('/api/v1/tables', [
            ...$this->validTable($zone),
            'code' => 'T2',
        ])->assertUnprocessable()->assertJsonValidationErrors('display_name');
    }

    #[DataProvider('invalidCapacities')]
    public function test_invalid_capacity_returns_422(mixed $capacity): void
    {
        [$manager, , $zone] = $this->foundationUser();

        $this->actingAs($manager)->postJson('/api/v1/tables', [
            ...$this->validTable($zone),
            'capacity' => $capacity,
        ])->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }

    public static function invalidCapacities(): array
    {
        return [
            'zero' => [0],
            'over maximum' => [101],
            'not an integer' => ['four'],
        ];
    }

    public function test_manager_can_edit_deactivate_and_reactivate_table(): void
    {
        [$manager, $restaurant, $zone] = $this->foundationUser();
        $table = DiningTable::query()->create([
            ...$this->validTable($zone),
            'restaurant_id' => $restaurant->id,
            'active' => true,
        ]);

        $this->actingAs($manager)->patchJson("/api/v1/tables/{$table->id}", [
            'display_name' => 'Window Table',
            'capacity' => 6,
        ])->assertOk()->assertJsonPath('data.display_name', 'Window Table');
        $this->assertDatabaseHas('dining_tables', [
            'id' => $table->id,
            'display_name' => 'Window Table',
            'capacity' => 6,
        ]);

        $this->actingAs($manager)->deleteJson("/api/v1/tables/{$table->id}/activation")
            ->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('dining_tables', ['id' => $table->id, 'active' => false]);

        $this->actingAs($manager)->postJson("/api/v1/tables/{$table->id}/activation")
            ->assertOk()->assertJsonPath('data.active', true);
        $this->assertDatabaseHas('dining_tables', ['id' => $table->id, 'active' => true]);
    }

    public function test_manager_can_create_edit_deactivate_and_reactivate_empty_zone(): void
    {
        [$manager] = $this->foundationUser();

        $response = $this->actingAs($manager)->postJson('/api/v1/zones', ['name' => 'Rooftop'])
            ->assertCreated()->assertJsonPath('data.name', 'Rooftop');
        $zoneId = $response->json('data.id');

        $this->actingAs($manager)->patchJson("/api/v1/zones/{$zoneId}", ['name' => 'Upper Deck'])
            ->assertOk()->assertJsonPath('data.name', 'Upper Deck');
        $this->actingAs($manager)->deleteJson("/api/v1/zones/{$zoneId}/activation")
            ->assertOk()->assertJsonPath('data.active', false);
        $this->actingAs($manager)->postJson("/api/v1/zones/{$zoneId}/activation")
            ->assertOk()->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('zones', ['id' => $zoneId, 'name' => 'Upper Deck', 'active' => true]);
    }

    public function test_zone_with_active_table_cannot_be_deactivated(): void
    {
        [$manager, $restaurant, $zone] = $this->foundationUser();
        DiningTable::query()->create([
            ...$this->validTable($zone),
            'restaurant_id' => $restaurant->id,
            'active' => true,
        ]);

        $this->actingAs($manager)->deleteJson("/api/v1/zones/{$zone->id}/activation")
            ->assertConflict()
            ->assertJsonPath('message', 'Deactivate or move every active table before deactivating this zone.');
        $this->assertTrue($zone->fresh()->active);
    }

    private function foundationUser(bool $canManageFloor = true): array
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
        ]);
        $zone = Zone::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Indoor',
            'active' => true,
        ]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'role-'.uniqid(), 'name' => 'Test role']);

        if ($canManageFloor) {
            $permission = Permission::query()->create(['key' => 'manage-floor', 'name' => 'Manage floor']);
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return [$user, $restaurant, $zone];
    }

    private function validTable(Zone $zone): array
    {
        return [
            'zone_id' => $zone->id,
            'code' => 'T1',
            'display_name' => 'Table 1',
            'capacity' => 4,
        ];
    }
}
