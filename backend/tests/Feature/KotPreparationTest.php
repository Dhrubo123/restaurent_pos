<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Kot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KotPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_station_round_splits_into_kitchen_and_bar_kots_and_later_round_sends_only_new_items(): void
    {
        [$waiter, , , $table, $burger, $cola, $tea, $cheese] = $this->kotSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 3])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 2,
            'modifier_option_ids' => [$cheese->id],
            'note' => 'No onions',
        ])->assertCreated()->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $cola->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $send1 = $this->actingAs($waiter)
            ->withHeader('Idempotency-Key', 'send-round-1')
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $order['version']])
            ->assertCreated();

        $repeatSend1 = $this->actingAs($waiter)
            ->withHeader('Idempotency-Key', 'send-round-1')
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $order['version']])
            ->assertCreated();

        $this->assertEquals($send1->json('kots'), $repeatSend1->json('kots'));
        $this->assertCount(2, $send1->json('kots'));
        $this->assertSame('KITCHEN', $send1->json('kots.0.station'));
        $this->assertSame('KOT-0001', $send1->json('kots.0.kot_number'));
        $this->assertCount(1, $send1->json('kots.0.items'));
        $this->assertSame('Smokehouse Burger', $send1->json('kots.0.items.0.item_name'));
        $this->assertSame('BAR', $send1->json('kots.1.station'));
        $this->assertSame('KOT-0002', $send1->json('kots.1.kot_number'));
        $this->assertCount(1, $send1->json('kots.1.items'));
        $this->assertSame('Cola', $send1->json('kots.1.items.0.item_name'));

        $updatedOrder = $send1->json('data');

        $updatedOrder = $this->actingAs($waiter)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/orders/{$order['id']}/items", [
                'version' => $updatedOrder['version'],
                'menu_item_id' => $tea->id,
                'quantity' => 2,
            ])->assertCreated()->json('data');

        $send2 = $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $updatedOrder['version']])
            ->assertCreated();

        $this->assertCount(1, $send2->json('kots'));
        $this->assertSame('BAR', $send2->json('kots.0.station'));
        $this->assertSame(2, $send2->json('kots.0.round_number'));
        $this->assertSame('KOT-0003', $send2->json('kots.0.kot_number'));
        $this->assertCount(1, $send2->json('kots.0.items'));
        $this->assertSame('Masala Tea', $send2->json('kots.0.items.0.item_name'));
        $this->assertSame(3, Kot::query()->count());
    }

    public function test_kitchen_and_bar_station_isolation_price_free_kots_and_status_transitions(): void
    {
        [$waiter, , $restaurant, $table, $burger, $cola] = $this->kotSetup();
        $kitchenUser = $this->userWithPermissions($restaurant, ['manage-kitchen']);
        $barUser = $this->userWithPermissions($restaurant, ['manage-bar']);

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $cola->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $sent = $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $order['version']])
            ->assertCreated();

        $kitchenKotId = $sent->json('kots.0.id');
        $barKotId = $sent->json('kots.1.id');

        $kitchenQueue = $this->actingAs($kitchenUser)->getJson('/api/v1/kots')->assertOk();
        $this->assertCount(1, $kitchenQueue->json('data'));
        $this->assertSame('KITCHEN', $kitchenQueue->json('data.0.station'));
        $this->assertStringNotContainsString('unit_price', $kitchenQueue->getContent());
        $this->assertStringNotContainsString('450.00', $kitchenQueue->getContent());

        $this->actingAs($kitchenUser)->getJson('/api/v1/kots?station=BAR')->assertForbidden();
        $this->actingAs($barUser)->postJson("/api/v1/kots/{$kitchenKotId}/start")->assertForbidden();

        $this->actingAs($kitchenUser)->postJson("/api/v1/kots/{$kitchenKotId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'PREPARING');

        $readyResponse = $this->actingAs($kitchenUser)->postJson("/api/v1/kots/{$kitchenKotId}/ready")
            ->assertOk()
            ->assertJsonPath('data.status', 'READY');

        $kotItemId = $readyResponse->json('data.items.0.id');

        $this->actingAs($waiter)->postJson("/api/v1/kot-items/{$kotItemId}/served")
            ->assertOk()
            ->assertJsonPath('data.status', 'SERVED');

        $this->actingAs($barUser)->postJson("/api/v1/kots/{$barKotId}/reprint", [
            'reason' => 'Paper roll replaced at bar printer',
        ])->assertOk()->assertJsonPath('data.reprint_count', 1);
    }

    public function test_sent_item_cannot_be_deleted_by_waiter_but_manager_can_cancel_with_reason_and_adjust_bill(): void
    {
        [$waiter, $manager, , $table, $burger, $cola] = $this->kotSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $burger->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $cola->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $sentOrder = $this->actingAs($waiter)
            ->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $order['version']])
            ->assertCreated()
            ->json('data');

        $burgerLineId = $sentOrder['items'][0]['id'];

        $this->actingAs($waiter)->deleteJson("/api/v1/orders/{$order['id']}/items/{$burgerLineId}", [
            'version' => $sentOrder['version'],
        ])->assertConflict();

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items/{$burgerLineId}/cancel", [
            'version' => $sentOrder['version'],
            'reason' => 'Guest changed mind',
        ])->assertForbidden();

        $afterCancel = $this->actingAs($manager)->postJson("/api/v1/orders/{$order['id']}/items/{$burgerLineId}/cancel", [
            'version' => $sentOrder['version'],
            'reason' => 'Guest changed mind',
        ])->assertOk()->json('data');

        $this->assertSame('CANCELLED', $afterCancel['items'][0]['status']);
        $this->assertSame('120.00', $afterCancel['subtotal']);
        $this->assertSame('CANCELLED', $afterCancel['kots'][1]['status'] === 'CANCELLED' ? $afterCancel['kots'][1]['status'] : $afterCancel['kots'][0]['status']);
    }

    private function kotSetup(): array
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
        $table = DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T1',
            'display_name' => 'Table 1',
            'capacity' => 4,
            'active' => true,
        ]);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Menu',
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
            'sku' => 'BRG-01',
            'name' => 'Smokehouse Burger',
            'station' => 'KITCHEN',
            'price' => 450,
            'active' => true,
            'available' => true,
        ]);
        $burger->modifierGroups()->attach($group->id);

        $cola = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'BEV-01',
            'name' => 'Cola',
            'station' => 'BAR',
            'price' => 120,
            'active' => true,
            'available' => true,
        ]);

        $tea = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'BEV-02',
            'name' => 'Masala Tea',
            'station' => 'BAR',
            'price' => 90,
            'active' => true,
            'available' => true,
        ]);

        $waiter = $this->userWithPermissions($restaurant, ['view-floor', 'manage-orders']);
        $manager = $this->userWithPermissions($restaurant, ['view-floor', 'manage-floor', 'manage-menu', 'manage-orders']);

        return [$waiter, $manager, $restaurant, $table, $burger, $cola, $tea, $cheese];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(Restaurant $restaurant, array $permissions): User
    {
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'role-'.uniqid(), 'name' => 'Role']);

        foreach ($permissions as $permKey) {
            $perm = Permission::query()->firstOrCreate(['key' => $permKey], ['name' => $permKey]);
            $role->permissions()->attach($perm);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
