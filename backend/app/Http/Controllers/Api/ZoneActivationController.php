<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneActivationController extends Controller
{
    public function store(Request $request, Zone $zone): JsonResponse
    {
        $this->assertRestaurant($request, $zone);
        $before = $zone->toArray();
        $zone->update(['active' => true]);
        Audit::record($request, 'zone.activated', $zone, $before, $zone->fresh()->toArray());

        return response()->json(['data' => $zone->fresh()]);
    }

    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        $this->assertRestaurant($request, $zone);
        abort_if(
            $zone->tables()->where('active', true)->exists(),
            409,
            'Deactivate or move every active table before deactivating this zone.',
        );
        $before = $zone->toArray();
        $zone->update(['active' => false]);
        Audit::record($request, 'zone.deactivated', $zone, $before, $zone->fresh()->toArray());

        return response()->json(['data' => $zone->fresh()]);
    }

    private function assertRestaurant(Request $request, Zone $zone): void
    {
        abort_unless($zone->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
