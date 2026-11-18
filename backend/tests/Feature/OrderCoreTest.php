<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\KotItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_one_order_line_moves_to_another_open_table_and_both_bills_recalculate(): void
    {
        [$waiter, , $restaurant, [$t1, $t2], $burger, $cheese] = $this->orderSetup();
        $source = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $target = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t2->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
        ])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
            'modifier_option_ids' => [$cheese->id],
        ])->assertCreated()->json('data');
        $movedId = $source['items'][1]['id'];

        $result = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items/{$movedId}/transfer", [
            'version' => $source['version'],
            'to_dining_table_id' => $t2->id,
            'reason' => 'Guest at Table 2 ordered this dish',
        ])->assertOk()->json('data');

        $this->assertCount(1, $result['items']);
        $this->assertSame('472.50', $result['total']);
        $this->assertSame('535.50', Order::query()->findOrFail($target['id'])->total);
        $this->assertSame($target['id'], OrderItem::query()->findOrFail($movedId)->order_id);
        $this->assertSame('60.00', OrderItem::query()->findOrFail($movedId)->modifier_unit_total);
        $this->assertDatabaseHas('audit_events', ['restaurant_id' => $restaurant->id, 'action' => 'order_item.transferred', 'auditable_id' => $movedId]);
    }

    public function test_sent_item_moves_to_new_table_without_a_second_kot(): void
    {
        [$waiter, $manager, , [$t1, $t2], $burger] = $this->orderSetup();
        $source = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
        ])->assertCreated()->json('data');
        $itemId = $source['items'][0]['id'];
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/send", [
            'version' => $source['version'],
        ])->assertCreated()->json('data');

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items/{$itemId}/transfer", [
            'version' => $source['version'], 'to_dining_table_id' => $t2->id,
            'reason' => 'Guest moved to Table 2',
        ])->assertOk();

        $this->assertSame(1, KotItem::query()->count());
        $this->assertSame(1, Kot::query()->count());
        $this->assertSame('T1', $this->actingAs($manager)->getJson('/api/v1/kots')->assertOk()->json('data.0.table.code'));
        $this->assertSame('472.50', Order::query()->where('dining_table_id', $t2->id)->firstOrFail()->total);
        $this->assertSame('0.00', Order::query()->findOrFail($source['id'])->total);
    }

    public function test_served_item_cannot_move_to_another_table(): void
    {
        [$waiter, , , [$t1, $t2], $burger] = $this->orderSetup();
        $source = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
        ])->assertCreated()->json('data');
        $itemId = $source['items'][0]['id'];
        OrderItem::query()->findOrFail($itemId)->update(['status' => 'SERVED', 'sent_quantity' => 1, 'served_quantity' => 1]);

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items/{$itemId}/transfer", [
            'version' => $source['version'], 'to_dining_table_id' => $t2->id,
            'reason' => 'Attempt to move served food',
        ])->assertConflict();

        $this->assertSame($source['id'], OrderItem::query()->findOrFail($itemId)->order_id);
        $this->assertNull($t2->fresh()->openOrder());
    }

    public function test_item_transfer_rejects_recorded_payment_without_changing_either_check(): void
    {
        [$waiter, , , [$t1, $t2], $burger] = $this->orderSetup();
        $source = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
        ])->assertCreated()->json('data');
        $itemId = $source['items'][0]['id'];
        Order::query()->findOrFail($source['id'])->update(['paid_amount' => 100, 'status' => 'PARTIALLY_PAID']);

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items/{$itemId}/transfer", [
            'version' => $source['version'], 'to_dining_table_id' => $t2->id,
            'reason' => 'Guest moved this item',
        ])->assertConflict();

        $this->assertSame($source['id'], OrderItem::query()->findOrFail($itemId)->order_id);
        $this->assertNull($t2->fresh()->openOrder());
    }

    public function test_item_transfer_cannot_target_another_restaurants_table(): void
    {
        [$waiter, , , [$t1], $burger] = $this->orderSetup();
        $otherRestaurant = Restaurant::query()->create(['name' => 'Other Cafe', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT']);
        $otherZone = Zone::query()->create(['restaurant_id' => $otherRestaurant->id, 'name' => 'Indoor', 'active' => true]);
        $otherTable = DiningTable::query()->create([
            'restaurant_id' => $otherRestaurant->id, 'zone_id' => $otherZone->id,
            'code' => 'T1', 'display_name' => 'Other Table', 'capacity' => 2, 'active' => true,
        ]);
        $source = $this->actingAs($waiter)->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 2])->assertCreated()->json('data');
        $source = $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items", [
            'version' => $source['version'], 'menu_item_id' => $burger->id, 'quantity' => 1,
        ])->assertCreated()->json('data');
        $itemId = $source['items'][0]['id'];

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$source['id']}/items/{$itemId}/transfer", [
            'version' => $source['version'], 'to_dining_table_id' => $otherTable->id,
            'reason' => 'Cross tenant attempt',
        ])->assertNotFound();

        $this->assertSame($source['id'], OrderItem::query()->findOrFail($itemId)->order_id);
    }

    public function test_open_order_can_reserve_and_release_multiple_dining_tables_for_one_party(): void
    {
        [$waiter, , , [$t1, $t2, $t3]] = $this->orderSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t1->id}/orders", ['guest_count' => 8])
            ->assertCreated()
            ->json('data');

        $combined = $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/merge-tables", [
                'version' => $order['version'],
                'table_ids' => [$t1->id, $t2->id, $t3->id],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame([$t1->id, $t2->id, $t3->id], $combined['merged_dining_table_ids']);
        $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$t2->id}/orders", ['guest_count' => 2])
            ->assertOk()
            ->assertJsonPath('data.id', $order['id']);

        $floorTables = collect($this->actingAs($waiter)->getJson('/api/v1/floor')->assertOk()->json('data'))
            ->flatMap(fn (array $zone) => $zone['tables'])
            ->keyBy('id');
        $this->assertSame('OPEN', $floorTables[$t1->id]['status']);
        $this->assertSame('OPEN', $floorTables[$t2->id]['status']);
        $this->assertSame('OPEN', $floorTables[$t3->id]['status']);
        $this->assertSame($order['id'], $floorTables[$t3->id]['order']['id']);

        $released = $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/release-table", [
                'version' => $combined['version'],
                'dining_table_id' => $t3->id,
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame([$t1->id, $t2->id], $released['merged_dining_table_ids']);
        $floorTables = collect($this->actingAs($waiter)->getJson('/api/v1/floor')->assertOk()->json('data'))
            ->flatMap(fn (array $zone) => $zone['tables'])
            ->keyBy('id');
        $this->assertSame('AVAILABLE', $floorTables[$t3->id]['status']);
    }

    public function test_open_order_can_be_cancelled_without_a_reason(): void
    {
        [$waiter, , , [$table]] = $this->orderSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/cancel", ['version' => $order['version']])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame('CANCELLED', Order::query()->findOrFail($order['id'])->status);
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
