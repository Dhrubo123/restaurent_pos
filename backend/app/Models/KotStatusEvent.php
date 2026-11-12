<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KotStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id', 'kot_id', 'kot_item_id', 'from_status',
        'to_status', 'actor_id', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function kot(): BelongsTo
    {
        return $this->belongsTo(Kot::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
