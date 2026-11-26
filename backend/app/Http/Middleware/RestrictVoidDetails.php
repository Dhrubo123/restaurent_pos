<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class RestrictVoidDetails
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            $data = $response->getData();
            if ($this->redact($data) && ! $request->user()?->roles()->where('key', 'admin')->exists()) {
                $response->setData($data);
            }
        }

        return $response;
    }

    private function redact(mixed $value): bool
    {
        $changed = false;
        if ($value instanceof stdClass) {
            foreach (['void_reason', 'voided_by', 'voided_at', 'cancel_reason', 'cancelled_by', 'cancelled_by_name', 'cancelled_at', 'canceller'] as $field) {
                if (property_exists($value, $field)) {
                    unset($value->{$field});
                    $changed = true;
                }
            }
        }

        if (is_array($value) || $value instanceof stdClass) {
            foreach ($value as $child) {
                $changed = $this->redact($child) || $changed;
            }
        }

        return $changed;
    }
}
