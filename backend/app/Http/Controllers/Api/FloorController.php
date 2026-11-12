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
                ->with(['orders' => fn ($orders) => $orders
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->with('waiter:id,name')
                    ->withCount(['items' => fn ($iq) => $iq->where('status', '!=', 'CANCELLED')])
                    ->latest('opened_at')])
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $zones->map(fn (Zone $zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'active' => $zone->active,
            'tables' => $zone->tables->map(function ($table) {
                $order = $table->orders->first();
                $status = ! $table->active ? 'INACTIVE' : ($order?->status ?? 'AVAILABLE');

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
                    ] : null,
                ];
            })->values(),
        ])->values()]);
    }
}
