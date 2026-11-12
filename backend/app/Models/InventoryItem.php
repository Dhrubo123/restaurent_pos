<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    public const UNITS = ['pcs', 'kg', 'g', 'l', 'ml', 'btl', 'can', 'pack'];

    protected $fillable = [
        'restaurant_id',
        'sku',
        'name',
        'category',
        'unit',
        'current_stock',
        'reorder_level',
        'unit_cost',
        'auto_eighty_six',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'auto_eighty_six' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function menuIngredients(): HasMany
    {
        return $this->hasMany(MenuItemIngredient::class);
    }
}
