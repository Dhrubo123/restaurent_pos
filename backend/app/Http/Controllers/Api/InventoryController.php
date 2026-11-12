<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\InventoryStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);
        $restaurantId = $request->user()->restaurant_id;

        $items = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::query()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('name')
            ->get();

        $purchaseOrders = PurchaseOrder::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['supplier:id,name', 'receiver:id,name', 'items.inventoryItem:id,sku,name,unit'])
            ->latest('received_at')
            ->limit(40)
            ->get();

        $recipes = MenuItemIngredient::query()
            ->where('restaurant_id', $restaurantId)
            ->with([
                'menuItem:id,sku,name,price,available',
                'inventoryItem:id,sku,name,unit,unit_cost,current_stock',
            ])
            ->get();

        $movements = StockMovement::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['inventoryItem:id,sku,name,unit', 'actor:id,name'])
            ->latest('occurred_at')
            ->limit(80)
            ->get();

        $activeItems = $items->where('active', true);
        $outOfStockCount = $activeItems->filter(fn (InventoryItem $i) => (float) $i->current_stock <= 0)->count();
        $lowStockCount = $activeItems->filter(
            fn (InventoryItem $i) => (float) $i->current_stock > 0 && (float) $i->current_stock <= (float) $i->reorder_level,
        )->count();
        $valuation = round(
            (float) $activeItems->sum(fn (InventoryItem $i) => max(0.0, (float) $i->current_stock) * (float) $i->unit_cost),
            2,
        );

        return response()->json([
            'data' => [
                'summary' => [
                    'total_items' => $activeItems->count(),
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'inventory_valuation' => number_format($valuation, 2, '.', ''),
                ],
                'items' => $items,
                'suppliers' => $suppliers,
                'purchase_orders' => $purchaseOrders,
                'recipes' => $recipes,
                'movements' => $movements,
            ],
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $validated = $request->validate([
            'sku' => [
                'required',
                'string',
                'max:40',
                Rule::unique('inventory_items', 'sku')->where('restaurant_id', $restaurantId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'unit' => ['required', Rule::in(InventoryItem::UNITS)],
            'initial_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:9999999.999'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'auto_eighty_six' => ['boolean'],
        ]);

        return DB::transaction(function () use ($request, $user, $restaurantId, $validated) {
            $initialStock = round((float) ($validated['initial_stock'] ?? 0), 3);

            $item = InventoryItem::query()->create([
                'restaurant_id' => $restaurantId,
                'sku' => Str::upper(trim($validated['sku'])),
                'name' => trim($validated['name']),
                'category' => trim($validated['category']),
                'unit' => $validated['unit'],
                'current_stock' => number_format($initialStock, 3, '.', ''),
                'reorder_level' => number_format((float) $validated['reorder_level'], 3, '.', ''),
                'unit_cost' => number_format((float) $validated['unit_cost'], 2, '.', ''),
                'auto_eighty_six' => (bool) ($validated['auto_eighty_six'] ?? true),
                'active' => true,
            ]);

            if ($initialStock > 0) {
                StockMovement::query()->create([
                    'restaurant_id' => $restaurantId,
                    'inventory_item_id' => $item->id,
                    'type' => 'ADJUSTMENT',
                    'quantity_delta' => number_format($initialStock, 3, '.', ''),
                    'stock_after' => number_format($initialStock, 3, '.', ''),
                    'unit_cost' => $item->unit_cost,
                    'reason' => 'Opening stock on item creation',
                    'actor_id' => $user->id,
                    'occurred_at' => now(),
                ]);
            }

            Audit::record($request, 'inventory_item.created', $item, after: $item->toArray());

            return response()->json(['data' => $item], 201);
        });
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizeInventory($request);
        $restaurantId = $request->user()->restaurant_id;
        abort_unless($inventoryItem->restaurant_id === $restaurantId, 404);

        $validated = $request->validate([
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('inventory_items', 'sku')
                    ->where('restaurant_id', $restaurantId)
                    ->ignore($inventoryItem->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'category' => ['sometimes', 'required', 'string', 'max:80'],
            'unit' => ['sometimes', 'required', Rule::in(InventoryItem::UNITS)],
            'reorder_level' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.999'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'auto_eighty_six' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $before = $inventoryItem->toArray();

        $inventoryItem->update([
            'sku' => isset($validated['sku']) ? Str::upper(trim($validated['sku'])) : $inventoryItem->sku,
            'name' => isset($validated['name']) ? trim($validated['name']) : $inventoryItem->name,
            'category' => isset($validated['category']) ? trim($validated['category']) : $inventoryItem->category,
            'unit' => $validated['unit'] ?? $inventoryItem->unit,
            'reorder_level' => isset($validated['reorder_level'])
                ? number_format((float) $validated['reorder_level'], 3, '.', '')
                : $inventoryItem->reorder_level,
            'unit_cost' => isset($validated['unit_cost'])
                ? number_format((float) $validated['unit_cost'], 2, '.', '')
                : $inventoryItem->unit_cost,
            'auto_eighty_six' => array_key_exists('auto_eighty_six', $validated)
                ? (bool) $validated['auto_eighty_six']
                : $inventoryItem->auto_eighty_six,
            'active' => array_key_exists('active', $validated)
                ? (bool) $validated['active']
                : $inventoryItem->active,
        ]);

        Audit::record($request, 'inventory_item.updated', $inventoryItem, $before, $inventoryItem->toArray());

        return response()->json(['data' => $inventoryItem->fresh()]);
    }

    public function adjustStock(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizeInventory($request);
        $user = $request->user();
        abort_unless($inventoryItem->restaurant_id === $user->restaurant_id, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['WASTAGE', 'ADJUSTMENT'])],
            'quantity_delta' => ['required', 'numeric', 'not_in:0', 'min:-9999999.999', 'max:9999999.999'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $user, $inventoryItem, $validated) {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->whereKey($inventoryItem->id)->lockForUpdate()->firstOrFail();

            $rawDelta = round((float) $validated['quantity_delta'], 3);
            $delta = $validated['type'] === 'WASTAGE' ? -abs($rawDelta) : $rawDelta;

            $newStock = round((float) $locked->current_stock + $delta, 3);
            $beforeStock = $locked->current_stock;

            $locked->update([
                'current_stock' => number_format($newStock, 3, '.', ''),
            ]);

            $movement = StockMovement::query()->create([
                'restaurant_id' => $user->restaurant_id,
                'inventory_item_id' => $locked->id,
                'type' => $validated['type'],
                'quantity_delta' => number_format($delta, 3, '.', ''),
                'stock_after' => number_format($newStock, 3, '.', ''),
                'unit_cost' => $locked->unit_cost,
                'reason' => trim($validated['reason']),
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            InventoryStock::evaluateAutoEightySix($locked);

            Audit::record(
                $request,
                'inventory.stock_adjusted',
                $locked,
                ['current_stock' => $beforeStock],
                ['current_stock' => $locked->current_stock, 'type' => $validated['type'], 'delta' => $movement->quantity_delta],
                trim($validated['reason']),
            );

            return response()->json([
                'data' => $locked->fresh(),
                'movement' => $movement->load('actor:id,name'),
            ]);
        });
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);
        $restaurantId = $request->user()->restaurant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('suppliers', 'name')->where('restaurant_id', $restaurantId),
            ],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:300'],
        ]);

        $supplier = Supplier::query()->create([
            'restaurant_id' => $restaurantId,
            'name' => trim($validated['name']),
            'contact_person' => isset($validated['contact_person']) ? trim($validated['contact_person']) : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
            'email' => isset($validated['email']) ? trim($validated['email']) : null,
            'address' => isset($validated['address']) ? trim($validated['address']) : null,
            'active' => true,
        ]);

        Audit::record($request, 'supplier.created', $supplier, after: $supplier->toArray());

        return response()->json(['data' => $supplier], 201);
    }

    public function recordPurchase(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('restaurant_id', $restaurantId),
            ],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where('restaurant_id', $restaurantId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:9999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        return DB::transaction(function () use ($request, $user, $restaurantId, $validated) {
            $poNumber = $this->allocatePoNumber($restaurantId);
            $subtotal = 0.0;

            $preparedLines = [];
            foreach ($validated['items'] as $line) {
                $qty = round((float) $line['quantity'], 3);
                $cost = round((float) $line['unit_cost'], 2);
                $lineTotal = round($qty * $cost, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $preparedLines[] = [
                    'inventory_item_id' => (int) $line['inventory_item_id'],
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => $lineTotal,
                ];
            }

            $taxAmount = round((float) ($validated['tax_amount'] ?? 0), 2);
            $total = round($subtotal + $taxAmount, 2);

            $po = PurchaseOrder::query()->create([
                'restaurant_id' => $restaurantId,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'po_number' => $poNumber,
                'reference_number' => isset($validated['reference_number']) ? trim($validated['reference_number']) : null,
                'status' => 'RECEIVED',
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            foreach ($preparedLines as $line) {
                /** @var InventoryItem $lockedItem */
                $lockedItem = InventoryItem::query()
                    ->whereKey($line['inventory_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStock = max(0.0, (float) $lockedItem->current_stock);
                $oldCost = (float) $lockedItem->unit_cost;
                $newQty = $line['quantity'];
                $purchaseCost = $line['unit_cost'];

                $weightedCost = ($oldStock + $newQty) > 0
                    ? round((($oldStock * $oldCost) + ($newQty * $purchaseCost)) / ($oldStock + $newQty), 2)
                    : $purchaseCost;

                $newStock = round((float) $lockedItem->current_stock + $newQty, 3);

                $lockedItem->update([
                    'current_stock' => number_format($newStock, 3, '.', ''),
                    'unit_cost' => number_format($weightedCost, 2, '.', ''),
                ]);

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $po->id,
                    'inventory_item_id' => $lockedItem->id,
                    'quantity' => number_format($newQty, 3, '.', ''),
                    'unit_cost' => number_format($purchaseCost, 2, '.', ''),
                    'line_total' => number_format($line['line_total'], 2, '.', ''),
                ]);

                StockMovement::query()->create([
                    'restaurant_id' => $restaurantId,
                    'inventory_item_id' => $lockedItem->id,
                    'type' => 'PURCHASE',
                    'quantity_delta' => number_format($newQty, 3, '.', ''),
                    'stock_after' => number_format($newStock, 3, '.', ''),
                    'unit_cost' => number_format($purchaseCost, 2, '.', ''),
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $po->id,
                    'reason' => "Purchase {$po->po_number}" . ($po->reference_number ? " ({$po->reference_number})" : ''),
                    'actor_id' => $user->id,
                    'occurred_at' => now(),
                ]);
            }

            Audit::record($request, 'purchase_order.received', $po, after: [
                'po_number' => $po->po_number,
                'total' => $po->total,
                'items_count' => count($preparedLines),
            ]);

            return response()->json([
                'data' => $po->load(['supplier:id,name', 'receiver:id,name', 'items.inventoryItem:id,sku,name,unit']),
            ], 201);
        });
    }

    public function syncRecipe(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorizeInventory($request);
        $restaurantId = $request->user()->restaurant_id;
        abort_unless($menuItem->restaurant_id === $restaurantId, 404);

        $validated = $request->validate([
            'ingredients' => ['present', 'array'],
            'ingredients.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('inventory_items', 'id')->where('restaurant_id', $restaurantId),
            ],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:9999999.999'],
        ]);

        return DB::transaction(function () use ($request, $restaurantId, $menuItem, $validated) {
            MenuItemIngredient::query()
                ->where('restaurant_id', $restaurantId)
                ->where('menu_item_id', $menuItem->id)
                ->delete();

            foreach ($validated['ingredients'] as $ing) {
                MenuItemIngredient::query()->create([
                    'restaurant_id' => $restaurantId,
                    'menu_item_id' => $menuItem->id,
                    'inventory_item_id' => (int) $ing['inventory_item_id'],
                    'quantity' => number_format((float) $ing['quantity'], 3, '.', ''),
                ]);
            }

            $recipes = MenuItemIngredient::query()
                ->where('restaurant_id', $restaurantId)
                ->where('menu_item_id', $menuItem->id)
                ->with('inventoryItem:id,sku,name,unit,unit_cost,current_stock')
                ->get();

            Audit::record($request, 'menu_item.recipe_updated', $menuItem, after: [
                'menu_item_id' => $menuItem->id,
                'ingredients_count' => $recipes->count(),
            ]);

            return response()->json(['data' => $recipes]);
        });
    }

    private function allocatePoNumber(int $restaurantId): string
    {
        $next = PurchaseOrder::query()->where('restaurant_id', $restaurantId)->count() + 1;
        $poNumber = sprintf('PO-%04d', $next);

        while (PurchaseOrder::query()->where('restaurant_id', $restaurantId)->where('po_number', $poNumber)->exists()) {
            $next++;
            $poNumber = sprintf('PO-%04d', $next);
        }

        return $poNumber;
    }

    private function authorizeInventory(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage-menu') || $user->can('manage-floor'),
            403,
            'Manager permission is required to manage inventory and purchasing.',
        );
    }
}
