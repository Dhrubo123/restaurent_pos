<?php

namespace App\Support;

use App\Models\PriceHistory;
use Illuminate\Database\Eloquent\Model;

final class PriceLedger
{
    public static function recordInitial(
        Model $subject,
        int $restaurantId,
        string|float|int $amount,
        ?int $actorId = null,
        string $reason = 'Initial price',
    ): PriceHistory {
        return PriceHistory::query()->create([
            'restaurant_id' => $restaurantId,
            'priceable_type' => $subject::class,
            'priceable_id' => $subject->getKey(),
            'amount' => $amount,
            'effective_from' => now(),
            'effective_to' => null,
            'changed_by' => $actorId,
            'reason' => $reason,
        ]);
    }

    public static function recordChange(
        Model $subject,
        int $restaurantId,
        string|float|int $newAmount,
        ?int $actorId,
        string $reason,
    ): PriceHistory {
        $timestamp = now();

        PriceHistory::query()
            ->where('priceable_type', $subject::class)
            ->where('priceable_id', $subject->getKey())
            ->whereNull('effective_to')
            ->update(['effective_to' => $timestamp]);

        return PriceHistory::query()->create([
            'restaurant_id' => $restaurantId,
            'priceable_type' => $subject::class,
            'priceable_id' => $subject->getKey(),
            'amount' => $newAmount,
            'effective_from' => $timestamp,
            'effective_to' => null,
            'changed_by' => $actorId,
            'reason' => $reason,
        ]);
    }
}
