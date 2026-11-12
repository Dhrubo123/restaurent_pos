<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'restaurant_id', 'order_id', 'invoice_number', 'status', 'guest_count',
        'table_code', 'table_name', 'waiter_name', 'cashier_name',
        'subtotal', 'discount_amount', 'service_charge', 'tax_amount',
        'total', 'paid_amount', 'refunded_amount', 'tendered_cash', 'change_given',
        'reprint_count', 'last_reprinted_at', 'last_reprint_reason',
        'issued_by', 'issued_at', 'void_reason', 'voided_by', 'voided_at',
        'payment_snapshot',
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
            'refunded_amount' => 'decimal:2',
            'tendered_cash' => 'decimal:2',
            'change_given' => 'decimal:2',
            'issued_at' => 'datetime',
            'last_reprinted_at' => 'datetime',
            'voided_at' => 'datetime',
            'payment_snapshot' => 'array',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
