<?php

namespace App\Support;

use App\Models\InventoryItem;
use App\Models\Kot;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;
use App\Models\OrderItem;
use App\Models\StockMovement;

final class InventoryStock
{
    public static function deductForKotItem(
        OrderItem $orderItem,
        float $deltaQty,
        Kot $kot,
        int $actorId,
    ): void {
        if (! $orderItem->menu_item_id || $deltaQty <= 0) {
            return;
        }

        $recipes = MenuItemIngredient::query()
            ->where('restaurant_id', $orderItem->restaurant_id)
            ->where('menu_item_id', $orderItem->menu_item_id)
            ->get();

        foreach ($recipes as $recipe) {
            /** @var InventoryItem|null $invItem */
            $invItem = InventoryItem::query()
                ->whereKey($recipe->inventory_item_id)
                ->lockForUpdate()
                ->first();

            if (! $invItem || ! $invItem->active) {
                continue;
            }

            $deduction = round((float) $recipe->quantity * $deltaQty, 3);
            if ($deduction <= 0) {
                continue;
            }

            $newStock = round((float) $invItem->current_stock - $deduction, 3);
            $invItem->update([
                'current_stock' => number_format($newStock, 3, '.', ''),
            ]);

            StockMovement::query()->create([
                'restaurant_id' => $orderItem->restaurant_id,
                'inventory_item_id' => $invItem->id,
                'type' => 'SALE_DEDUCTION',
                'quantity_delta' => number_format(-$deduction, 3, '.', ''),
                'stock_after' => number_format($newStock, 3, '.', ''),
                'unit_cost' => $invItem->unit_cost,
                'reference_type' => Kot::class,
                'reference_id' => $kot->id,
                'reason' => "KOT {$kot->kot_number} · {$deltaQty}x {$orderItem->item_name}",
                'actor_id' => $actorId,
                'occurred_at' => now(),
            ]);

            self::evaluateAutoEightySix($invItem);
        }
    }

    public static function evaluateAutoEightySix(InventoryItem $invItem): void
    {
        if (! $invItem->auto_eighty_six) {
            return;
        }

        $linkedRecipes = MenuItemIngredient::query()
            ->where('inventory_item_id', $invItem->id)
            ->get();

        foreach ($linkedRecipes as $recipe) {
            $requiredPerUnit = (float) $recipe->quantity;
            if ((float) $invItem->current_stock < $requiredPerUnit) {
                MenuItem::query()
                    ->whereKey($recipe->menu_item_id)
                    ->where('available', true)
                    ->update(['available' => false]);
            }
        }
    }
}
