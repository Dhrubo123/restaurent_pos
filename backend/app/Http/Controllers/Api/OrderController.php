<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderMerge;
use App\Models\TableTransfer;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\OrderTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-orders');
        $restaurantId = $request->user()->restaurant_id;

        $orders = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', Order::OPEN_STATUSES)
            ->with([
                'table.zone:id,name',
                'waiter:id,name',
                'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
                'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
                'discounts',
                'payments' => fn ($q) => $q->with('receiver:id,name')->orderBy('id'),
            ])
            ->latest('opened_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        return response()->json(['data' => $this->loadOrderDetails($order)]);
    }

    public function openForTable(Request $request, DiningTable $diningTable): JsonResponse
    {
        Gate::authorize('manage-orders');
        abort_unless($diningTable->restaurant_id === $request->user()->restaurant_id, 404);

        $validated = $request->validate([
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return Idempotency::handle($request, "tables.{$diningTable->id}.orders.open", function () use ($request, $diningTable, $validated) {
            return DB::transaction(function () use ($request, $diningTable, $validated) {
                /** @var DiningTable $lockedTable */
                $lockedTable = DiningTable::query()->whereKey($diningTable->id)->lockForUpdate()->firstOrFail();

                abort_if(! $lockedTable->active, 409, 'Cannot open an order on an inactive table.');

                $existing = $lockedTable->orders()
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->latest('opened_at')
                    ->first();

                if ($existing) {
                    return response()->json(['data' => $this->loadOrderDetails($existing)], 200);
                }

                $user = $request->user();
                $nextNumber = Order::query()->where('restaurant_id', $user->restaurant_id)->count() + 1;
                $orderNumber = sprintf('ORD-%04d', $nextNumber);

                while (Order::query()->where('restaurant_id', $user->restaurant_id)->where('order_number', $orderNumber)->exists()) {
                    $nextNumber++;
                    $orderNumber = sprintf('ORD-%04d', $nextNumber);
                }

                $order = Order::query()->create([
                    'restaurant_id' => $user->restaurant_id,
                    'dining_table_id' => $lockedTable->id,
                    'order_number' => $orderNumber,
                    'status' => 'OPEN',
                    'waiter_id' => $user->id,
                    'guest_count' => $validated['guest_count'] ?? 2,
                    'version' => 1,
                    'note' => $validated['note'] ?? null,
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'service_charge' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'paid_amount' => 0,
                    'balance_due' => 0,
                    'opened_at' => now(),
                ]);

                Audit::record($request, 'order.opened', $order, after: $order->toArray());

                return response()->json(['data' => $this->loadOrderDetails($order)], 201);
            });
        });
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $this->assertVersion($locked, (int) $validated['version']);

            $before = $locked->only(['guest_count', 'note', 'version']);
            $locked->update([
                'guest_count' => $validated['guest_count'] ?? $locked->guest_count,
                'note' => array_key_exists('note', $validated) ? $validated['note'] : $locked->note,
                'version' => $locked->version + 1,
            ]);

            Audit::record($request, 'order.updated', $locked, $before, $locked->only(['guest_count', 'note', 'version']));

            return response()->json(['data' => $this->loadOrderDetails($locked->fresh())]);
        });
    }

    public function requestBill(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $this->assertVersion($locked, (int) $validated['version']);

            abort_if(
                ! $locked->items()->where('status', '!=', 'CANCELLED')->exists(),
                409,
                'Add at least one item before requesting the bill.',
            );

            $before = ['status' => $locked->status, 'version' => $locked->version];
            $locked->update([
                'status' => 'BILL_REQUESTED',
                'version' => $locked->version + 1,
            ]);

            OrderTotals::recalculateOrder($locked);
            Audit::record($request, 'order.bill_requested', $locked, $before, ['status' => 'BILL_REQUESTED', 'version' => $locked->version]);

            return response()->json(['data' => $this->loadOrderDetails($locked->fresh())]);
        });
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $this->assertVersion($locked, (int) $validated['version']);

            abort_if(
                (float) $locked->paid_amount > 0 || $locked->status === 'PARTIALLY_PAID',
                409,
                'Orders with captured payments cannot be cancelled without a refund.',
            );

            $hasSentItems = $locked->items()->where('sent_quantity', '>', 0)->exists();
            if ($hasSentItems && ! $request->user()->can('manage-floor')) {
                abort(403, 'Manager permission is required to cancel an order with sent kitchen items.');
            }

            $before = ['status' => $locked->status, 'version' => $locked->version];
            $locked->items()->where('status', '!=', 'CANCELLED')->update([
                'status' => 'CANCELLED',
                'cancel_reason' => $validated['reason'],
            ]);

            $locked->update([
                'status' => 'CANCELLED',
                'closed_at' => now(),
                'version' => $locked->version + 1,
            ]);

            Audit::record(
                $request,
                'order.cancelled',
                $locked,
                $before,
                ['status' => 'CANCELLED', 'version' => $locked->version],
                $validated['reason'],
            );

            return response()->json(['data' => $this->loadOrderDetails($locked->fresh())]);
        });
    }

    public function transfer(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'to_dining_table_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($lockedOrder);
            $this->assertVersion($lockedOrder, (int) $validated['version']);

            /** @var DiningTable $targetTable */
            $targetTable = DiningTable::query()
                ->where('restaurant_id', $request->user()->restaurant_id)
                ->whereKey($validated['to_dining_table_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($targetTable->id === $lockedOrder->dining_table_id, 409, 'Order is already assigned to this table.');
            abort_if(! $targetTable->active, 409, 'Cannot transfer an order to an inactive table.');

            $targetHasOpenOrder = $targetTable->orders()
                ->whereIn('status', Order::OPEN_STATUSES)
                ->exists();

            abort_if(
                $targetHasOpenOrder,
                409,
                'Target table already has an open order. Use order merge instead.',
            );

            $fromTableId = $lockedOrder->dining_table_id;

            TableTransfer::query()->create([
                'restaurant_id' => $lockedOrder->restaurant_id,
                'order_id' => $lockedOrder->id,
                'from_dining_table_id' => $fromTableId,
                'to_dining_table_id' => $targetTable->id,
                'actor_id' => $request->user()->id,
                'reason' => trim($validated['reason']),
                'transferred_at' => now(),
            ]);

            $lockedOrder->update([
                'dining_table_id' => $targetTable->id,
                'version' => $lockedOrder->version + 1,
            ]);

            Audit::record(
                $request,
                'order.transferred',
                $lockedOrder,
                ['dining_table_id' => $fromTableId],
                ['dining_table_id' => $targetTable->id, 'version' => $lockedOrder->version],
                trim($validated['reason']),
            );

            return response()->json(['data' => $this->loadOrderDetails($lockedOrder->fresh())]);
        });
    }

    public function merge(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'target_order_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $sourceOrder */
            $sourceOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($sourceOrder);
            $this->assertVersion($sourceOrder, (int) $validated['version']);

            /** @var Order $targetOrder */
            $targetOrder = Order::query()
                ->where('restaurant_id', $request->user()->restaurant_id)
                ->whereKey($validated['target_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($sourceOrder->id === $targetOrder->id, 409, 'Cannot merge an order into itself.');
            $this->assertOpen($targetOrder);

            abort_if(
                (float) $sourceOrder->paid_amount > 0
                || (float) $targetOrder->paid_amount > 0
                || $sourceOrder->status === 'PARTIALLY_PAID'
                || $targetOrder->status === 'PARTIALLY_PAID',
                409,
                'Orders with captured partial payments cannot be merged.',
            );

            $snapshot = [
                'source_order_number' => $sourceOrder->order_number,
                'target_order_number' => $targetOrder->order_number,
                'moved_item_ids' => $sourceOrder->items()->pluck('id')->all(),
            ];

            $sourceOrder->items()->update(['order_id' => $targetOrder->id]);

            $sourceOrder->update([
                'status' => 'MERGED',
                'closed_at' => now(),
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'balance_due' => 0,
                'version' => $sourceOrder->version + 1,
            ]);

            $targetOrder->update([
                'guest_count' => min(100, $targetOrder->guest_count + $sourceOrder->guest_count),
                'status' => 'OPEN',
                'version' => $targetOrder->version + 1,
            ]);

            OrderTotals::recalculateOrder($targetOrder);

            OrderMerge::query()->create([
                'restaurant_id' => $sourceOrder->restaurant_id,
                'source_order_id' => $sourceOrder->id,
                'target_order_id' => $targetOrder->id,
                'from_dining_table_id' => $sourceOrder->dining_table_id,
                'to_dining_table_id' => $targetOrder->dining_table_id,
                'actor_id' => $request->user()->id,
                'reason' => trim($validated['reason']),
                'snapshot' => $snapshot,
                'merged_at' => now(),
            ]);

            Audit::record(
                $request,
                'order.merged',
                $targetOrder,
                ['source_order_id' => $sourceOrder->id],
                $snapshot,
                trim($validated['reason']),
            );

            return response()->json(['data' => $this->loadOrderDetails($targetOrder->fresh())]);
        });
    }

    private function loadOrderDetails(Order $order): Order
    {
        return $order->load([
            'table.zone:id,name',
            'waiter:id,name',
            'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
            'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
            'discounts',
            'payments' => fn ($q) => $q->with('receiver:id,name')->orderBy('id'),
            'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
        ]);
    }

    private function assertRestaurant(Request $request, Order $order): void
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 404);
    }

    private function assertOpen(Order $order): void
    {
        abort_unless(
            in_array($order->status, Order::OPEN_STATUSES, true),
            409,
            "Order {$order->order_number} is no longer open.",
        );
    }

    private function assertVersion(Order $order, int $expectedVersion): void
    {
        abort_if(
            $order->version !== $expectedVersion,
            409,
            "Order {$order->order_number} was changed by another user (current version {$order->version}). Reload and review before saving.",
        );
    }
}
