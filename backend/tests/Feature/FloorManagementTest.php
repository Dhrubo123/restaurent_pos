<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_a_table_in_their_restaurant(): void
    {
        [$manager, $restaurant, $zone] = $this->floorUser('manage-floor');

        $response = $this->actingAs($manager)->postJson('/api/v1/tables', [
            'zone_id' => $zone->id,
            'code' => 'T16',
            'display_name' => 'Table 16',
            'capacity' => 4,
        ]);

        $response->assertCreated()->assertJsonPath('data.code', 'T16');
        $this->assertDatabaseHas('dining_tables', [
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T16',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'table.created', 'actor_id' => $manager->id]);
    }

    public function test_user_without_floor_permission_cannot_create_a_table(): void
    {
        [$user, , $zone] = $this->floorUser(null);

        $this->actingAs($user)->postJson('/api/v1/tables', [
            'zone_id' => $zone->id,
            'code' => 'T16',
            'display_name' => 'Table 16',
            'capacity' => 4,
        ])->assertForbidden();
    }

    public function test_table_with_active_order_cannot_be_deactivated(): void
    {
        [$manager, $restaurant, $zone] = $this->floorUser('manage-floor');
        $table = DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T4',
            'display_name' => 'Table 4',
            'capacity' => 4,
            'active' => true,
        ]);
        Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'dining_table_id' => $table->id,
            'order_number' => 'ORD-0001',
            'status' => 'OPEN',
            'waiter_id' => $manager->id,
            'guest_count' => 2,
            'opened_at' => now(),
        ]);

        $this->actingAs($manager)
            ->deleteJson("/api/v1/tables/{$table->id}/activation")
            ->assertConflict();

        $this->assertTrue($table->fresh()->active);
    }

    public function test_floor_never_leaks_another_restaurants_tables(): void
    {
        [$user, $restaurant, $zone] = $this->floorUser('view-floor');
        DiningTable::query()->create([
            'restaurant_id' => $restaurant->id, 'zone_id' => $zone->id, 'code' => 'T1',
            'display_name' => 'Table 1', 'capacity' => 4, 'active' => true,
        ]);
        $other = Restaurant::query()->create(['name' => 'Other', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT']);
        $otherZone = Zone::query()->create(['restaurant_id' => $other->id, 'name' => 'Secret', 'active' => true]);
        DiningTable::query()->create([
            'restaurant_id' => $other->id, 'zone_id' => $otherZone->id, 'code' => 'X1',
            'display_name' => 'Hidden', 'capacity' => 4, 'active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/floor')->assertOk();
        $this->assertStringContainsString('Table 1', $response->getContent());
        $this->assertStringNotContainsString('Hidden', $response->getContent());
    }

    private function floorUser(?string $permission): array
    {
        $restaurant = Restaurant::query()->create(['name' => 'Test Restaurant', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT']);
        $zone = Zone::query()->create(['restaurant_id' => $restaurant->id, 'name' => 'Indoor', 'active' => true]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'test-'.uniqid(), 'name' => 'Test role']);
        if ($permission) {
            $permissionModel = Permission::query()->firstOrCreate(['key' => $permission], ['name' => $permission]);
            $role->permissions()->attach($permissionModel);
        }
        $user->roles()->attach($role);

        return [$user, $restaurant, $zone];
    }
}
