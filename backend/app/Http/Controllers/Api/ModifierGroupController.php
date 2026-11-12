<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Support\Audit;
use App\Support\PriceLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierGroupController extends Controller
{
    public function store(StoreModifierGroupRequest $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $data = $request->validated();

        $group = DB::transaction(function () use ($request, $user, $restaurantId, $data) {
            $group = ModifierGroup::query()->create([
                'restaurant_id' => $restaurantId,
                'name' => $data['name'],
                'min_selections' => $data['min_selections'] ?? 0,
                'max_selections' => $data['max_selections'] ?? 1,
                'required' => $data['required'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'active' => true,
            ]);

            foreach ($data['options'] ?? [] as $index => $optionData) {
                $option = ModifierOption::query()->create([
                    'restaurant_id' => $restaurantId,
                    'modifier_group_id' => $group->id,
                    'name' => trim($optionData['name']),
                    'price' => $optionData['price'],
                    'sort_order' => $index + 1,
                    'active' => $optionData['active'] ?? true,
                    'available' => $optionData['available'] ?? true,
                ]);
                PriceLedger::recordInitial($option, $restaurantId, $option->price, $user->id);
            }

            Audit::record($request, 'modifier_group.created', $group, after: $group->load('options')->toArray());

            return $group;
        });

        return response()->json(['data' => $group->load('options')], 201);
    }

    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): JsonResponse
    {
        abort_unless($modifierGroup->restaurant_id === $request->user()->restaurant_id, 404);
        $data = $request->validated();
        $before = $modifierGroup->load('options')->toArray();

        DB::transaction(function () use ($request, $modifierGroup, $data): void {
            $options = $data['options'] ?? null;
            unset($data['options']);
            $modifierGroup->update($data);

            if (is_array($options)) {
                $this->syncOptions($request, $modifierGroup, $options);
            }
        });

        Audit::record($request, 'modifier_group.updated', $modifierGroup, $before, $modifierGroup->fresh()->load('options')->toArray());

        return response()->json(['data' => $modifierGroup->fresh()->load('options')]);
    }

    public function activate(Request $request, ModifierGroup $modifierGroup): JsonResponse
    {
        abort_unless($modifierGroup->restaurant_id === $request->user()->restaurant_id, 404);
        $before = $modifierGroup->toArray();
        $modifierGroup->update(['active' => true]);
        Audit::record($request, 'modifier_group.activated', $modifierGroup, $before, $modifierGroup->fresh()->toArray());

        return response()->json(['data' => $modifierGroup->fresh()->load('options')]);
    }

    public function deactivate(Request $request, ModifierGroup $modifierGroup): JsonResponse
    {
        abort_unless($modifierGroup->restaurant_id === $request->user()->restaurant_id, 404);
        $before = $modifierGroup->toArray();
        $modifierGroup->update(['active' => false]);
        Audit::record($request, 'modifier_group.deactivated', $modifierGroup, $before, $modifierGroup->fresh()->toArray());

        return response()->json(['data' => $modifierGroup->fresh()->load('options')]);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function syncOptions(Request $request, ModifierGroup $group, array $options): void
    {
        $seenIds = [];
        foreach ($options as $index => $optionData) {
            $optionId = isset($optionData['id']) ? (int) $optionData['id'] : null;
            $attributes = [
                'name' => trim((string) $optionData['name']),
                'price' => $optionData['price'],
                'sort_order' => $optionData['sort_order'] ?? ($index + 1),
                'active' => $optionData['active'] ?? true,
                'available' => $optionData['available'] ?? true,
            ];

            if ($optionId) {
                $option = ModifierOption::query()
                    ->where('restaurant_id', $request->user()->restaurant_id)
                    ->where('modifier_group_id', $group->id)
                    ->findOrFail($optionId);
                $oldPrice = (string) $option->price;
                $option->update($attributes);
                $newPrice = number_format((float) $option->price, 2, '.', '');
                if (bccomp($oldPrice, $newPrice, 2) !== 0) {
                    PriceLedger::recordChange($option, $group->restaurant_id, $newPrice, $request->user()->id, 'Menu configuration update');
                }
            } else {
                $option = ModifierOption::query()->create([
                    'restaurant_id' => $group->restaurant_id,
                    'modifier_group_id' => $group->id,
                    ...$attributes,
                ]);
                PriceLedger::recordInitial($option, $group->restaurant_id, $option->price, $request->user()->id);
            }

            $seenIds[] = $option->id;
        }

        $group->options()->whereNotIn('id', $seenIds)->update(['active' => false, 'available' => false]);
    }
}
