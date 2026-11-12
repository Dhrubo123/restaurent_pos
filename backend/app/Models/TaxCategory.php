<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'name', 'rate', 'inclusive', 'active', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'inclusive' => 'boolean',
            'active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
