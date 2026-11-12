<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kot;
use App\Models\KotItem;
use App\Models\KotStatusEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRound;
use App\Models\User;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\InventoryStock;
use App\Support\KotPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class KotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $allowedStations = $this->allowedViewStations($user);
        abort_if($allowedStations === [], 403);

        $requestedStation = Str::upper(trim((string) $request->query('station', '')));
        if ($requestedStation !== '') {
            abort_unless(in_array($requestedStation, $allowedStations, true), 403);
            $stations = [$requestedStation];
        } else {
            $stations = $allowedStations;
        }

        $includeCompleted = $request->boolean('include_completed');

        $kots = Kot::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('station', $stations)
            ->when(! $includeCompleted, fn ($q) => $q->whereIn('status', Kot::ACTIVE_STATUSES))
            ->with([
                'order.table.zone:id,name',
                'order.waiter:id,name',
                'order.restaurant:id,name',
                'round.sender:id,name',
                'items',
            ])
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 1 WHEN 'PREPARING' THEN 2 WHEN 'READY' THEN 3 ELSE 4 END")
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $kots->map(fn (Kot $kot) => KotPresenter::toArray($kot))->values(),
            'meta' => [
                'allowed_stations' => $allowedStations,
            ],
        ]);
    }

    public function sendRound(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return Idempotency::handle($request, "orders.{$order->id}.send", function () use ($request, $order, $validated) {
            return DB::transaction(function () use ($request, $order, $validated) {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                abort_unless(
                    in_array($lockedOrder->status, Order::OPEN_STATUSES, true),
                    409,
                    "Order {$lockedOrder->order_number} is no longer open.",
                );

                abort_if(
                    $lockedOrder->version !== (int) $validated['version'],
                    409,
                    "Order {$lockedOrder->order_number} was changed by another user (current version {$lockedOrder->version}). Reload and review before sending.",
                );

                $items = $lockedOrder->items()
                    ->where('status', '!=', 'CANCELLED')
                    ->with('modifiers')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                $unsentItems = $items->filter(function (OrderItem $item): bool {
                    $unsentQty = (float) $item->quantity - (float) $item->cancelled_quantity - (float) $item->sent_quantity;

                    return round($unsentQty, 3) > 0;
                });

                abort_if(
                    $unsentItems->isEmpty(),
                    409,
                    'There are no unsent draft items to send to the kitchen or bar.',
                );

                $user = $request->user();
                $restaurantId = $user->restaurant_id;
                $nextRoundNumber = ((int) $lockedOrder->rounds()->max('round_number')) + 1;
                $now = now();

                $round = OrderRound::query()->create([
                    'restaurant_id' => $restaurantId,
                    'order_id' => $lockedOrder->id,
                    'round_number' => $nextRoundNumber,
                    'sent_by' => $user->id,
                    'sent_at' => $now,
                ]);

                $groupedByStation = $unsentItems->groupBy('station');
                $createdKots = [];

                foreach (['KITCHEN', 'BAR'] as $station) {
                    $stationItems = $groupedByStation->get($station);
                    if (! $stationItems || $stationItems->isEmpty()) {
                        continue;
                    }

                    $kotNumber = $this->allocateKotNumber($restaurantId);

                    $kot = Kot::query()->create([
                        'restaurant_id' => $restaurantId,
                        'order_id' => $lockedOrder->id,
                        'order_round_id' => $round->id,
                        'station' => $station,
                        'kot_number' => $kotNumber,
                        'status' => 'PENDING',
                        'reprint_count' => 0,
                        'sent_at' => $now,
                    ]);

                    foreach ($stationItems as $orderItem) {
                        $deltaQty = round(
                            (float) $orderItem->quantity - (float) $orderItem->cancelled_quantity - (float) $orderItem->sent_quantity,
                            3,
                        );

                        $modifiersSnapshot = $orderItem->modifiers->map(fn ($mod) => [
                            'name' => $mod->name,
                            'quantity' => $mod->quantity,
                        ])->values()->all();

                        $kotItem = KotItem::query()->create([
                            'restaurant_id' => $restaurantId,
                            'kot_id' => $kot->id,
                            'order_item_id' => $orderItem->id,
                            'sku' => $orderItem->sku,
                            'item_name' => $orderItem->item_name,
                            'variant_name' => $orderItem->variant_name,
                            'quantity' => number_format($deltaQty, 3, '.', ''),
                            'cancelled_quantity' => 0,
                            'note' => $orderItem->note,
                            'modifiers_snapshot' => $modifiersSnapshot,
                            'status' => 'PENDING',
                        ]);

                        KotStatusEvent::query()->create([
                            'restaurant_id' => $restaurantId,
                            'kot_id' => $kot->id,
                            'kot_item_id' => $kotItem->id,
                            'from_status' => 'DRAFT',
                            'to_status' => 'PENDING',
                            'actor_id' => $user->id,
                            'created_at' => $now,
                        ]);

                        $orderItem->update([
                            'sent_quantity' => number_format((float) $orderItem->sent_quantity + $deltaQty, 3, '.', ''),
                            'status' => $orderItem->status === 'DRAFT' ? 'PENDING' : $orderItem->status,
                            'version' => $orderItem->version + 1,
                        ]);

                        InventoryStock::deductForKotItem($orderItem, $deltaQty, $kot, $user->id);
                    }

                    Audit::record($request, 'kot.created', $kot, after: [
                        'kot_number' => $kot->kot_number,
                        'station' => $kot->station,
                        'round_number' => $round->round_number,
                    ]);

                    $createdKots[] = KotPresenter::toArray($kot->fresh());
                }

                $lockedOrder->update([
                    'version' => $lockedOrder->version + 1,
                ]);

                Audit::record($request, 'order.round_sent', $lockedOrder, after: [
                    'round_number' => $round->round_number,
                    'kots' => array_column($createdKots, 'kot_number'),
                ]);

                return response()->json([
                    'data' => $this->loadOrderWithKots($lockedOrder->fresh()),
                    'kots' => $createdKots,
                ], 201);
            });
        });
    }

    public function start(Request $request, Kot $kot): JsonResponse
    {
        $this->assertRestaurant($request, $kot);
        $this->assertStationActionPermission($request->user(), $kot->station);

        abort_if(
            ! in_array($kot->status, ['PENDING', 'PREPARING'], true),
            409,
            "KOT {$kot->kot_number} cannot be moved to Preparing from status {$kot->status}.",
        );

        DB::transaction(function () use ($request, $kot) {
            $now = now();
            $fromStatus = $kot->status;

            $kot->update([
                'status' => 'PREPARING',
                'started_at' => $kot->started_at ?? $now,
            ]);

            $pendingItems = $kot->items()->where('status', 'PENDING')->get();
            foreach ($pendingItems as $item) {
                $item->update(['status' => 'PREPARING', 'started_at' => $now]);
                OrderItem::query()
                    ->whereKey($item->order_item_id)
                    ->whereIn('status', ['DRAFT', 'PENDING'])
                    ->update(['status' => 'PREPARING']);
            }

            KotStatusEvent::query()->create([
                'restaurant_id' => $kot->restaurant_id,
                'kot_id' => $kot->id,
                'from_status' => $fromStatus,
                'to_status' => 'PREPARING',
                'actor_id' => $request->user()->id,
                'created_at' => $now,
            ]);

            Audit::record($request, 'kot.started', $kot, ['status' => $fromStatus], ['status' => 'PREPARING']);
        });

        return response()->json(['data' => KotPresenter::toArray($kot->fresh())]);
    }

    public function ready(Request $request, Kot $kot): JsonResponse
    {
        $this->assertRestaurant($request, $kot);
        $this->assertStationActionPermission($request->user(), $kot->station);

        abort_if(
            ! in_array($kot->status, ['PENDING', 'PREPARING'], true),
            409,
            "KOT {$kot->kot_number} cannot be marked Ready from status {$kot->status}.",
        );

        DB::transaction(function () use ($request, $kot) {
            $now = now();
            $fromStatus = $kot->status;

            $kot->update([
                'status' => 'READY',
                'started_at' => $kot->started_at ?? $now,
                'ready_at' => $now,
            ]);

            $activeItems = $kot->items()->whereIn('status', ['PENDING', 'PREPARING'])->get();
            foreach ($activeItems as $item) {
                $item->update([
                    'status' => 'READY',
                    'started_at' => $item->started_at ?? $now,
                    'ready_at' => $now,
                ]);
                OrderItem::query()
                    ->whereKey($item->order_item_id)
                    ->whereIn('status', ['DRAFT', 'PENDING', 'PREPARING'])
                    ->update(['status' => 'READY']);
            }

            KotStatusEvent::query()->create([
                'restaurant_id' => $kot->restaurant_id,
                'kot_id' => $kot->id,
                'from_status' => $fromStatus,
                'to_status' => 'READY',
                'actor_id' => $request->user()->id,
                'created_at' => $now,
            ]);

            Audit::record($request, 'kot.ready', $kot, ['status' => $fromStatus], ['status' => 'READY']);
        });

        return response()->json(['data' => KotPresenter::toArray($kot->fresh())]);
    }

    public function serveItem(Request $request, KotItem $kotItem): JsonResponse
    {
        Gate::authorize('manage-orders');
        abort_unless($kotItem->restaurant_id === $request->user()->restaurant_id, 404);
        abort_if($kotItem->status === 'CANCELLED', 409, 'Cancelled items cannot be marked served.');

        DB::transaction(function () use ($request, $kotItem) {
            $now = now();
            $fromStatus = $kotItem->status;

            $kotItem->update([
                'status' => 'SERVED',
                'served_at' => $now,
            ]);

            /** @var OrderItem|null $orderItem */
            $orderItem = OrderItem::query()->find($kotItem->order_item_id);
            if ($orderItem) {
                $effectiveSent = max(0.0, (float) $orderItem->sent_quantity - (float) $orderItem->cancelled_quantity);
                $newServed = min($effectiveSent, (float) $orderItem->served_quantity + (float) $kotItem->quantity);
                $orderItem->update([
                    'served_quantity' => number_format($newServed, 3, '.', ''),
                    'status' => $newServed >= $effectiveSent ? 'SERVED' : $orderItem->status,
                ]);
            }

            $kot = $kotItem->kot()->lockForUpdate()->firstOrFail();
            $hasRemaining = $kot->items()
                ->whereNotIn('status', ['SERVED', 'CANCELLED'])
                ->exists();

            if (! $hasRemaining) {
                $kot->update(['status' => 'SERVED']);
            }

            KotStatusEvent::query()->create([
                'restaurant_id' => $kot->restaurant_id,
                'kot_id' => $kot->id,
                'kot_item_id' => $kotItem->id,
                'from_status' => $fromStatus,
                'to_status' => 'SERVED',
                'actor_id' => $request->user()->id,
                'created_at' => $now,
            ]);

            Audit::record($request, 'kot_item.served', $kotItem, ['status' => $fromStatus], ['status' => 'SERVED']);
        });

        return response()->json(['data' => KotPresenter::toArray($kotItem->kot->fresh())]);
    }

    public function reprint(Request $request, Kot $kot): JsonResponse
    {
        $this->assertRestaurant($request, $kot);
        $user = $request->user();

        $canReprint = $user->can('manage-floor')
            || $user->can('manage-menu')
            || $user->can('manage-orders')
            || ($kot->station === 'KITCHEN' && $user->can('manage-kitchen'))
            || ($kot->station === 'BAR' && $user->can('manage-bar'));

        abort_unless($canReprint, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $before = ['reprint_count' => $kot->reprint_count];
        $kot->update([
            'reprint_count' => $kot->reprint_count + 1,
            'last_reprinted_at' => now(),
            'last_reprint_reason' => trim($validated['reason']),
        ]);

        Audit::record(
            $request,
            'kot.reprinted',
            $kot,
            $before,
            ['reprint_count' => $kot->reprint_count],
            trim($validated['reason']),
        );

        return response()->json(['data' => KotPresenter::toArray($kot->fresh())]);
    }

    private function allocateKotNumber(int $restaurantId): string
    {
        $next = Kot::query()->where('restaurant_id', $restaurantId)->count() + 1;
        $kotNumber = sprintf('KOT-%04d', $next);

        while (Kot::query()->where('restaurant_id', $restaurantId)->where('kot_number', $kotNumber)->exists()) {
            $next++;
            $kotNumber = sprintf('KOT-%04d', $next);
        }

        return $kotNumber;
    }

    private function loadOrderWithKots(Order $order): Order
    {
        return $order->load([
            'table.zone:id,name',
            'waiter:id,name',
            'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
            'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
            'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedViewStations(User $user): array
    {
        if (
            $user->can('manage-floor')
            || $user->can('manage-menu')
            || $user->can('manage-orders')
            || $user->can('view-floor')
        ) {
            return ['KITCHEN', 'BAR'];
        }

        $stations = [];
        if ($user->can('manage-kitchen')) {
            $stations[] = 'KITCHEN';
        }
        if ($user->can('manage-bar')) {
            $stations[] = 'BAR';
        }

        return $stations;
    }

    private function assertStationActionPermission(User $user, string $station): void
    {
        $allowed = $user->can('manage-floor')
            || $user->can('manage-menu')
            || ($station === 'KITCHEN' && $user->can('manage-kitchen'))
            || ($station === 'BAR' && $user->can('manage-bar'));

        abort_unless($allowed, 403);
    }

    private function assertRestaurant(Request $request, Kot $kot): void
    {
        abort_unless($kot->restaurant_id === $request->user()->restaurant_id, 404);
    }
}
