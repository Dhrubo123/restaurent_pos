<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'sku', 'item_name', 'variant_name', 'quantity',
        'unit_price', 'modifier_unit_total', 'modifiers_snapshot',
        'line_subtotal', 'line_tax', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'modifier_unit_total' => 'decimal:2',
            'modifiers_snapshot' => 'array',
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
