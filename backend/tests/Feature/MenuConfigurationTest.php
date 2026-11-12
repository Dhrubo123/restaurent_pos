<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\ItemVariant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubcategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_subcategories_but_waiter_cannot_change_menu_configuration(): void
    {
        [$restaurant, $manager, $waiter] = $this->accessSetup();
        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Beverages',
            'default_station' => 'BAR',
            'active' => true,
        ]);

        $this->actingAs($waiter)->postJson('/api/v1/menu/subcategories', [
            'menu_category_id' => $category->id,
            'name' => 'Cold drinks',
        ])->assertForbidden();

        $subcategory = $this->actingAs($manager)->postJson('/api/v1/menu/subcategories', [
            'menu_category_id' => $category->id,
            'name' => 'Cold drinks',
            'sort_order' => 4,
        ])->assertCreated()->json('data');

        $this->assertSame(4, $subcategory['sort_order']);
        $this->actingAs($manager)->postJson("/api/v1/menu/subcategories/{$subcategory['id']}/activation")
            ->assertOk()->assertJsonPath('data.active', true);
    }

    public function test_modifier_group_rejects_impossible_selection_rules_and_preserves_choice_availability(): void
    {
        [, $manager] = $this->accessSetup();

        $this->actingAs($manager)->postJson('/api/v1/menu/modifier-groups', [
            'name' => 'Impossible choices',
            'min_selections' => 3,
            'max_selections' => 1,
            'options' => [['name' => 'Only choice', 'price' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors('min_selections');

        $group = $this->actingAs($manager)->postJson('/api/v1/menu/modifier-groups', [
            'name' => 'Choose a side',
            'required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
            'options' => [
                ['name' => 'Fries', 'price' => 40, 'available' => true],
                ['name' => 'Salad', 'price' => 20, 'available' => false],
            ],
        ])->assertCreated()->json('data');

        $this->assertTrue($group['required']);
        $this->assertFalse($group['options'][1]['available']);
    }

    public function test_server_enforces_variant_and_modifier_rules_and_keeps_price_snapshots(): void
    {
        [$restaurant, $manager, $waiter] = $this->accessSetup();
        $zone = Zone::query()->create(['restaurant_id' => $restaurant->id, 'name' => 'Indoor', 'active' => true]);
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
            'name' => 'Burgers',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);
        $subcategory = MenuSubcategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Signature',
            'active' => true,
        ]);
        $group = ModifierGroup::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Cheese',
            'required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
            'active' => true,
        ]);
        $cheese = ModifierOption::query()->create([
            'restaurant_id' => $restaurant->id,
            'modifier_group_id' => $group->id,
            'name' => 'Extra cheese',
            'price' => 50,
            'active' => true,
            'available' => true,
        ]);
        $item = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'menu_subcategory_id' => $subcategory->id,
            'sku' => 'SIG-BRG',
            'name' => 'Signature Burger',
            'station' => 'KITCHEN',
            'price' => 400,
            'active' => true,
            'available' => true,
        ]);
        $item->modifierGroups()->attach($group->id);
        $variant = ItemVariant::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_item_id' => $item->id,
            'sku' => 'SIG-BRG-L',
            'name' => 'Large',
            'price' => 600,
            'active' => true,
            'available' => true,
        ]);

        $order = $this->actingAs($waiter)->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()->json('data');

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $item->id,
            'quantity' => 1,
        ])->assertUnprocessable();

        $added = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $item->id,
            'item_variant_id' => $variant->id,
            'modifier_option_ids' => [$cheese->id],
            'quantity' => 2,
            'note' => 'Cut in half',
            'unit_price' => 1,
        ])->assertCreated()->json('data');

        $this->assertSame('600.00', $added['items'][0]['unit_price']);
        $this->assertSame('50.00', $added['items'][0]['modifier_unit_total']);
        $this->assertSame('1300.00', $added['subtotal']);
        $this->assertSame('Large', $added['items'][0]['variant_name']);
        $this->assertSame('Extra cheese', $added['items'][0]['modifiers'][0]['name']);

        $variant->update(['price' => 700]);
        $cheese->update(['price' => 90]);
        $this->assertSame('600.00', $added['items'][0]['unit_price']);
        $this->assertSame('50.00', $added['items'][0]['modifier_unit_total']);

        $sent = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/send", ['version' => $added['version']])
            ->assertCreated();
        $this->assertSame('Large', $sent->json('kots.0.items.0.variant_name'));
        $this->assertSame('Extra cheese', $sent->json('kots.0.items.0.modifiers.0.name'));
        $this->assertSame('Cut in half', $sent->json('kots.0.items.0.note'));
        $ticketResponse = $this->actingAs($waiter)->getJson('/api/v1/kots')->assertOk();
        $this->assertStringNotContainsString('600.00', $ticketResponse->getContent());
    }

    /** @return array{Restaurant, User, User} */
    private function accessSetup(): array
    {
        $restaurant = Restaurant::query()->create(['name' => 'Menu Test', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT']);
        $manager = $this->userWithPermissions($restaurant, ['manage-menu', 'manage-orders', 'view-floor']);
        $waiter = $this->userWithPermissions($restaurant, ['manage-orders', 'view-floor']);

        return [$restaurant, $manager, $waiter];
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(Restaurant $restaurant, array $permissions): User
    {
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'role-'.uniqid(), 'name' => 'Role']);
        foreach ($permissions as $permissionKey) {
            $permission = Permission::query()->firstOrCreate(['key' => $permissionKey], ['name' => $permissionKey]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
