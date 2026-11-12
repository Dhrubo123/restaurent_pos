<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id', 'user_id', 'scope', 'idempotency_key',
        'request_hash', 'status_code', 'response_body', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
