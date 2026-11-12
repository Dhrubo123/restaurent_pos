<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMerge extends Model
{
    protected $fillable = [
        'restaurant_id', 'source_order_id', 'target_order_id',
        'from_dining_table_id', 'to_dining_table_id', 'actor_id',
        'reason', 'snapshot', 'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'merged_at' => 'datetime',
        ];
    }
}
