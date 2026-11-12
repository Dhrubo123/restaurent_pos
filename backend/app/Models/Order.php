<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const OPEN_STATUSES = ['OPEN', 'BILL_REQUESTED', 'PARTIALLY_PAID'];

    protected $fillable = [
        'restaurant_id', 'dining_table_id', 'order_number', 'status', 'waiter_id', 'guest_count',
        'version', 'note', 'subtotal', 'discount_amount', 'service_charge', 'tax_amount',
        'total', 'paid_amount', 'balance_due', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TableTransfer::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(OrderRound::class);
    }

    public function kots(): HasMany
    {
        return $this->hasMany(Kot::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
