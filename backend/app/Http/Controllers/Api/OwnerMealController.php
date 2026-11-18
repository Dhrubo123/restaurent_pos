<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DiningTable;
use App\Models\ItemVariant;
use App\Models\Kot;
use App\Models\KotItem;
use App\Models\KotStatusEvent;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderRound;
use App\Models\Restaurant;
use App\Models\Zone;
use App\Support\Audit;
use App\Support\InventoryStock;
use App\Support\KotPresenter;
use App\Support\OrderTotals;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerMealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-orders') || $user->can('manage-floor') || $user->can('manage-menu') || $user->roles()->where('key', 'admin')->exists(),
            403,
        );
        $isAdmin = $user->roles()->where('key', 'admin')->exists() || $user->can('manage-security');

        $restaurant = Restaurant::query()->findOrFail($user->restaurant_id);
        $tz = $restaurant->timezone ?? 'Asia/Dhaka';

        $nowTz = Carbon::now($tz);
        $monthStart = $nowTz->copy()->startOfMonth()->utc();
        $monthEnd = $nowTz->copy()->endOfMonth()->utc();
        $dayStart = $nowTz->copy()->startOfDay()->utc();
        $dayEnd = $nowTz->copy()->endOfDay()->utc();

        $monthlyBudget = round((float) ($restaurant->owner_monthly_budget ?? 20000), 2);

        $thisMonthConsumed = round(
            (float) Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', 'OWNER_COMP')
                ->whereBetween('opened_at', [$monthStart, $monthEnd])
                ->sum('total'),
            2,
        );

        $todayConsumed = round(
            (float) Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', 'OWNER_COMP')
                ->whereBetween('opened_at', [$dayStart, $dayEnd])
                ->sum('total'),
            2,
        );

        $thisMonthCount = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'OWNER_COMP')
            ->whereBetween('opened_at', [$monthStart, $monthEnd])
            ->count();

        $recentMeals = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'OWNER_COMP')
            ->with([
                'waiter:id,name',
                'items.modifiers',
                'kots' => fn ($q) => $q->with([
                    'order.table.zone:id,name',
                    'order.waiter:id,name',
                    'order.restaurant:id,name',
                    'round.sender:id,name',
                    'items',
                ])->latest('sent_at'),
            ])
            ->latest('opened_at')
            ->limit(40)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'guest_count' => $order->guest_count,
                    'note' => $order->note,
                    'subtotal' => $order->subtotal,
                    'tax_amount' => $order->tax_amount,
                    'total' => $order->total,
                    'opened_at' => $order->opened_at?->toIso8601String(),
                    'waiter_name' => $order->waiter?->name ?? 'Owner',
                    'items' => $order->items->map(fn (OrderItem $i) => [
                        'id' => $i->id,
                        'sku' => $i->sku,
                        'item_name' => $i->item_name,
                        'variant_name' => $i->variant_name,
                        'station' => $i->station,
                        'quantity' => $i->quantity,
                        'line_total' => $i->line_total,
                        'note' => $i->note,
                        'modifiers' => $i->modifiers->map(fn ($m) => [
                            'name' => $m->name,
                            'unit_price' => $m->unit_price,
                        ])->values()->all(),
                    ])->values()->all(),
                    'kots' => $order->kots->map(fn (Kot $k) => KotPresenter::toArray($k))->values()->all(),
                ];
            })
            ->values()
            ->all();

        $voidEvents = AuditEvent::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('action', 'order_item.sent_cancelled')
            ->with('actor:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        $voidOrderItems = OrderItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', $voidEvents->pluck('auditable_id'))
            ->with([
                'order.table:id,code,display_name',
                'order.items' => fn ($q) => $q->where('status', '!=', 'CANCELLED')->orderBy('id'),
                'order.kots' => fn ($q) => $q->with([
                    'order.table.zone:id,name',
                    'order.waiter:id,name',
                    'order.restaurant:id,name',
                    'round.sender:id,name',
                    'items',
                ])->orderBy('id'),
                'kotItems.kot' => fn ($q) => $q->with([
                    'order.table.zone:id,name',
                    'order.waiter:id,name',
                    'order.restaurant:id,name',
                    'round.sender:id,name',
                    'items',
                ]),
            ])
            ->get()
            ->keyBy('id');

        $voidedAlerts = $voidEvents->map(function (AuditEvent $event) use ($voidOrderItems): array {
            /** @var OrderItem|null $item */
            $item = $voidOrderItems->get($event->auditable_id);
            $kots = $item?->kotItems
                ->map(fn ($kotItem) => $kotItem->kot)
                ->filter()
                ->unique('id')
                ->values() ?? collect();

            $voidKotIds = $kots->pluck('id')->all();

            $replacementItems = ($item?->order?->items ?? collect())
                ->where('id', '!=', $item?->id)
                ->map(fn (OrderItem $activeLine): array => [
                    'id' => $activeLine->id,
                    'item_name' => $activeLine->item_name,
                    'variant_name' => $activeLine->variant_name,
                    'quantity' => (string) $activeLine->quantity,
                    'line_total' => (string) $activeLine->line_total,
                ])
                ->values()
                ->all();

            $replacementKots = ($item?->order?->kots ?? collect())
                ->whereNotIn('id', $voidKotIds)
                ->map(fn (Kot $k) => KotPresenter::toArray($k))
                ->values()
                ->all();

            return [
                'id' => $event->id,
                'order_item_id' => $event->auditable_id,
                'order_number' => $item?->order?->order_number ?? 'Unknown order',
                'table_code' => $item?->order?->table?->code ?? 'Takeaway',
                'table_name' => $item?->order?->table?->display_name,
                'item_name' => $item?->item_name ?? 'Deleted menu item',
                'variant_name' => $item?->variant_name,
                'station' => $item?->station ?? $kots->first()?->station,
                'quantity' => (string) ($item?->cancelled_quantity ?? $event->after['cancelled_quantity'] ?? 0),
                'original_total' => number_format((float) ($event->before['line_total'] ?? 0), 2, '.', ''),
                'reason' => $event->reason,
                'voided_by' => $event->actor?->name ?? 'System',
                'voided_at' => $event->created_at?->toIso8601String(),
                'kots' => $kots->map(fn (Kot $k) => KotPresenter::toArray($k))->values()->all(),
                'replacement_items' => $replacementItems,
                'replacement_kots' => $replacementKots,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'monthly_budget' => number_format($monthlyBudget, 2, '.', ''),
                'this_month_consumed' => number_format($thisMonthConsumed, 2, '.', ''),
                'remaining_budget' => number_format(max(0.0, $monthlyBudget - $thisMonthConsumed), 2, '.', ''),
                'over_budget_amount' => number_format(max(0.0, $thisMonthConsumed - $monthlyBudget), 2, '.', ''),
                'today_consumed' => number_format($todayConsumed, 2, '.', ''),
                'this_month_orders_count' => $thisMonthCount,
                'month_label' => $nowTz->format('F Y'),
                'recent_meals' => $recentMeals,
                'voided_alerts' => $isAdmin ? $voidedAlerts : [],
            ],
        ]);
    }

    public function updateBudget(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-floor') || $user->can('manage-menu') || $user->roles()->where('key', 'admin')->exists(),
            403,
        );

        $validated = $request->validate([
            'monthly_budget' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $restaurant = Restaurant::query()->findOrFail($user->restaurant_id);
        $before = ['owner_monthly_budget' => $restaurant->owner_monthly_budget];
        $restaurant->update([
            'owner_monthly_budget' => number_format((float) $validated['monthly_budget'], 2, '.', ''),
        ]);

        Audit::record(
            $request,
            'owner.budget_updated',
            $restaurant,
            $before,
            ['owner_monthly_budget' => $restaurant->owner_monthly_budget],
        );

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-orders') || $user->can('manage-floor') || $user->can('manage-menu') || $user->roles()->where('key', 'admin')->exists(),
            403,
        );

        $validated = $request->validate([
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'note' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.item_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:1', 'max:200'],
            'items.*.modifier_option_ids' => ['sometimes', 'array'],
            'items.*.modifier_option_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:250'],
        ]);

        return DB::transaction(function () use ($request, $user, $validated) {
            $restaurantId = $user->restaurant_id;

            $ownerZone = Zone::query()->firstOrCreate(
                ['restaurant_id' => $restaurantId, 'name' => 'Owner House Account'],
                ['sort_order' => 999, 'active' => false],
            );

            $ownerTable = DiningTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurantId, 'code' => 'OWNER'],
                [
                    'zone_id' => $ownerZone->id,
                    'display_name' => 'Owner Meal (No Sale)',
                    'capacity' => 4,
                    'sort_order' => 999,
                    'active' => false,
                ],
            );

            $nextNumber = Order::query()->where('restaurant_id', $restaurantId)->count() + 1;
            $orderNumber = sprintf('OWN-%04d', $nextNumber);
            while (Order::query()->where('restaurant_id', $restaurantId)->where('order_number', $orderNumber)->exists()) {
                $nextNumber++;
                $orderNumber = sprintf('OWN-%04d', $nextNumber);
            }

            $customNote = trim((string) ($validated['note'] ?? ''));
            $fullNote = $customNote !== ''
                ? "OWNER MEAL (NO SALE) · {$customNote}"
                : 'OWNER MEAL (NO SALE — INVENTORY DEDUCTED)';

            $now = now();

            $order = Order::query()->create([
                'restaurant_id' => $restaurantId,
                'dining_table_id' => $ownerTable->id,
                'merged_dining_table_ids' => [$ownerTable->id],
                'order_number' => $orderNumber,
                'status' => 'OWNER_COMP',
                'waiter_id' => $user->id,
                'guest_count' => $validated['guest_count'] ?? 1,
                'version' => 1,
                'note' => $fullNote,
                'subtotal' => 0,
                'discount_amount' => 0,
                'service_charge' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'balance_due' => 0,
                'opened_at' => $now,
                'closed_at' => $now,
            ]);

            $createdOrderItems = collect();

            foreach ($validated['items'] as $itemPayload) {
                /** @var MenuItem $menuItem */
                $menuItem = MenuItem::query()
                    ->where('restaurant_id', $restaurantId)
                    ->whereKey($itemPayload['menu_item_id'])
                    ->with(['taxCategory', 'variants'])
                    ->firstOrFail();

                $unitPrice = (float) $menuItem->price;
                $variantName = null;
                $variantId = $itemPayload['item_variant_id'] ?? null;

                if ($variantId) {
                    /** @var ItemVariant $variant */
                    $variant = $menuItem->variants->firstWhere('id', (int) $variantId);
                    if ($variant) {
                        $unitPrice = (float) $variant->price;
                        $variantName = $variant->name;
                    }
                }

                $modIds = $itemPayload['modifier_option_ids'] ?? [];
                $modifierOptions = ! empty($modIds)
                    ? ModifierOption::query()->whereIn('id', $modIds)->get()
                    : collect();

                $modifierUnitTotal = (float) $modifierOptions->sum(fn (ModifierOption $m) => (float) $m->price);
                $taxRate = (float) ($menuItem->taxCategory?->rate ?? 0);
                $taxInclusive = (bool) ($menuItem->taxCategory?->inclusive ?? false);
                $quantity = (float) $itemPayload['quantity'];

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
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'item_variant_id' => $variantId,
                    'sku' => $menuItem->sku,
                    'item_name' => $menuItem->name,
                    'variant_name' => $variantName,
                    'station' => $menuItem->station,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'modifier_unit_total' => number_format($modifierUnitTotal, 2, '.', ''),
                    'tax_rate' => number_format($taxRate, 3, '.', ''),
                    'tax_inclusive' => $taxInclusive,
                    'quantity' => number_format($quantity, 3, '.', ''),
                    'sent_quantity' => number_format($quantity, 3, '.', ''),
                    'cancelled_quantity' => 0,
                    'served_quantity' => 0,
                    ...$lineAmounts,
                    'note' => ! empty($itemPayload['note']) ? trim($itemPayload['note']) : null,
                    'status' => 'PENDING',
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

                $createdOrderItems->push($orderItem->load('modifiers'));
            }

            OrderTotals::recalculateOrder($order);
            $order->update([
                'status' => 'OWNER_COMP',
                'paid_amount' => 0,
                'balance_due' => 0,
                'closed_at' => $now,
            ]);

            $round = OrderRound::query()->create([
                'restaurant_id' => $restaurantId,
                'order_id' => $order->id,
                'round_number' => 1,
                'sent_by' => $user->id,
                'sent_at' => $now,
            ]);

            $groupedByStation = $createdOrderItems->groupBy('station');
            $createdKots = [];

            foreach (['KITCHEN', 'BAR'] as $station) {
                $stationItems = $groupedByStation->get($station);
                if (! $stationItems || $stationItems->isEmpty()) {
                    continue;
                }

                $kotNumber = $this->allocateKotNumber($restaurantId);

                $kot = Kot::query()->create([
                    'restaurant_id' => $restaurantId,
                    'order_id' => $order->id,
                    'order_round_id' => $round->id,
                    'station' => $station,
                    'kot_number' => $kotNumber,
                    'status' => 'PENDING',
                    'reprint_count' => 0,
                    'sent_at' => $now,
                ]);

                foreach ($stationItems as $orderItem) {
                    $qty = (float) $orderItem->quantity;
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
                        'quantity' => number_format($qty, 3, '.', ''),
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

                    InventoryStock::deductForKotItem($orderItem, $qty, $kot, $user->id);
                }

                $createdKots[] = KotPresenter::toArray($kot->fresh());
            }

            Audit::record($request, 'owner.meal_ordered', $order, after: [
                'order_number' => $order->order_number,
                'budget_value' => $order->total,
                'kots' => array_column($createdKots, 'kot_number'),
            ]);

            return response()->json([
                'kots' => $createdKots,
                'summary' => $this->index($request)->getData(true)['data'],
            ], 201);
        });
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
}
