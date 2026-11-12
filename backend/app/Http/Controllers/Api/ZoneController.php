<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Models\Zone;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-floor');

        return response()->json(['data' => Zone::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->withCount('tables')
            ->orderBy('sort_order')
            ->get()]);
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $data = $request->validated();

        $zone = Zone::query()->create([...$data, 'restaurant_id' => $restaurantId, 'active' => true]);
        Audit::record($request, 'zone.created', $zone, after: $zone->toArray());

        return response()->json(['data' => $zone], 201);
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $this->assertRestaurant($request, $zone);
        $data = $request->validated();
        $before = $zone->toArray();
        $zone->update($data);
        Audit::record($request, 'zone.updated', $zone, $before, $zone->fresh()->toArray());

        return response()->json(['data' => $zone->fresh()]);
    }

    private function assertRestaurant(Request $request, Zone $zone): void
    {
        abort_unless($zone->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
