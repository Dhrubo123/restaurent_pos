<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['CASH', 'CARD', 'BKASH', 'NAGAD'];

    protected $fillable = [
        'restaurant_id', 'order_id', 'cashier_shift_id', 'method',
        'amount', 'tendered_amount', 'change_given', 'status',
        'provider_reference', 'received_by', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_given' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
