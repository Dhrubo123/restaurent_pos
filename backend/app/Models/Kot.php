<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kot extends Model
{
    public const STATIONS = ['KITCHEN', 'BAR'];
    public const ACTIVE_STATUSES = ['PENDING', 'PREPARING', 'READY'];

    protected $fillable = [
        'restaurant_id', 'order_id', 'order_round_id', 'station', 'kot_number',
        'status', 'reprint_count', 'last_reprinted_at', 'last_reprint_reason',
        'sent_at', 'started_at', 'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'last_reprinted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KotItem::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(KotStatusEvent::class);
    }
}
