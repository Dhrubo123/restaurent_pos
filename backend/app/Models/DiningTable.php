<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'zone_id', 'code', 'display_name', 'capacity', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function openOrder(): ?Order
    {
        return $this->orders()->whereIn('status', Order::OPEN_STATUSES)->latest('opened_at')->first();
    }
}
