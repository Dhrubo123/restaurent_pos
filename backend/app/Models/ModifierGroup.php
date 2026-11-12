<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'name', 'min_selections', 'max_selections', 'required', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'item_modifier_groups');
    }
}
