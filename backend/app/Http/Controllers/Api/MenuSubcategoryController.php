<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuSubcategory;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuSubcategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $data = $request->validate([
            'menu_category_id' => [
                'required',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $restaurantId)->where('active', true),
            ],
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $name = trim($data['name']);
        $exists = MenuSubcategory::query()
            ->where('menu_category_id', $data['menu_category_id'])
            ->where('name', $name)
            ->exists();
        abort_if($exists, 422, 'A subcategory with this name already exists in the selected category.');

        $subcategory = MenuSubcategory::query()->create([
            'restaurant_id' => $restaurantId,
            'menu_category_id' => $data['menu_category_id'],
            'name' => $name,
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => true,
        ]);

        Audit::record($request, 'menu_subcategory.created', $subcategory, after: $subcategory->toArray());

        return response()->json(['data' => $subcategory], 201);
    }

    public function update(Request $request, MenuSubcategory $menuSubcategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuSubcategory);
        $restaurantId = $request->user()->restaurant_id;
        $data = $request->validate([
            'menu_category_id' => [
                'sometimes',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $restaurantId)->where('active', true),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        $categoryId = $data['menu_category_id'] ?? $menuSubcategory->menu_category_id;
        $name = $data['name'] ?? $menuSubcategory->name;
        $exists = MenuSubcategory::query()
            ->where('menu_category_id', $categoryId)
            ->where('name', $name)
            ->whereKeyNot($menuSubcategory->id)
            ->exists();
        abort_if($exists, 422, 'A subcategory with this name already exists in the selected category.');

        $before = $menuSubcategory->toArray();
        $menuSubcategory->update($data);
        Audit::record($request, 'menu_subcategory.updated', $menuSubcategory, $before, $menuSubcategory->fresh()->toArray());

        return response()->json(['data' => $menuSubcategory->fresh()]);
    }

    public function activate(Request $request, MenuSubcategory $menuSubcategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuSubcategory);
        abort_unless($menuSubcategory->category()->where('active', true)->exists(), 409, 'Activate the parent category first.');

        $before = $menuSubcategory->toArray();
        $menuSubcategory->update(['active' => true]);
        Audit::record($request, 'menu_subcategory.activated', $menuSubcategory, $before, $menuSubcategory->fresh()->toArray());

        return response()->json(['data' => $menuSubcategory->fresh()]);
    }

    public function deactivate(Request $request, MenuSubcategory $menuSubcategory): JsonResponse
    {
        $this->assertRestaurant($request, $menuSubcategory);
        abort_if($menuSubcategory->items()->where('active', true)->exists(), 409, 'Deactivate or move every active item before deactivating this subcategory.');

        $before = $menuSubcategory->toArray();
        $menuSubcategory->update(['active' => false]);
        Audit::record($request, 'menu_subcategory.deactivated', $menuSubcategory, $before, $menuSubcategory->fresh()->toArray());

        return response()->json(['data' => $menuSubcategory->fresh()]);
    }

    private function assertRestaurant(Request $request, MenuSubcategory $subcategory): void
    {
        abort_unless($subcategory->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
