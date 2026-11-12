<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Audit
{
    public static function record(
        Request $request,
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): void {
        $user = $request->user();

        AuditEvent::query()->create([
            'restaurant_id' => $user?->restaurant_id,
            'actor_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'request_id' => $request->header('X-Request-ID'),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
