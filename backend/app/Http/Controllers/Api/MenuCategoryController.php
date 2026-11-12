<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use App\Models\MenuCategory;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $data = $request->validated();

        $category = MenuCategory::query()->create([
            'default_station' => 'KITCHEN',
            'sort_order' => 0,
            ...$data,
            'restaurant_id' => $restaurantId,
            'active' => true,
        ]);

        Audit::record($request, 'menu_category.created', $category, after: $category->toArray());

        return response()->json(['data' => $category], 201);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuCategory);
        $before = $menuCategory->toArray();
        $menuCategory->update($request->validated());
        Audit::record($request, 'menu_category.updated', $menuCategory, $before, $menuCategory->fresh()->toArray());

        return response()->json(['data' => $menuCategory->fresh()]);
    }

    public function activate(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuCategory);
        $before = $menuCategory->toArray();
        $menuCategory->update(['active' => true]);
        Audit::record($request, 'menu_category.activated', $menuCategory, $before, $menuCategory->fresh()->toArray());

        return response()->json(['data' => $menuCategory->fresh()]);
    }

    public function deactivate(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuCategory);
        abort_if(
            $menuCategory->items()->where('active', true)->exists(),
            409,
            'Deactivate or move every active item before deactivating this category.',
        );

        $before = $menuCategory->toArray();
        $menuCategory->update(['active' => false]);
        Audit::record($request, 'menu_category.deactivated', $menuCategory, $before, $menuCategory->fresh()->toArray());

        return response()->json(['data' => $menuCategory->fresh()]);
    }

    private function assertRestaurant(Request $request, MenuCategory $category): void
    {
        abort_unless($category->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
