<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPES = ['PURCHASE', 'SALE_DEDUCTION', 'WASTAGE', 'ADJUSTMENT', 'VOID_RETURN'];

    protected $fillable = [
        'restaurant_id',
        'inventory_item_id',
        'type',
        'quantity_delta',
        'stock_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reason',
        'actor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
