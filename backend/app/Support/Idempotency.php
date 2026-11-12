<?php

namespace App\Support;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class Idempotency
{
    /**
     * @param  Closure(): JsonResponse  $callback
     */
    public static function handle(Request $request, string $scope, Closure $callback): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $callback();
        }

        $user = $request->user();
        $payload = $request->all();
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = IdempotencyKey::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            abort_if(
                ! hash_equals($existing->request_hash, $hash),
                409,
                'Idempotency key has already been used with a different request payload.',
            );

            return response()->json($existing->response_body, $existing->status_code);
        }

        $response = $callback();

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            IdempotencyKey::query()->create([
                'restaurant_id' => $user->restaurant_id,
                'user_id' => $user->id,
                'scope' => $scope,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
