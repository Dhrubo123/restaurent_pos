<?php

namespace App\Support;

use App\Models\Kot;
use App\Models\KotItem;

final class KotPresenter
{
    /**
     * Format a KOT as a strictly price-free operational ticket payload.
     *
     * @return array<string, mixed>
     */
    public static function toArray(Kot $kot): array
    {
        $kot->loadMissing([
            'order.table.zone:id,name',
            'order.waiter:id,name',
            'order.restaurant:id,name',
            'round.sender:id,name',
            'items',
        ]);

        return [
            'id' => $kot->id,
            'kot_number' => $kot->kot_number,
            'station' => $kot->station,
            'status' => $kot->status,
            'round_number' => $kot->round?->round_number ?? 1,
            'order_id' => $kot->order_id,
            'order_number' => $kot->order?->order_number,
            'order_note' => $kot->order?->note,
            'guest_count' => $kot->order?->guest_count ?? 1,
            'restaurant_name' => $kot->order?->restaurant?->name ?? 'RestoFlow',
            'table' => $kot->order?->table ? [
                'id' => $kot->order->table->id,
                'code' => $kot->order->table->code,
                'display_name' => $kot->order->table->display_name,
                'zone' => $kot->order->table->zone?->name ?? 'Dining',
            ] : null,
            'waiter_name' => $kot->round?->sender?->name ?? $kot->order?->waiter?->name ?? 'Staff',
            'reprint_count' => $kot->reprint_count,
            'last_reprinted_at' => $kot->last_reprinted_at,
            'last_reprint_reason' => $kot->last_reprint_reason,
            'sent_at' => $kot->sent_at,
            'started_at' => $kot->started_at,
            'ready_at' => $kot->ready_at,
            'items' => $kot->items->map(fn (KotItem $item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'sku' => $item->sku,
                'item_name' => $item->item_name,
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'cancelled_quantity' => $item->cancelled_quantity,
                'note' => $item->note,
                'modifiers' => array_map(
                    fn (array $mod) => [
                        'name' => $mod['name'] ?? '',
                        'quantity' => $mod['quantity'] ?? 1,
                    ],
                    $item->modifiers_snapshot ?? [],
                ),
                'status' => $item->status,
                'cancel_reason' => $item->cancel_reason,
                'started_at' => $item->started_at,
                'ready_at' => $item->ready_at,
                'served_at' => $item->served_at,
                'cancelled_at' => $item->cancelled_at,
            ])->values()->all(),
        ];
    }
}
