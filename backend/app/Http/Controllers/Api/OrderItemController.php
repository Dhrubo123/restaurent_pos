<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Support\Audit;
use App\Support\OrderTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderItemController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'menu_item_id' => ['required', 'integer'],
            'item_variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:1', 'max:500'],
            'modifier_option_ids' => ['sometimes', 'array'],
            'modifier_option_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableOrder($lockedOrder, (int) $validated['version']);

            $restaurantId = $request->user()->restaurant_id;

            /** @var MenuItem $menuItem */
            $menuItem = MenuItem::query()
                ->where('restaurant_id', $restaurantId)
                ->with(['category', 'subcategory', 'taxCategory', 'modifierGroups'])
                ->findOrFail($validated['menu_item_id']);

            abort_if(! $menuItem->active, 409, 'This menu item is inactive and cannot be ordered.');
            abort_if(! $menuItem->available, 409, "{$menuItem->name} is currently marked as sold out.");
            abort_if(! $menuItem->category?->active, 409, 'This item category is inactive and cannot be ordered.');
            abort_if($menuItem->subcategory && ! $menuItem->subcategory->active, 409, 'This item subcategory is inactive and cannot be ordered.');

            $unitPrice = (float) $menuItem->price;
            $sku = $menuItem->sku;
            $variantName = null;
            $variantId = null;

            $hasAvailableVariants = $menuItem->variants()
                ->where('active', true)
                ->where('available', true)
                ->exists();
            abort_if($hasAvailableVariants && empty($validated['item_variant_id']), 422, 'Choose an available variant for this item.');

            if (! empty($validated['item_variant_id'])) {
                /** @var ItemVariant $variant */
                $variant = ItemVariant::query()
                    ->where('restaurant_id', $restaurantId)
                    ->where('menu_item_id', $menuItem->id)
                    ->where('active', true)
                    ->where('available', true)
                    ->findOrFail($validated['item_variant_id']);
                $unitPrice = (float) $variant->price;
                $sku = $variant->sku;
                $variantName = $variant->name;
                $variantId = $variant->id;
            }

            if ($request->has('price') || $request->has('unit_price')) {
                $submittedPrice = (float) ($request->input('price') ?? $request->input('unit_price'));
                if (round($submittedPrice, 2) !== round($unitPrice, 2)) {
                    Audit::record(
                        $request,
                        'security.price_tamper_ignored',
                        $menuItem,
                        ['client_submitted_price' => $submittedPrice],
                        ['authoritative_server_price' => $unitPrice],
                    );
                }
            }

            $modifierOptionIds = array_values(array_unique($validated['modifier_option_ids'] ?? []));
            $modifierOptions = collect();
            if ($modifierOptionIds !== []) {
                $allowedGroupIds = $menuItem->modifierGroups()->where('active', true)->pluck('modifier_groups.id');
                $modifierOptions = ModifierOption::query()
                    ->where('restaurant_id', $restaurantId)
                    ->whereIn('modifier_group_id', $allowedGroupIds)
                    ->where('active', true)
                    ->where('available', true)
                    ->whereIn('id', $modifierOptionIds)
                    ->get();

                abort_if(
                    $modifierOptions->count() !== count($modifierOptionIds),
                    422,
                    'One or more selected modifiers are invalid for this item.',
                );
            }

            foreach ($menuItem->modifierGroups->where('active', true) as $group) {
                $selectedCount = $modifierOptions
                    ->where('modifier_group_id', $group->id)
                    ->count();
                $minimum = $group->required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;

                abort_if(
                    $selectedCount < $minimum,
                    422,
                    "Select at least {$minimum} choice(s) from {$group->name}.",
                );
                abort_if(
                    $selectedCount > (int) $group->max_selections,
                    422,
                    "Select no more than {$group->max_selections} choice(s) from {$group->name}.",
                );
            }

            $modifierUnitTotal = (float) $modifierOptions->sum(fn (ModifierOption $opt) => (float) $opt->price);
            $taxRate = (float) ($menuItem->taxCategory?->rate ?? 0);
            $taxInclusive = (bool) ($menuItem->taxCategory?->inclusive ?? false);
            $quantity = (float) $validated['quantity'];

            $lineAmounts = OrderTotals::calculateLine(
                $unitPrice,
                $modifierUnitTotal,
                $quantity,
                0.0,
                $taxRate,
                $taxInclusive,
            );

            $orderItem = OrderItem::query()->create([
                'restaurant_id' => $restaurantId,
                'order_id' => $lockedOrder->id,
                'menu_item_id' => $menuItem->id,
                'item_variant_id' => $variantId,
                'sku' => $sku,
                'item_name' => $menuItem->name,
                'variant_name' => $variantName,
                'station' => $menuItem->station,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'modifier_unit_total' => number_format($modifierUnitTotal, 2, '.', ''),
                'tax_rate' => number_format($taxRate, 3, '.', ''),
                'tax_inclusive' => $taxInclusive,
                'quantity' => number_format($quantity, 3, '.', ''),
                'sent_quantity' => 0,
                'cancelled_quantity' => 0,
                'served_quantity' => 0,
                ...$lineAmounts,
                'note' => isset($validated['note']) ? trim($validated['note']) : null,
                'status' => 'DRAFT',
                'version' => 1,
            ]);

            foreach ($modifierOptions as $option) {
                OrderItemModifier::query()->create([
                    'order_item_id' => $orderItem->id,
                    'modifier_option_id' => $option->id,
                    'name' => $option->name,
                    'unit_price' => $option->price,
                    'quantity' => 1,
                ]);
            }

            $lockedOrder->update([
                'status' => $lockedOrder->status === 'BILL_REQUESTED' ? 'OPEN' : $lockedOrder->status,
                'version' => $lockedOrder->version + 1,
            ]);

            OrderTotals::recalculateOrder($lockedOrder);
            Audit::record($request, 'order_item.added', $orderItem, after: $orderItem->load('modifiers')->toArray());

            return response()->json([
                'data' => $lockedOrder->fresh()->load([
                    'table.zone:id,name',
                    'waiter:id,name',
                    'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
                    'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
                ]),
            ], 201);
        });
    }

    public function update(Request $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);
        abort_unless($orderItem->order_id === $order->id, 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:1', 'max:500'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        return DB::transaction(function () use ($request, $order, $orderItem, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableOrder($lockedOrder, (int) $validated['version']);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()->whereKey($orderItem->id)->lockForUpdate()->firstOrFail();
            abort_if(
                (float) $lockedItem->sent_quantity > 0 || $lockedItem->status !== 'DRAFT',
                409,
                'Sent items cannot be edited directly. Use authorized cancellation instead.',
            );

            $before = $lockedItem->only(['quantity', 'note']);
            $quantity = isset($validated['quantity']) ? (float) $validated['quantity'] : (float) $lockedItem->quantity;

            $lineAmounts = OrderTotals::calculateLine(
                (float) $lockedItem->unit_price,
                (float) $lockedItem->modifier_unit_total,
                $quantity,
                (float) $lockedItem->cancelled_quantity,
                (float) $lockedItem->tax_rate,
                (bool) $lockedItem->tax_inclusive,
            );

            $lockedItem->update([
                'quantity' => number_format($quantity, 3, '.', ''),
                'note' => array_key_exists('note', $validated) ? $validated['note'] : $lockedItem->note,
                ...$lineAmounts,
                'version' => $lockedItem->version + 1,
            ]);

            $lockedOrder->update(['version' => $lockedOrder->version + 1]);
            OrderTotals::recalculateOrder($lockedOrder);

            Audit::record($request, 'order_item.updated', $lockedItem, $before, $lockedItem->only(['quantity', 'note']));

            return response()->json([
                'data' => $lockedOrder->fresh()->load([
                    'table.zone:id,name',
                    'waiter:id,name',
                    'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
                    'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
                ]),
            ]);
        });
    }

    public function destroy(Request $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        Gate::authorize('manage-orders');
        $this->assertRestaurant($request, $order);
        abort_unless($orderItem->order_id === $order->id, 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $order, $orderItem, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableOrder($lockedOrder, (int) $validated['version']);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()->whereKey($orderItem->id)->lockForUpdate()->firstOrFail();
            abort_if(
                (float) $lockedItem->sent_quantity > 0 || $lockedItem->status !== 'DRAFT',
                409,
                'Sent items cannot be deleted. Cancel the sent item with a manager reason instead.',
            );

            $before = $lockedItem->toArray();
            $lockedItem->delete();

            $lockedOrder->update(['version' => $lockedOrder->version + 1]);
            OrderTotals::recalculateOrder($lockedOrder);

            Audit::record($request, 'order_item.removed', $orderItem, before: $before);

            return response()->json([
                'data' => $lockedOrder->fresh()->load([
                    'table.zone:id,name',
                    'waiter:id,name',
                    'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
                    'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
                    'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
                ]),
            ]);
        });
    }

    public function cancelSent(Request $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->assertRestaurant($request, $order);
        abort_unless($orderItem->order_id === $order->id, 404);

        $user = $request->user();
        abort_unless(
            $user->can('manage-floor') || $user->can('manage-menu'),
            403,
            'Manager approval is required to cancel a sent kitchen or bar item.',
        );

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $user, $order, $orderItem, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableOrder($lockedOrder, (int) $validated['version']);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()->whereKey($orderItem->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedItem->status === 'CANCELLED', 409, 'This item is already cancelled.');

            $reason = trim($validated['reason']);
            $now = now();
            $before = $lockedItem->only(['status', 'cancelled_quantity', 'line_subtotal', 'line_total']);

            $lockedItem->update([
                'cancelled_quantity' => $lockedItem->quantity,
                'line_subtotal' => 0,
                'line_tax' => 0,
                'line_total' => 0,
                'status' => 'CANCELLED',
                'cancel_reason' => $reason,
                'version' => $lockedItem->version + 1,
            ]);

            $kotItems = $lockedItem->kotItems()->with('kot')->get();
            foreach ($kotItems as $kotItem) {
                $kotItem->update([
                    'cancelled_quantity' => $kotItem->quantity,
                    'status' => 'CANCELLED',
                    'cancel_reason' => $reason,
                    'cancelled_by' => $user->id,
                    'cancelled_at' => $now,
                ]);

                if ($kotItem->kot) {
                    $hasActiveOnKot = $kotItem->kot->items()->where('status', '!=', 'CANCELLED')->exists();
                    if (! $hasActiveOnKot) {
                        $kotItem->kot->update(['status' => 'CANCELLED']);
                    }
                }
            }

            $lockedOrder->update(['version' => $lockedOrder->version + 1]);
            OrderTotals::recalculateOrder($lockedOrder);

            Audit::record(
                $request,
                'order_item.sent_cancelled',
                $lockedItem,
                $before,
                ['status' => 'CANCELLED', 'cancelled_quantity' => $lockedItem->quantity],
                $reason,
            );

            return response()->json([
                'data' => $lockedOrder->fresh()->load([
                    'table.zone:id,name',
                    'waiter:id,name',
                    'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
                    'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
                    'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
                ]),
            ]);
        });
    }

    private function assertRestaurant(Request $request, Order $order): void
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 404);
    }

    private function assertEditableOrder(Order $order, int $expectedVersion): void
    {
        abort_unless(
            in_array($order->status, Order::OPEN_STATUSES, true),
            409,
            "Order {$order->order_number} is no longer open.",
        );

        abort_if(
            $order->version !== $expectedVersion,
            409,
            "Order {$order->order_number} was changed by another user (current version {$order->version}). Reload and review before saving.",
        );
    }
}
