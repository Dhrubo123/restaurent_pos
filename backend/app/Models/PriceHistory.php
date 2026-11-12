<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'priceable_type', 'priceable_id', 'amount',
        'effective_from', 'effective_to', 'changed_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
