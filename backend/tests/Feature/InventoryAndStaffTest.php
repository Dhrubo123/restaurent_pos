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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryAndStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_records_new_products_and_drinks_with_a_slip_in_one_entry(): void
    {
        Storage::fake('local');
        [, $supervisor, $admin] = $this->setupFixtures();
        $supervisor->roles()->attach(Role::query()->firstOrCreate(['key' => 'supervisor'], ['name' => 'Supervisor']));
        $slip = '%PDF-1.4 supplier slip';
        $items = [
            ['name' => 'Onions', 'quantity' => 5, 'unit' => 'kg', 'category' => 'Products'],
            ['name' => 'Cola', 'quantity' => 12, 'unit' => 'btl', 'category' => 'Drinks'],
        ];

        $purchase = $this->actingAs($supervisor)->post('/api/v1/inventory/purchases', [
            'items' => $items,
            'bill' => UploadedFile::fake()->createWithContent('slip.pdf', $slip),
        ])->assertCreated()->json('data');
        $this->assertDatabaseHas('inventory_items', ['name' => 'Onions', 'current_stock' => 5]);
        $this->assertDatabaseHas('inventory_items', ['name' => 'Cola', 'current_stock' => 12, 'category' => 'Drinks']);

        $this->actingAs($admin)->getJson('/api/v1/inventory?received_date=today')->assertOk()
            ->assertJsonPath('data.received_date', now('Asia/Dhaka')->toDateString())
            ->assertJsonPath('data.purchase_orders.0.receiver.name', $supervisor->name)
            ->assertJsonPath('data.purchase_orders.0.has_bill_copy', true)
            ->assertJsonCount(2, 'data.purchase_orders.0.items');
        $this->get("/api/v1/inventory/purchases/{$purchase['id']}/bill")
            ->assertOk()->assertStreamedContent($slip);
    }

    public function test_supervisor_can_upload_a_purchase_bill_and_only_own_restaurant_admin_can_view_it(): void
    {
        Storage::fake('local');
        [$waiter, $manager, $admin] = $this->setupFixtures();
        $supervisor = User::factory()->create(['restaurant_id' => $manager->restaurant_id, 'active' => true]);
        $supervisor->roles()->attach(Role::query()->firstOrCreate(['key' => 'supervisor'], ['name' => 'Supervisor']));

        $stock = $this->actingAs($manager)->postJson('/api/v1/inventory/items', [
            'name' => 'Beef Patty',
            'category' => 'Meat',
            'unit' => 'pcs',
            'reorder_level' => 0,
            'unit_cost' => 100,
        ])->assertCreated()->json('data');
        $this->assertMatchesRegularExpression('/^INV-[0-9A-Z]{26}$/', $stock['sku']);

        $purchase = $this->postJson('/api/v1/inventory/purchases', [
            'items' => [['inventory_item_id' => $stock['id'], 'quantity' => 5, 'unit_cost' => 100]],
        ])->assertCreated()->json('data');
        $url = "/api/v1/inventory/purchases/{$purchase['id']}/bill";
        $bill = '%PDF-1.4 test purchase bill';

        $this->actingAs($waiter)->post($url, [
            'bill' => UploadedFile::fake()->createWithContent('bill.pdf', $bill),
        ])->assertForbidden();
        $this->actingAs($supervisor)->postJson($url, [
            'bill' => UploadedFile::fake()->createWithContent('bill.txt', 'Not a bill'),
        ])->assertUnprocessable()->assertJsonValidationErrors('bill');
        $this->actingAs($supervisor)->post($url, [
            'bill' => UploadedFile::fake()->createWithContent('bill.pdf', $bill),
        ])->assertOk();
        $this->get($url)->assertForbidden();

        $this->actingAs($admin)->get($url)->assertOk()->assertStreamedContent($bill);
        $this->getJson('/api/v1/inventory')->assertOk()
            ->assertJsonPath('data.purchase_orders.0.has_bill_copy', true)
            ->assertJsonPath('data.items.0.current_stock', '5.000');
        $this->getJson('/api/v1/inventory?received_date=2000-01-01')->assertOk()
            ->assertJsonCount(0, 'data.purchase_orders');
        $this->getJson('/api/v1/inventory?received_date='.now('Asia/Dhaka')->toDateString())->assertOk()
            ->assertJsonPath('data.purchase_orders.0.id', $purchase['id']);

        $otherRestaurant = Restaurant::query()->create([
            'name' => 'Other Restaurant',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
            'active' => true,
        ]);
        $otherAdmin = User::factory()->create(['restaurant_id' => $otherRestaurant->id, 'active' => true]);
        $otherAdmin->roles()->attach(Role::query()->where('key', 'admin')->firstOrFail());
        $this->actingAs($otherAdmin)->get($url)->assertNotFound();
    }

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

    public function test_only_admin_can_access_staff_management_and_cannot_deactivate_self(): void
    {
        [, $manager, $admin] = $this->setupFixtures();

        $supervisor = User::factory()->create(['restaurant_id' => $admin->restaurant_id, 'active' => true]);
        $supervisor->roles()->attach(Role::query()->firstOrCreate(['key' => 'supervisor'], ['name' => 'Supervisor']));

        $staffPayload = [
            'name' => 'Rafiul Islam',
            'email' => 'rafiul@restaurant.test',
            'password' => 'StrongPass#2026',
            'role_key' => 'waiter',
        ];
        foreach ([$manager, $supervisor] as $nonAdmin) {
            $this->actingAs($nonAdmin)->postJson('/api/v1/staff', $staffPayload)->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['email' => $staffPayload['email']]);

        $createdWaiter = $this->actingAs($admin)->postJson('/api/v1/staff', $staffPayload)->assertCreated()->json('data');

        $this->assertSame('rafiul@restaurant.test', $createdWaiter['email']);
        $this->assertSame('waiter', $createdWaiter['roles'][0]['key']);

        $staffUrl = "/api/v1/staff/{$createdWaiter['id']}";
        $passwordReset = ['password' => 'NewWaiterPass#2026', 'reason' => 'Staff forgot shift password'];
        foreach ([$manager, $supervisor] as $nonAdmin) {
            $this->actingAs($nonAdmin)->getJson('/api/v1/staff')->assertForbidden();
            $this->patchJson($staffUrl, ['name' => 'Unauthorized update'])->assertForbidden();
            $this->postJson("{$staffUrl}/reset-password", $passwordReset)->assertForbidden();
            $this->postJson("{$staffUrl}/activation")->assertForbidden();
            $this->deleteJson("{$staffUrl}/activation")->assertForbidden();
        }

        $this->actingAs($admin)->getJson('/api/v1/staff')->assertOk();
        $this->patchJson($staffUrl, ['name' => 'Updated Waiter'])->assertOk();
        $this->postJson("{$staffUrl}/reset-password", $passwordReset)->assertOk();

        $deactivated = $this->deleteJson("{$staffUrl}/activation")
            ->assertOk()
            ->json('data');
        $this->assertFalse($deactivated['active']);
        $this->postJson("{$staffUrl}/activation")->assertOk()->assertJsonPath('data.active', true);

        $this->deleteJson("/api/v1/staff/{$admin->id}/activation")
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
