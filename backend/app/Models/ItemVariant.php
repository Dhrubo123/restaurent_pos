<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'menu_item_id', 'sku', 'name', 'price', 'sort_order', 'active', 'available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function priceHistories(): MorphMany
    {
        return $this->morphMany(PriceHistory::class, 'priceable');
    }
}
