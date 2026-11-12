<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MenuItem extends Model
{
    use HasFactory;

    public const STATIONS = ['KITCHEN', 'BAR'];

    protected $fillable = [
        'restaurant_id', 'menu_category_id', 'tax_category_id', 'sku', 'name',
        'menu_subcategory_id', 'description', 'image_path', 'station', 'price', 'sort_order', 'active', 'available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MenuSubcategory::class, 'menu_subcategory_id');
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'item_modifier_groups');
    }

    public function priceHistories(): MorphMany
    {
        return $this->morphMany(PriceHistory::class, 'priceable');
    }
}
