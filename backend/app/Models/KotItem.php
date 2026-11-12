<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KotItem extends Model
{
    protected $fillable = [
        'restaurant_id', 'kot_id', 'order_item_id', 'sku', 'item_name',
        'variant_name', 'quantity', 'cancelled_quantity', 'note', 'modifiers_snapshot',
        'status', 'cancel_reason', 'cancelled_by', 'started_at', 'ready_at',
        'served_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cancelled_quantity' => 'decimal:3',
            'modifiers_snapshot' => 'array',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function kot(): BelongsTo
    {
        return $this->belongsTo(Kot::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
