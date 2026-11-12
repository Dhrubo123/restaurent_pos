<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiningTableActivationController extends Controller
{
    public function store(Request $request, DiningTable $diningTable): JsonResponse
    {
        $this->assertRestaurant($request, $diningTable);
        abort_unless($diningTable->zone()->where('active', true)->exists(), 409, 'Activate the table zone first.');
        $before = $diningTable->toArray();
        $diningTable->update(['active' => true]);
        Audit::record($request, 'table.activated', $diningTable, $before, $diningTable->fresh()->toArray());

        return response()->json(['data' => $diningTable->fresh()->load('zone')]);
    }

    public function destroy(Request $request, DiningTable $diningTable): JsonResponse
    {
        $this->assertRestaurant($request, $diningTable);

        $table = DB::transaction(function () use ($request, $diningTable): DiningTable {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($diningTable->id);
            abort_if(
                $table->orders()->whereIn('status', Order::OPEN_STATUSES)->exists(),
                409,
                'This table has an active order and cannot be deactivated.',
            );
            $before = $table->toArray();
            $table->update(['active' => false]);
            Audit::record($request, 'table.deactivated', $table, $before, $table->fresh()->toArray());

            return $table->fresh()->load('zone');
        });

        return response()->json(['data' => $table]);
    }

    private function assertRestaurant(Request $request, DiningTable $table): void
    {
        abort_unless($table->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
