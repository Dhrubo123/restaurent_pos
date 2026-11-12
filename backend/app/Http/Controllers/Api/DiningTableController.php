<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\DiningTable;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningTableController extends Controller
{
    public function store(StoreDiningTableRequest $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $data = $request->validated();
        if (! isset($data['sort_order'])) {
            $maxSort = (int) DiningTable::query()
                ->where('restaurant_id', $restaurantId)
                ->max('sort_order');
            $data['sort_order'] = $maxSort + 1;
        }
        $table = DiningTable::query()->create([...$data, 'restaurant_id' => $restaurantId, 'active' => true]);
        Audit::record($request, 'table.created', $table, after: $table->toArray());

        return response()->json(['data' => $table->load('zone')], 201);
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $diningTable): JsonResponse
    {
        $this->assertRestaurant($request, $diningTable);
        $data = $request->validated();
        $before = $diningTable->toArray();
        $diningTable->update($data);
        Audit::record($request, 'table.updated', $diningTable, $before, $diningTable->fresh()->toArray());

        return response()->json(['data' => $diningTable->fresh()->load('zone')]);
    }

    private function assertRestaurant(Request $request, DiningTable $table): void
    {
        abort_unless($table->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
