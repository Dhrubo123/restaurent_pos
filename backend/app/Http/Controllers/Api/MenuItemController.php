<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\TaxCategory;
use App\Support\Audit;
use App\Support\PriceLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $data = $request->validated();

        $item = DB::transaction(function () use ($request, $user, $restaurantId, $data) {
            $item = MenuItem::query()->create([
                'restaurant_id' => $restaurantId,
                'menu_category_id' => $data['menu_category_id'],
                'menu_subcategory_id' => $data['menu_subcategory_id'] ?? null,
                'tax_category_id' => $data['tax_category_id'] ?? null,
                'sku' => $data['sku'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'station' => $data['station'],
                'price' => $data['price'],
                'sort_order' => $data['sort_order'] ?? 0,
                'active' => true,
                'available' => $data['available'] ?? true,
            ]);

            PriceLedger::recordInitial($item, $restaurantId, $item->price, $user->id);

            if (! empty($data['modifier_group_ids'])) {
                $item->modifierGroups()->sync($data['modifier_group_ids']);
            }

            foreach ($data['variants'] ?? [] as $index => $variantData) {
                $variant = ItemVariant::query()->create([
                    'restaurant_id' => $restaurantId,
                    'menu_item_id' => $item->id,
                    'sku' => Str::upper(trim($variantData['sku'])),
                    'name' => trim($variantData['name']),
                    'price' => $variantData['price'],
                    'sort_order' => $index + 1,
                    'active' => $variantData['active'] ?? true,
                    'available' => $variantData['available'] ?? true,
                ]);
                PriceLedger::recordInitial($variant, $restaurantId, $variant->price, $user->id);
            }

            Audit::record($request, 'menu_item.created', $item, after: $item->toArray());

            return $item;
        });

        return response()->json([
            'data' => $item->load(['category', 'subcategory', 'taxCategory', 'variants', 'modifierGroups.options', 'priceHistories.actor:id,name']),
        ], 201);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->assertRestaurant($request, $menuItem);
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($request, $user, $menuItem, $data) {
            $before = $menuItem->toArray();
            $oldPrice = (string) $menuItem->price;
            $newPrice = array_key_exists('price', $data)
                ? number_format((float) $data['price'], 2, '.', '')
                : $oldPrice;
            $priceChanged = bccomp($newPrice, $oldPrice, 2) !== 0;

            $reason = $data['reason'] ?? null;
            $modifierGroupIds = $data['modifier_group_ids'] ?? null;
            $variants = $data['variants'] ?? null;
            unset($data['reason'], $data['modifier_group_ids'], $data['variants']);

            $menuItem->update($data);

            if (is_array($modifierGroupIds)) {
                $menuItem->modifierGroups()->sync($modifierGroupIds);
            }

            if (is_array($variants)) {
                $this->syncVariants($request, $menuItem, $variants);
            }

            if ($priceChanged) {
                PriceLedger::recordChange($menuItem, $user->restaurant_id, $newPrice, $user->id, (string) $reason);
                Audit::record(
                    $request,
                    'menu_item.price_changed',
                    $menuItem,
                    ['price' => $oldPrice],
                    ['price' => $newPrice],
                    (string) $reason,
                );
            } else {
                Audit::record($request, 'menu_item.updated', $menuItem, $before, $menuItem->fresh()->toArray());
            }
        });

        return response()->json([
            'data' => $menuItem->fresh()->load(['category', 'subcategory', 'taxCategory', 'variants', 'modifierGroups.options', 'priceHistories.actor:id,name']),
        ]);
    }

    public function updateAvailability(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->assertRestaurant($request, $menuItem);
        $user = $request->user();

        $canToggle = $user->can('manage-menu')
            || ($menuItem->station === 'KITCHEN' && $user->can('manage-kitchen'))
            || ($menuItem->station === 'BAR' && $user->can('manage-bar'));

        abort_unless($canToggle, 403);

        $validated = $request->validate([
            'available' => ['required', 'boolean'],
        ]);

        $before = ['available' => $menuItem->available];
        $menuItem->update(['available' => $validated['available']]);
        Audit::record($request, 'menu_item.availability_changed', $menuItem, $before, ['available' => $menuItem->available]);

        return response()->json(['data' => $menuItem->fresh()]);
    }

    public function activate(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->assertRestaurant($request, $menuItem);
        $before = $menuItem->toArray();
        $menuItem->update(['active' => true]);
        Audit::record($request, 'menu_item.activated', $menuItem, $before, $menuItem->fresh()->toArray());

        return response()->json(['data' => $menuItem->fresh()]);
    }

    public function deactivate(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->assertRestaurant($request, $menuItem);
        $before = $menuItem->toArray();
        $menuItem->update(['active' => false]);
        Audit::record($request, 'menu_item.deactivated', $menuItem, $before, $menuItem->fresh()->toArray());

        return response()->json(['data' => $menuItem->fresh()]);
    }

    public function storeTaxCategory(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'inclusive' => ['required', 'boolean'],
        ]);

        $tax = TaxCategory::query()->updateOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => trim($validated['name'])],
            [
                'rate' => $validated['rate'],
                'inclusive' => $validated['inclusive'],
                'active' => true,
                'effective_from' => now(),
            ],
        );

        Audit::record($request, 'tax_category.saved', $tax, after: $tax->toArray());

        return response()->json(['data' => $tax], 201);
    }

    public function applyBulkTaxCategory(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $validated = $request->validate([
            'tax_category_id' => ['nullable', 'integer'],
        ]);

        $taxCategoryId = $validated['tax_category_id'] ?? null;
        if ($taxCategoryId !== null) {
            TaxCategory::query()
                ->where('restaurant_id', $restaurantId)
                ->where('active', true)
                ->findOrFail($taxCategoryId);
        }

        $count = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->update(['tax_category_id' => $taxCategoryId]);

        return response()->json([
            'data' => [
                'updated_count' => $count,
                'tax_category_id' => $taxCategoryId,
            ],
        ]);
    }

    private function assertRestaurant(Request $request, MenuItem $item): void
    {
        abort_unless($item->restaurant_id === $request->user()->restaurant_id, 404);
    }

    /**
     * Variant prices replace the menu item's base price; the base price is used only when no variant is selected.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Request $request, MenuItem $menuItem, array $variants): void
    {
        $restaurantId = $request->user()->restaurant_id;
        $seenIds = [];

        foreach ($variants as $index => $variantData) {
            $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;
            $sku = Str::upper(trim((string) $variantData['sku']));
            $name = trim((string) $variantData['name']);

            $duplicateSku = ItemVariant::query()
                ->where('restaurant_id', $restaurantId)
                ->where('sku', $sku)
                ->when($variantId, fn ($query) => $query->whereKeyNot($variantId))
                ->exists();
            abort_if($duplicateSku, 422, "Variant SKU {$sku} is already in use.");

            if ($variantId) {
                $variant = ItemVariant::query()
                    ->where('restaurant_id', $restaurantId)
                    ->where('menu_item_id', $menuItem->id)
                    ->findOrFail($variantId);

                $oldPrice = (string) $variant->price;
                $newPrice = number_format((float) $variantData['price'], 2, '.', '');
                $variant->update([
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $newPrice,
                    'sort_order' => $variantData['sort_order'] ?? ($index + 1),
                    'active' => $variantData['active'] ?? $variant->active,
                    'available' => $variantData['available'] ?? $variant->available,
                ]);

                if (bccomp($oldPrice, $newPrice, 2) !== 0) {
                    PriceLedger::recordChange($variant, $restaurantId, $newPrice, $request->user()->id, 'Menu configuration update');
                }
            } else {
                $variant = ItemVariant::query()->create([
                    'restaurant_id' => $restaurantId,
                    'menu_item_id' => $menuItem->id,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $variantData['price'],
                    'sort_order' => $variantData['sort_order'] ?? ($index + 1),
                    'active' => $variantData['active'] ?? true,
                    'available' => $variantData['available'] ?? true,
                ]);
                PriceLedger::recordInitial($variant, $restaurantId, $variant->price, $request->user()->id);
            }

            $seenIds[] = $variant->id;
        }

        $menuItem->variants()->whereNotIn('id', $seenIds)->update(['active' => false, 'available' => false]);
    }
}
