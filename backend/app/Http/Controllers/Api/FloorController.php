<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive') && $request->user()->can('manage-floor');
        $zones = Zone::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->when(! $includeInactive, fn ($query) => $query->where('active', true))
            ->with(['tables' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $openOrders = Order::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('status', Order::OPEN_STATUSES)
            ->with('waiter:id,name')
            ->withCount(['items' => fn ($query) => $query->where('status', '!=', 'CANCELLED')])
            ->latest('opened_at')
            ->get();

        $orderForTable = [];
        foreach ($openOrders as $order) {
            $tableIds = $order->merged_dining_table_ids;
            $tableIds = is_array($tableIds) && $tableIds !== [] ? $tableIds : [$order->dining_table_id];

            foreach ($tableIds as $tableId) {
                $orderForTable[(int) $tableId] = $order;
            }
        }

        return response()->json(['data' => $zones->map(fn (Zone $zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'active' => $zone->active,
            'tables' => $zone->tables->map(function ($table) use ($orderForTable) {
                /** @var Order|null $order */
                $order = $orderForTable[$table->id] ?? null;
                $status = ! $table->active ? 'INACTIVE' : ($order?->status ?? 'AVAILABLE');
                $groupTableIds = $order?->merged_dining_table_ids;
                $groupTableIds = is_array($groupTableIds) && $groupTableIds !== [] ? array_values(array_map('intval', $groupTableIds)) : ($order ? [$order->dining_table_id] : []);

                return [
                    'id' => $table->id,
                    'code' => $table->code,
                    'display_name' => $table->display_name,
                    'capacity' => $table->capacity,
                    'active' => $table->active,
                    'status' => $status,
                    'order' => $order ? [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'guest_count' => $order->guest_count,
                        'total' => $order->total,
                        'items_count' => $order->items_count,
                        'waiter_name' => $order->waiter?->name,
                        'opened_at' => $order->opened_at,
                        'version' => $order->version,
                        'group_table_ids' => $groupTableIds,
                        'group_primary_table_id' => $order->dining_table_id,
                    ] : null,
                ];
            })->values(),
        ])->values()]);
    }
}
