<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuSubcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'menu_category_id', 'name', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
