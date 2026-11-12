<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    public const TYPES = ['PERCENTAGE', 'FIXED'];

    protected $fillable = [
        'restaurant_id', 'order_id', 'type', 'value',
        'computed_amount', 'reason', 'requested_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'computed_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
