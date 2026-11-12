<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CashierShiftController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        Gate::authorize('take-payment');
        $user = $request->user();

        $currentShift = CashierShift::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('cashier_id', $user->id)
            ->where('status', 'OPEN')
            ->latest('opened_at')
            ->first();

        $recentShifts = CashierShift::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['cashier:id,name', 'approver:id,name'])
            ->latest('opened_at')
            ->limit(15)
            ->get()
            ->map(fn (CashierShift $shift) => $this->presentShift($shift));

        return response()->json([
            'data' => [
                'current_shift' => $currentShift ? $this->presentShift($currentShift) : null,
                'recent_shifts' => $recentShifts,
            ],
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        Gate::authorize('take-payment');
        $user = $request->user();

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $user, $validated) {
            $existing = CashierShift::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->where('cashier_id', $user->id)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            abort_if($existing !== null, 409, 'You already have an open cashier shift.');

            $openingFloat = number_format((float) $validated['opening_float'], 2, '.', '');

            $shift = CashierShift::query()->create([
                'restaurant_id' => $user->restaurant_id,
                'cashier_id' => $user->id,
                'opening_float' => $openingFloat,
                'expected_cash' => $openingFloat,
                'status' => 'OPEN',
                'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
                'opened_at' => now(),
            ]);

            Audit::record($request, 'shift.opened', $shift, after: $shift->toArray());

            return response()->json(['data' => $this->presentShift($shift)], 201);
        });
    }

    public function close(Request $request, CashierShift $cashierShift): JsonResponse
    {
        Gate::authorize('take-payment');
        $user = $request->user();
        abort_unless($cashierShift->restaurant_id === $user->restaurant_id, 404);

        $canClose = $cashierShift->cashier_id === $user->id
            || $user->can('manage-floor')
            || $user->can('manage-menu');
        abort_unless($canClose, 403);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $cashierShift, $validated) {
            /** @var CashierShift $locked */
            $locked = CashierShift::query()->whereKey($cashierShift->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'OPEN', 409, 'This shift is already closed.');

            $cashCaptured = (float) $locked->payments()
                ->where('method', 'CASH')
                ->where('status', 'CAPTURED')
                ->sum('amount');
            $cashRefunded = (float) $locked->refunds()
                ->where('method', 'CASH')
                ->sum('amount');

            $expectedCash = round((float) $locked->opening_float + $cashCaptured - $cashRefunded, 2);
            $countedCash = round((float) $validated['counted_cash'], 2);
            $variance = round($countedCash - $expectedCash, 2);

            $before = $locked->only(['status', 'expected_cash', 'counted_cash', 'variance']);

            $locked->update([
                'expected_cash' => number_format($expectedCash, 2, '.', ''),
                'counted_cash' => number_format($countedCash, 2, '.', ''),
                'variance' => number_format($variance, 2, '.', ''),
                'status' => 'CLOSED',
                'closed_at' => now(),
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $locked->notes,
            ]);

            Audit::record(
                $request,
                'shift.closed',
                $locked,
                $before,
                $locked->only(['status', 'expected_cash', 'counted_cash', 'variance']),
            );

            return response()->json(['data' => $this->presentShift($locked->fresh())]);
        });
    }

    public function approve(Request $request, CashierShift $cashierShift): JsonResponse
    {
        $user = $request->user();
        abort_unless($cashierShift->restaurant_id === $user->restaurant_id, 404);

        $isManager = $user->can('manage-floor') || $user->can('manage-menu');
        abort_unless($isManager, 403, 'Manager permission is required to approve shift variances.');

        abort_if($cashierShift->status !== 'CLOSED', 409, 'Only closed shifts can be approved.');

        $before = ['status' => $cashierShift->status, 'approved_by' => $cashierShift->approved_by];
        $cashierShift->update([
            'status' => 'APPROVED',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        Audit::record($request, 'shift.approved', $cashierShift, $before, [
            'status' => 'APPROVED',
            'approved_by' => $user->id,
        ]);

        return response()->json(['data' => $this->presentShift($cashierShift->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentShift(CashierShift $shift): array
    {
        $shift->loadMissing(['cashier:id,name', 'approver:id,name']);

        $byMethod = $shift->payments()
            ->where('status', 'CAPTURED')
            ->selectRaw('method, SUM(amount) as total_amount, COUNT(*) as payments_count')
            ->groupBy('method')
            ->pluck('total_amount', 'method');

        $refundsByMethod = $shift->refunds()
            ->selectRaw('method, SUM(amount) as total_refunded')
            ->groupBy('method')
            ->pluck('total_refunded', 'method');

        $cashSales = round((float) ($byMethod['CASH'] ?? 0) - (float) ($refundsByMethod['CASH'] ?? 0), 2);
        $cardSales = round((float) ($byMethod['CARD'] ?? 0) - (float) ($refundsByMethod['CARD'] ?? 0), 2);
        $bkashSales = round((float) ($byMethod['BKASH'] ?? 0) - (float) ($refundsByMethod['BKASH'] ?? 0), 2);
        $nagadSales = round((float) ($byMethod['NAGAD'] ?? 0) - (float) ($refundsByMethod['NAGAD'] ?? 0), 2);
        $expectedCash = round((float) $shift->opening_float + $cashSales, 2);

        return [
            'id' => $shift->id,
            'cashier_id' => $shift->cashier_id,
            'cashier_name' => $shift->cashier?->name ?? 'Cashier',
            'status' => $shift->status,
            'opening_float' => $shift->opening_float,
            'expected_cash' => number_format($expectedCash, 2, '.', ''),
            'counted_cash' => $shift->counted_cash,
            'variance' => $shift->variance,
            'notes' => $shift->notes,
            'opened_at' => $shift->opened_at,
            'closed_at' => $shift->closed_at,
            'approved_by_name' => $shift->approver?->name,
            'approved_at' => $shift->approved_at,
            'totals_by_method' => [
                'CASH' => number_format($cashSales, 2, '.', ''),
                'CARD' => number_format($cardSales, 2, '.', ''),
                'BKASH' => number_format($bkashSales, 2, '.', ''),
                'NAGAD' => number_format($nagadSales, 2, '.', ''),
                'TOTAL' => number_format($cashSales + $cardSales + $bkashSales + $nagadSales, 2, '.', ''),
            ],
        ];
    }
}
