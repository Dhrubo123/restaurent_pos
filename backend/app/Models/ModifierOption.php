<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ModifierOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'modifier_group_id', 'name', 'price', 'station_override', 'sort_order', 'active', 'available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function priceHistories(): MorphMany
    {
        return $this->morphMany(PriceHistory::class, 'priceable');
    }
}
