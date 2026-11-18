<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'timezone', 'currency_code', 'owner_monthly_budget', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'owner_monthly_budget' => 'decimal:2',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
