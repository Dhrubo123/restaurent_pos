<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\PriceHistory;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_category_modifier_group_and_item_with_initial_price_history(): void
    {
        [$manager, $restaurant] = $this->menuUser(['manage-menu']);

        $catResponse = $this->actingAs($manager)->postJson('/api/v1/menu/categories', [
            'name' => 'Burgers',
            'default_station' => 'KITCHEN',
        ])->assertCreated();
        $categoryId = $catResponse->json('data.id');

        $modResponse = $this->actingAs($manager)->postJson('/api/v1/menu/modifier-groups', [
            'name' => 'Add-ons',
            'min_selections' => 0,
            'max_selections' => 2,
            'options' => [
                ['name' => 'Extra Cheese', 'price' => 60],
                ['name' => 'Beef Bacon', 'price' => 120],
            ],
        ])->assertCreated();
        $modifierGroupId = $modResponse->json('data.id');

        $itemResponse = $this->actingAs($manager)->postJson('/api/v1/menu/items', [
            'menu_category_id' => $categoryId,
            'sku' => 'brg-01',
            'name' => 'Smokehouse Burger',
            'description' => 'Chargrilled beef patty with cheddar.',
            'station' => 'KITCHEN',
            'price' => 450,
            'modifier_group_ids' => [$modifierGroupId],
            'variants' => [
                ['sku' => 'brg-01-reg', 'name' => 'Single', 'price' => 450],
                ['sku' => 'brg-01-dbl', 'name' => 'Double', 'price' => 620],
            ],
        ])->assertCreated();

        $itemId = $itemResponse->json('data.id');
        $this->assertSame('BRG-01', $itemResponse->json('data.sku'));
        $this->assertCount(2, $itemResponse->json('data.variants'));
        $this->assertCount(1, $itemResponse->json('data.modifier_groups'));

        $this->assertDatabaseHas('price_histories', [
            'restaurant_id' => $restaurant->id,
            'priceable_type' => MenuItem::class,
            'priceable_id' => $itemId,
            'amount' => '450.00',
            'changed_by' => $manager->id,
            'effective_to' => null,
        ]);
    }

    public function test_changing_item_price_requires_reason_and_records_non_overlapping_price_history(): void
    {
        [$manager, $restaurant] = $this->menuUser(['manage-menu']);
        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Burgers',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);

        $createResponse = $this->actingAs($manager)->postJson('/api/v1/menu/items', [
            'menu_category_id' => $category->id,
            'sku' => 'BRG-01',
            'name' => 'Classic Burger',
            'station' => 'KITCHEN',
            'price' => 450,
        ])->assertCreated();
        $itemId = $createResponse->json('data.id');

        $this->actingAs($manager)->patchJson("/api/v1/menu/items/{$itemId}", [
            'price' => 480,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->actingAs($manager)->patchJson("/api/v1/menu/items/{$itemId}", [
            'price' => 480,
            'reason' => 'Supplier beef cost adjustment',
        ])->assertOk()->assertJsonPath('data.price', '480.00');

        $histories = PriceHistory::query()
            ->where('priceable_type', MenuItem::class)
            ->where('priceable_id', $itemId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $histories);
        $this->assertSame('450.00', $histories[0]->amount);
        $this->assertNotNull($histories[0]->effective_to);
        $this->assertSame('480.00', $histories[1]->amount);
        $this->assertNull($histories[1]->effective_to);
        $this->assertSame('Supplier beef cost adjustment', $histories[1]->reason);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'menu_item.price_changed',
            'auditable_id' => $itemId,
            'reason' => 'Supplier beef cost adjustment',
        ]);
    }

    public function test_station_roles_only_view_and_toggle_availability_for_their_own_station(): void
    {
        [$manager, $restaurant] = $this->menuUser(['manage-menu']);
        $kitchenUser = $this->userInRestaurant($restaurant, ['manage-kitchen']);
        $barUser = $this->userInRestaurant($restaurant, ['manage-bar']);
        $waiterUser = $this->userInRestaurant($restaurant, ['view-floor', 'manage-orders']);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'All Day',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);

        $kitchenItem = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'KIT-01',
            'name' => 'Steak',
            'station' => 'KITCHEN',
            'price' => 700,
            'active' => true,
            'available' => true,
        ]);

        $barItem = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'BAR-01',
            'name' => 'Mojito',
            'station' => 'BAR',
            'price' => 220,
            'active' => true,
            'available' => true,
        ]);

        $kitchenMenu = $this->actingAs($kitchenUser)->getJson('/api/v1/menu')->assertOk();
        $this->assertStringContainsString('Steak', $kitchenMenu->getContent());
        $this->assertStringNotContainsString('Mojito', $kitchenMenu->getContent());

        $barMenu = $this->actingAs($barUser)->getJson('/api/v1/menu')->assertOk();
        $this->assertStringContainsString('Mojito', $barMenu->getContent());
        $this->assertStringNotContainsString('Steak', $barMenu->getContent());

        $waiterMenu = $this->actingAs($waiterUser)->getJson('/api/v1/menu')->assertOk();
        $this->assertStringContainsString('Steak', $waiterMenu->getContent());
        $this->assertStringContainsString('Mojito', $waiterMenu->getContent());

        $this->actingAs($kitchenUser)->postJson("/api/v1/menu/items/{$kitchenItem->id}/availability", [
            'available' => false,
        ])->assertOk()->assertJsonPath('data.available', false);

        $this->actingAs($kitchenUser)->postJson("/api/v1/menu/items/{$barItem->id}/availability", [
            'available' => false,
        ])->assertForbidden();

        $this->actingAs($waiterUser)->postJson("/api/v1/menu/items/{$kitchenItem->id}/availability", [
            'available' => true,
        ])->assertForbidden();

        $this->actingAs($manager)->postJson("/api/v1/menu/items/{$barItem->id}/availability", [
            'available' => false,
        ])->assertOk()->assertJsonPath('data.available', false);
    }

    public function test_category_with_active_items_cannot_be_deactivated(): void
    {
        [$manager, $restaurant] = $this->menuUser(['manage-menu']);
        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Starters',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);
        $item = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'STR-01',
            'name' => 'Soup',
            'station' => 'KITCHEN',
            'price' => 180,
            'active' => true,
            'available' => true,
        ]);

        $this->actingAs($manager)
            ->deleteJson("/api/v1/menu/categories/{$category->id}/activation")
            ->assertConflict();

        $this->actingAs($manager)
            ->deleteJson("/api/v1/menu/items/{$item->id}/activation")
            ->assertOk();

        $this->actingAs($manager)
            ->deleteJson("/api/v1/menu/categories/{$category->id}/activation")
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function menuUser(array $permissions): array
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
        ]);

        return [$this->userInRestaurant($restaurant, $permissions), $restaurant];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userInRestaurant(Restaurant $restaurant, array $permissions): User
    {
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'role-'.uniqid(), 'name' => 'Test role']);

        foreach ($permissions as $permKey) {
            $perm = Permission::query()->firstOrCreate(['key' => $permKey], ['name' => $permKey]);
            $role->permissions()->attach($perm);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
