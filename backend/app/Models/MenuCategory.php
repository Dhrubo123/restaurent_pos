<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    public const STATIONS = ['KITCHEN', 'BAR'];

    protected $fillable = [
        'restaurant_id', 'name', 'default_station', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(MenuSubcategory::class);
    }
}
