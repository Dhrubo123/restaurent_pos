<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableTransfer extends Model
{
    protected $fillable = [
        'restaurant_id', 'order_id', 'from_dining_table_id', 'to_dining_table_id',
        'actor_id', 'reason', 'transferred_at',
    ];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }

    public function fromTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'from_dining_table_id');
    }

    public function toTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'to_dining_table_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
