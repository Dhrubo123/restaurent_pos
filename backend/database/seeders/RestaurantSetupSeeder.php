<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Permission;
use App\Models\PriceHistory;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TaxCategory;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class RestaurantSetupSeeder extends Seeder
{
    public function run(): void
    {
        $restaurantName = env('RESTAURANT_NAME', 'Clique Cafe');
        $restaurant = Restaurant::query()->oldest('id')->first();

        if ($restaurant) {
            $restaurant->update(['name' => $restaurantName]);
        } else {
            $restaurant = Restaurant::query()->create([
                'name' => $restaurantName,
                'timezone' => 'Asia/Dhaka',
                'currency_code' => 'BDT',
                'active' => true,
            ]);
        }

        $permissionNames = [
            'view-floor' => 'View floor and table status',
            'manage-floor' => 'Manage zones and tables',
            'manage-menu' => 'Manage menu and pricing',
            'manage-users' => 'Manage users and roles',
            'manage-security' => 'Manage security-sensitive access controls',
            'manage-settings' => 'Manage restaurant settings',
            'manage-orders' => 'Create and update orders',
            'manage-kitchen' => 'Update Kitchen tickets',
            'manage-bar' => 'Update Bar tickets',
            'take-payment' => 'Take payments and finalize invoices',
            'refund-payment' => 'Void and refund payments',
            'view-reports' => 'View financial reports',
        ];
        $permissions = collect($permissionNames)->mapWithKeys(fn ($name, $key) => [
            $key => Permission::query()->firstOrCreate(['key' => $key], ['name' => $name]),
        ]);

        $roleMap = [
            'admin' => array_keys($permissionNames),
            'manager' => array_values(array_diff(array_keys($permissionNames), ['manage-users', 'manage-security'])),
            'waiter' => ['view-floor', 'manage-orders'],
            'cashier' => ['view-floor', 'manage-orders', 'take-payment'],
            'kitchen' => ['manage-kitchen'],
            'bar' => ['manage-bar'],
        ];
        collect($roleMap)->each(function ($permissionKeys, $key) use ($permissions): void {
            $role = Role::query()->firstOrCreate(['key' => $key], ['name' => ucfirst($key)]);
            $role->permissions()->sync(collect($permissionKeys)->map(fn ($permission) => $permissions[$permission]->id));
        });

        $indoor = Zone::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Indoor'],
            ['sort_order' => 1, 'active' => true],
        );
        $outdoor = Zone::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Outdoor'],
            ['sort_order' => 2, 'active' => true],
        );

        foreach (range(1, 15) as $number) {
            DiningTable::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'T'.$number],
                [
                    'zone_id' => $number <= 10 ? $indoor->id : $outdoor->id,
                    'display_name' => 'Table '.$number,
                    'capacity' => $number % 5 === 0 ? 6 : 4,
                    'sort_order' => $number,
                    'active' => true,
                ],
            );
        }

        DiningTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('code', 'like', 'QA%')
            ->whereDoesntHave('orders')
            ->delete();

        $vat = TaxCategory::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Standard VAT 5%'],
            ['rate' => 5.000, 'inclusive' => false, 'active' => true, 'effective_from' => now()],
        );
        TaxCategory::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'VAT Included in Food Price (5%)'],
            ['rate' => 5.000, 'inclusive' => true, 'active' => true, 'effective_from' => now()],
        );

        $addOns = ModifierGroup::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Burger Add-ons'],
            ['min_selections' => 0, 'max_selections' => 3, 'required' => false, 'sort_order' => 1, 'active' => true],
        );
        foreach ([['Extra Cheese', 60], ['Beef Bacon', 120], ['Jalapeños', 40]] as $idx => [$optName, $optPrice]) {
            ModifierOption::query()->firstOrCreate(
                ['modifier_group_id' => $addOns->id, 'name' => $optName],
                ['restaurant_id' => $restaurant->id, 'price' => $optPrice, 'sort_order' => $idx + 1, 'active' => true],
            );
        }

        $categories = [
            'Mains & Burgers' => ['station' => 'KITCHEN', 'sort' => 1, 'items' => [
                ['sku' => 'BRG-01', 'name' => 'Smokehouse Beef Burger', 'price' => 450, 'desc' => 'Chargrilled patty, cheddar, house smoky mayo, brioche bun.', 'image' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=640&q=80', 'mods' => [$addOns->id]],
                ['sku' => 'BRG-02', 'name' => 'Crispy Chicken Burger', 'price' => 390, 'desc' => 'Buttermilk fried thigh, spicy slaw, pickles.', 'image' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?auto=format&fit=crop&w=640&q=80', 'mods' => [$addOns->id]],
                ['sku' => 'MNS-01', 'name' => 'Grilled Herb Chicken Steak', 'price' => 620, 'desc' => 'Served with mashed potatoes and sautéed vegetables.', 'image' => 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=640&q=80', 'mods' => []],
            ]],
            'Starters & Sides' => ['station' => 'KITCHEN', 'sort' => 2, 'items' => [
                ['sku' => 'STR-01', 'name' => 'Peri-Peri Chicken Wings (6 pcs)', 'price' => 320, 'desc' => 'Flame-grilled wings tossed in house peri-peri glaze.', 'image' => 'https://images.unsplash.com/photo-1527477396000-e27163b481c2?auto=format&fit=crop&w=640&q=80', 'mods' => []],
                ['sku' => 'STR-02', 'name' => 'Truffle Parmesan Fries', 'price' => 240, 'desc' => 'Hand-cut fries with grated parmesan and herb oil.', 'image' => 'https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=640&q=80', 'mods' => []],
            ]],
            'Beverages & Bar' => ['station' => 'BAR', 'sort' => 3, 'items' => [
                ['sku' => 'BEV-01', 'name' => 'Fresh Lime Soda', 'price' => 140, 'desc' => 'Sweet, salted, or mixed fresh lime sparkler.', 'image' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=640&q=80', 'mods' => []],
                ['sku' => 'BEV-02', 'name' => 'Iced Hazelnut Latte', 'price' => 260, 'desc' => 'Double-shot espresso over ice with roasted hazelnut.', 'image' => 'https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?auto=format&fit=crop&w=640&q=80', 'mods' => []],
                ['sku' => 'BEV-03', 'name' => 'Artisanal Cola', 'price' => 120, 'desc' => 'Chilled 330ml glass bottle.', 'image' => 'https://images.unsplash.com/photo-1554866585-cd94860890b7?auto=format&fit=crop&w=640&q=80', 'mods' => []],
            ]],
        ];

        foreach ($categories as $categoryName => $cfg) {
            $category = MenuCategory::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $categoryName],
                ['default_station' => $cfg['station'], 'sort_order' => $cfg['sort'], 'active' => true],
            );

            foreach ($cfg['items'] as $index => $itemData) {
                $item = MenuItem::query()->firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'sku' => $itemData['sku']],
                    [
                        'menu_category_id' => $category->id,
                        'tax_category_id' => $vat->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['desc'],
                        'image_path' => $itemData['image'],
                        'station' => $cfg['station'],
                        'price' => $itemData['price'],
                        'sort_order' => $index + 1,
                        'active' => true,
                        'available' => true,
                    ],
                );

                if (! $item->image_path && ! empty($itemData['image'])) {
                    $item->update(['image_path' => $itemData['image']]);
                }

                if (! empty($itemData['mods'])) {
                    $item->modifierGroups()->syncWithoutDetaching($itemData['mods']);
                }

                PriceHistory::query()->firstOrCreate(
                    ['priceable_type' => MenuItem::class, 'priceable_id' => $item->id, 'effective_to' => null],
                    [
                        'restaurant_id' => $restaurant->id,
                        'amount' => $item->price,
                        'effective_from' => now(),
                        'reason' => 'Initial seed price',
                    ],
                );
            }
        }

        $suppliers = [
            ['name' => 'Dhaka Prime Meats & Poultry', 'contact' => 'Karim Uddin', 'phone' => '01711-000111'],
            ['name' => 'artisan Bakery & Buns Co.', 'contact' => 'Nadia Rahman', 'phone' => '01819-222333'],
            ['name' => 'Metro Beverage Distributors', 'contact' => 'Tanvir Ahmed', 'phone' => '01911-444555'],
        ];
        foreach ($suppliers as $sup) {
            Supplier::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $sup['name']],
                ['contact_person' => $sup['contact'], 'phone' => $sup['phone'], 'active' => true],
            );
        }

        $stockSeed = [
            ['sku' => 'ING-BEEF-PATTY', 'name' => 'Beef Burger Patty 150g', 'cat' => 'Meat & Poultry', 'unit' => 'pcs', 'stock' => 40, 'reorder' => 10, 'cost' => 135],
            ['sku' => 'ING-CHICKEN-THIGH', 'name' => 'Crispy Chicken Thigh Fillet', 'cat' => 'Meat & Poultry', 'unit' => 'pcs', 'stock' => 35, 'reorder' => 10, 'cost' => 95],
            ['sku' => 'ING-BRIOCHE-BUN', 'name' => 'Artisan Brioche Burger Bun', 'cat' => 'Bakery', 'unit' => 'pcs', 'stock' => 60, 'reorder' => 15, 'cost' => 28],
            ['sku' => 'ING-CHEDDAR-SLICE', 'name' => 'Aged Cheddar Slice', 'cat' => 'Dairy', 'unit' => 'pcs', 'stock' => 80, 'reorder' => 20, 'cost' => 22],
            ['sku' => 'ING-POTATO-FRIES', 'name' => 'Hand-Cut Fries Portion', 'cat' => 'Produce', 'unit' => 'kg', 'stock' => 18, 'reorder' => 5, 'cost' => 160],
            ['sku' => 'ING-COLA-BTL', 'name' => 'Artisanal Cola 330ml Bottle', 'cat' => 'Beverages', 'unit' => 'btl', 'stock' => 48, 'reorder' => 12, 'cost' => 45],
        ];

        $invBySku = [];
        foreach ($stockSeed as $s) {
            $invBySku[$s['sku']] = InventoryItem::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => $s['sku']],
                [
                    'name' => $s['name'],
                    'category' => $s['cat'],
                    'unit' => $s['unit'],
                    'current_stock' => $s['stock'],
                    'reorder_level' => $s['reorder'],
                    'unit_cost' => $s['cost'],
                    'auto_eighty_six' => true,
                    'active' => true,
                ],
            );
        }

        $recipeMap = [
            'BRG-01' => [['ING-BEEF-PATTY', 1], ['ING-BRIOCHE-BUN', 1], ['ING-CHEDDAR-SLICE', 1]],
            'BRG-02' => [['ING-CHICKEN-THIGH', 1], ['ING-BRIOCHE-BUN', 1]],
            'STR-02' => [['ING-POTATO-FRIES', 0.250]],
            'BEV-03' => [['ING-COLA-BTL', 1]],
        ];

        foreach ($recipeMap as $menuSku => $ingPairs) {
            $menuItem = MenuItem::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('sku', $menuSku)
                ->first();
            if (! $menuItem) {
                continue;
            }
            foreach ($ingPairs as [$ingSku, $qty]) {
                $invItem = $invBySku[$ingSku] ?? null;
                if ($invItem) {
                    MenuItemIngredient::query()->firstOrCreate(
                        ['menu_item_id' => $menuItem->id, 'inventory_item_id' => $invItem->id],
                        ['restaurant_id' => $restaurant->id, 'quantity' => $qty],
                    );
                }
            }
        }
    }
}
