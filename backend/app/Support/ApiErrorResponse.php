<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiErrorResponse
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function make(string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
