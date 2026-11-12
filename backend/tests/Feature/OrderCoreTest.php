<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\TaxCategory;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_order_enforces_single_open_order_per_table_and_idempotency(): void
    {
        [$waiter, , , [$t1]] = $this->orderSetup();

        $first = $this->actingAs($waiter)
            ->withHeader('Idempotency-Key', 'open-t1-key-1')
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 3])
            ->assertCreated();

        $orderId = $first->json('data.id');

        $repeat = $this->actingAs($waiter)
            ->withHeader('Idempotency-Key', 'open-t1-key-1')
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 3])
            ->assertCreated();

        $this->assertSame($orderId, $repeat->json('data.id'));

        $this->actingAs($waiter)
            ->withHeader('Idempotency-Key', 'open-t1-key-1')
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 6])
            ->assertConflict();

        $secondOpen = $this->actingAs($waiter)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 4])
            ->assertOk();

        $this->assertSame($orderId, $secondOpen->json('data.id'));
        $this->assertSame(1, Order::query()->where('dining_table_id', $t1->id)->count());
    }

    public function test_price_snapshot_is_preserved_after_menu_price_change_and_client_price_tampering_is_ignored(): void
    {
        [$waiter, $manager, $restaurant, [$t1], $burger, $cheeseOption] = $this->orderSetup();

        $open = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])
            ->assertCreated();
        $orderId = $open->json('data.id');

        $afterFirstItem = $this->actingAs($waiter)->postJson("/api/v1/orders/{$orderId}/items", [
            'version' => 1,
            'menu_item_id' => $burger->id,
            'quantity' => 1,
            'modifier_option_ids' => [$cheeseOption->id],
            'price' => 1.00,
        ])->assertCreated();

        $this->assertSame('450.00', $afterFirstItem->json('data.items.0.unit_price'));
        $this->assertSame('60.00', $afterFirstItem->json('data.items.0.modifier_unit_total'));
        $this->assertSame('510.00', $afterFirstItem->json('data.subtotal'));
        $this->assertDatabaseHas('audit_events', [
            'restaurant_id' => $restaurant->id,
            'action' => 'security.price_tamper_ignored',
        ]);

        $this->actingAs($manager)->patchJson("/api/v1/menu/items/{$burger->id}", [
            'price' => 480,
            'reason' => 'Updated menu price',
        ])->assertOk();

        $afterSecondItem = $this->actingAs($waiter)->postJson("/api/v1/orders/{$orderId}/items", [
            'version' => 2,
            'menu_item_id' => $burger->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame('450.00', $afterSecondItem->json('data.items.0.unit_price'));
        $this->assertSame('480.00', $afterSecondItem->json('data.items.1.unit_price'));
        $this->assertSame('990.00', $afterSecondItem->json('data.subtotal'));
        $this->assertSame('49.50', $afterSecondItem->json('data.tax_amount'));
        $this->assertSame('1039.50', $afterSecondItem->json('data.total'));
    }

    public function test_concurrent_stale_order_version_returns_409_conflict(): void
    {
        [$waiter, , , [$t1], $burger] = $this->orderSetup();

        $open = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])
            ->assertCreated();
        $orderId = $open->json('data.id');

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$orderId}/items", [
            'version' => 1,
            'menu_item_id' => $burger->id,
            'quantity' => 1,
        ])->assertCreated()->assertJsonPath('data.version', 2);

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$orderId}/items", [
            'version' => 1,
            'menu_item_id' => $burger->id,
            'quantity' => 2,
        ])->assertConflict()->assertJsonPath('code', 'conflict');

        $this->assertCount(1, Order::query()->findOrFail($orderId)->items);
    }

    public function test_table_transfer_and_order_merge_workflows(): void
    {
        [$waiter, , , [$t1, $t2, $t3], $burger] = $this->orderSetup();

        $order1 = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order1 = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order1['id']}/items", [
            'version' => $order1['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $transferred = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order1['id']}/transfer", [
            'version' => $order1['version'],
            'to_dining_table_id' => $t2->id,
            'reason' => 'Guest requested quieter table',
        ])->assertOk()->json('data');

        $this->assertSame($t2->id, $transferred['dining_table_id']);
        $this->assertNull($t1->fresh()->openOrder());
        $this->assertNotNull($t2->fresh()->openOrder());

        $order3 = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t3->id}/orders", ['guest_count' => 3])
            ->assertCreated()
            ->json('data');

        $order3 = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order3['id']}/items", [
            'version' => $order3['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 2,
        ])->assertCreated()->json('data');

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$transferred['id']}/transfer", [
            'version' => $transferred['version'],
            'to_dining_table_id' => $t3->id,
            'reason' => 'Try transfer into occupied table',
        ])->assertConflict();

        $merged = $this->actingAs($waiter)->postJson("/api/v1/orders/{$transferred['id']}/merge", [
            'version' => $transferred['version'],
            'target_order_id' => $order3['id'],
            'reason' => 'Friends joined Table 3',
        ])->assertOk()->json('data');

        $this->assertSame($order3['id'], $merged['id']);
        $this->assertSame(5, $merged['guest_count']);
        $this->assertCount(2, $merged['items']);
        $this->assertSame('1350.00', $merged['subtotal']);
        $this->assertSame('MERGED', Order::query()->findOrFail($transferred['id'])->status);
        $this->assertNull($t2->fresh()->openOrder());
    }

    private function orderSetup(): array
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

        $tables = collect([1, 2, 3])->map(fn (int $num) => DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T'.$num,
            'display_name' => 'Table '.$num,
            'capacity' => 4,
            'active' => true,
        ]))->all();

        $vat = TaxCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'VAT 5%',
            'rate' => 5.000,
            'inclusive' => false,
            'active' => true,
        ]);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Mains',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);

        $group = ModifierGroup::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Add-ons',
            'min_selections' => 0,
            'max_selections' => 2,
            'required' => false,
            'active' => true,
        ]);

        $cheese = ModifierOption::query()->create([
            'restaurant_id' => $restaurant->id,
            'modifier_group_id' => $group->id,
            'name' => 'Extra Cheese',
            'price' => 60,
            'active' => true,
        ]);

        $burger = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'tax_category_id' => $vat->id,
            'sku' => 'BRG-01',
            'name' => 'Smokehouse Burger',
            'station' => 'KITCHEN',
            'price' => 450,
            'active' => true,
            'available' => true,
        ]);
        $burger->modifierGroups()->attach($group->id);

        $waiter = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $waiterRole = Role::query()->create(['key' => 'waiter-'.uniqid(), 'name' => 'Waiter']);
        foreach (['view-floor', 'manage-orders'] as $permKey) {
            $perm = Permission::query()->firstOrCreate(['key' => $permKey], ['name' => $permKey]);
            $waiterRole->permissions()->attach($perm);
        }
        $waiter->roles()->attach($waiterRole);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $managerRole = Role::query()->create(['key' => 'manager-'.uniqid(), 'name' => 'Manager']);
        foreach (['view-floor', 'manage-floor', 'manage-menu', 'manage-orders'] as $permKey) {
            $perm = Permission::query()->firstOrCreate(['key' => $permKey], ['name' => $permKey]);
            $managerRole->permissions()->attach($perm);
        }
        $manager->roles()->attach($managerRole);

        return [$waiter, $manager, $restaurant, $tables, $burger, $cheese];
    }
}
