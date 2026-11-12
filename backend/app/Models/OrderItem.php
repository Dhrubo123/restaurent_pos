<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'restaurant_id', 'order_id', 'menu_item_id', 'item_variant_id', 'sku', 'item_name',
        'variant_name', 'station', 'unit_price', 'modifier_unit_total', 'tax_rate',
        'tax_inclusive', 'quantity', 'sent_quantity', 'cancelled_quantity', 'served_quantity',
        'line_subtotal', 'line_tax', 'line_total', 'note', 'status', 'cancel_reason', 'version',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'modifier_unit_total' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_inclusive' => 'boolean',
            'quantity' => 'decimal:3',
            'sent_quantity' => 'decimal:3',
            'cancelled_quantity' => 'decimal:3',
            'served_quantity' => 'decimal:3',
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    public function kotItems(): HasMany
    {
        return $this->hasMany(KotItem::class);
    }
}
