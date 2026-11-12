<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAndStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_recipe_mapping_and_kot_auto_deduction_with_auto_86(): void
    {
        [$waiter, $manager, , $table, $burger] = $this->setupFixtures();

        $supplier = $this->actingAs($manager)->postJson('/api/v1/inventory/suppliers', [
            'name' => 'Dhaka Fresh Meats',
            'contact_person' => 'Karim Uddin',
            'phone' => '01700000000',
        ])->assertCreated()->json('data');

        $patty = $this->actingAs($manager)->postJson('/api/v1/inventory/items', [
            'sku' => 'ING-PATTY',
            'name' => 'Beef Patty 150g',
            'category' => 'Meat & Poultry',
            'unit' => 'pcs',
            'initial_stock' => 0,
            'reorder_level' => 2,
            'unit_cost' => 120,
            'auto_eighty_six' => true,
        ])->assertCreated()->json('data');

        // Record Purchase Order for 3 patties @ 140 BDT each
        $po = $this->actingAs($manager)->postJson('/api/v1/inventory/purchases', [
            'supplier_id' => $supplier['id'],
            'reference_number' => 'BILL-901',
            'items' => [
                [
                    'inventory_item_id' => $patty['id'],
                    'quantity' => 3,
                    'unit_cost' => 140,
                ],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame('PO-0001', $po['po_number']);
        $this->assertSame('420.00', $po['total']);

        // Link Burger recipe: 1 burger uses 1 patty
        $this->actingAs($manager)->putJson("/api/v1/inventory/recipes/{$burger->id}", [
            'ingredients' => [
                [
                    'inventory_item_id' => $patty['id'],
                    'quantity' => 1,
                ],
            ],
        ])->assertOk();

        // Waiter opens order and sends 2 burgers to Kitchen
        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 2,
        ])->assertCreated()->json('data');

        $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $order['version']])
            ->assertCreated();

        // Stock is now 3 - 2 = 1.000 (low stock, but still >= 1 so burger is still available)
        $invAfterSend = $this->actingAs($manager)->getJson('/api/v1/inventory')->assertOk()->json('data');
        $this->assertSame('1.000', $invAfterSend['items'][0]['current_stock']);
        $this->assertSame(1, $invAfterSend['summary']['low_stock_count']);
        $this->assertTrue($burger->fresh()->available);

        // Manager logs 1 wasted patty -> stock drops to 0.000 and Burger auto-86s (available = false)
        $this->actingAs($manager)->postJson("/api/v1/inventory/items/{$patty['id']}/adjust", [
            'type' => 'WASTAGE',
            'quantity_delta' => 1,
            'reason' => 'Dropped patty on grill floor',
        ])->assertOk();

        $this->assertFalse($burger->fresh()->available);
    }

    public function test_staff_management_enforces_role_hierarchy_and_prevents_self_deactivation(): void
    {
        [, $manager, $admin] = $this->setupFixtures();

        // Manager creates a new Waiter
        $createdWaiter = $this->actingAs($manager)->postJson('/api/v1/staff', [
            'name' => 'Rafiul Islam',
            'email' => 'rafiul@restaurant.test',
            'password' => 'StrongPass#2026',
            'role_key' => 'waiter',
        ])->assertCreated()->json('data');

        $this->assertSame('rafiul@restaurant.test', $createdWaiter['email']);
        $this->assertSame('waiter', $createdWaiter['roles'][0]['key']);

        // Manager cannot escalate a user to Admin
        $this->actingAs($manager)->postJson('/api/v1/staff', [
            'name' => 'Fake Admin',
            'email' => 'fakeadmin@restaurant.test',
            'password' => 'StrongPass#2026',
            'role_key' => 'admin',
        ])->assertForbidden();

        // Manager cannot reset Admin's password
        $this->actingAs($manager)->postJson("/api/v1/staff/{$admin->id}/reset-password", [
            'password' => 'AnotherPass#2026',
            'reason' => 'Unauthorized attempt',
        ])->assertForbidden();

        // Manager can reset Waiter's password and deactivate them
        $this->actingAs($manager)->postJson("/api/v1/staff/{$createdWaiter['id']}/reset-password", [
            'password' => 'NewWaiterPass#2026',
            'reason' => 'Staff forgot shift password',
        ])->assertOk();

        $deactivated = $this->actingAs($manager)
            ->deleteJson("/api/v1/staff/{$createdWaiter['id']}/activation")
            ->assertOk()
            ->json('data');
        $this->assertFalse($deactivated['active']);

        // Manager cannot deactivate their own account
        $this->actingAs($manager)
            ->deleteJson("/api/v1/staff/{$manager->id}/activation")
            ->assertConflict();
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: DiningTable, 4: MenuItem}
     */
    private function setupFixtures(): array
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Bistro',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
            'active' => true,
        ]);

        $perms = collect([
            'view-floor',
            'manage-floor',
            'manage-menu',
            'manage-orders',
            'manage-users',
            'manage-security',
        ])->mapWithKeys(fn ($k) => [
            $k => Permission::query()->firstOrCreate(['key' => $k], ['name' => $k]),
        ]);

        $waiterRole = Role::query()->create(['key' => 'waiter', 'name' => 'Waiter']);
        $waiterRole->permissions()->sync([$perms['view-floor']->id, $perms['manage-orders']->id]);

        $managerRole = Role::query()->create(['key' => 'manager', 'name' => 'Manager']);
        $managerRole->permissions()->sync([
            $perms['view-floor']->id,
            $perms['manage-floor']->id,
            $perms['manage-menu']->id,
            $perms['manage-orders']->id,
        ]);

        $adminRole = Role::query()->create(['key' => 'admin', 'name' => 'Admin']);
        $adminRole->permissions()->sync($perms->pluck('id'));

        $waiter = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $waiter->roles()->sync([$waiterRole->id]);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $manager->roles()->sync([$managerRole->id]);

        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $admin->roles()->sync([$adminRole->id]);

        $zone = Zone::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main Hall',
            'sort_order' => 1,
            'active' => true,
        ]);

        $table = DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T1',
            'display_name' => 'Table 1',
            'capacity' => 4,
            'sort_order' => 1,
            'active' => true,
        ]);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Burgers',
            'default_station' => 'KITCHEN',
            'sort_order' => 1,
            'active' => true,
        ]);

        $burger = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'BRG-01',
            'name' => 'House Beef Burger',
            'station' => 'KITCHEN',
            'price' => 450,
            'available' => true,
            'active' => true,
        ]);

        return [$waiter, $manager, $admin, $table, $burger];
    }
}
