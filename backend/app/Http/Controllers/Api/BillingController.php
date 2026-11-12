<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\OrderTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public const WAITER_MAX_DISCOUNT_PERCENT = 5.0;

    public function applyDiscount(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage-orders') || $user->can('take-payment'), 403);
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(Discount::TYPES)],
            'value' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $user, $order, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpenOrder($lockedOrder, (int) $validated['version']);

            OrderTotals::recalculateOrder($lockedOrder);
            $subtotal = (float) $lockedOrder->subtotal;
            abort_if($subtotal <= 0, 409, 'Add items to the order before applying a discount.');

            $type = $validated['type'];
            $value = (float) $validated['value'];

            if ($type === 'PERCENTAGE') {
                abort_if($value > 100, 422, 'Percentage discount cannot exceed 100%.');
                $computedAmount = round($subtotal * ($value / 100), 2);
                $effectivePct = $value;
            } else {
                abort_if($value > $subtotal, 422, 'Fixed discount cannot exceed the order subtotal.');
                $computedAmount = round($value, 2);
                $effectivePct = ($computedAmount / $subtotal) * 100;
            }

            $isManager = $user->can('manage-floor') || $user->can('manage-menu');
            if ($effectivePct > self::WAITER_MAX_DISCOUNT_PERCENT + 0.0001 && ! $isManager) {
                abort(403, 'Discounts above 5% require manager approval.');
            }

            $lockedOrder->discounts()->delete();

            $discount = Discount::query()->create([
                'restaurant_id' => $lockedOrder->restaurant_id,
                'order_id' => $lockedOrder->id,
                'type' => $type,
                'value' => number_format($value, 2, '.', ''),
                'computed_amount' => number_format($computedAmount, 2, '.', ''),
                'reason' => trim($validated['reason']),
                'requested_by' => $user->id,
                'approved_by' => $isManager ? $user->id : null,
            ]);

            $lockedOrder->update(['version' => $lockedOrder->version + 1]);
            OrderTotals::recalculateOrder($lockedOrder);

            Audit::record(
                $request,
                'order.discount_applied',
                $lockedOrder,
                null,
                $discount->toArray(),
                trim($validated['reason']),
            );

            return response()->json(['data' => $this->loadOrderWithBilling($lockedOrder->fresh())]);
        });
    }

    public function removeDiscount(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage-orders') || $user->can('take-payment'), 403);
        $this->assertRestaurant($request, $order);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $order, $validated) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOpenOrder($lockedOrder, (int) $validated['version']);

            $lockedOrder->discounts()->delete();
            $lockedOrder->update(['version' => $lockedOrder->version + 1]);
            OrderTotals::recalculateOrder($lockedOrder);

            Audit::record($request, 'order.discount_removed', $lockedOrder);

            return response()->json(['data' => $this->loadOrderWithBilling($lockedOrder->fresh())]);
        });
    }

    public function recordPayment(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('take-payment');
        $this->assertRestaurant($request, $order);
        $user = $request->user();

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'tendered_amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
        ]);

        return Idempotency::handle($request, "orders.{$order->id}.payments", function () use ($request, $user, $order, $validated) {
            return DB::transaction(function () use ($request, $user, $order, $validated) {
                $shift = $this->requireOpenShift($user);

                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->assertOpenOrder($lockedOrder, (int) $validated['version']);

                $payment = $this->capturePaymentOnLockedOrder($lockedOrder, $shift, $user, $validated);
                $this->refreshShiftExpectedCash($shift);

                Audit::record($request, 'payment.captured', $payment, after: $payment->toArray());

                return response()->json([
                    'data' => $this->loadOrderWithBilling($lockedOrder->fresh()),
                    'payment' => $payment,
                ], 201);
            });
        });
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('take-payment');
        $this->assertRestaurant($request, $order);
        $user = $request->user();

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'method' => ['sometimes', 'nullable', Rule::in(Payment::METHODS)],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'tendered_amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return Idempotency::handle($request, "orders.{$order->id}.checkout", function () use ($request, $user, $order, $validated) {
            return DB::transaction(function () use ($request, $user, $order, $validated) {
                $shift = $this->requireOpenShift($user);

                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->status === 'PAID' && $lockedOrder->invoice) {
                    return response()->json([
                        'data' => $this->loadOrderWithBilling($lockedOrder),
                        'invoice' => $lockedOrder->invoice->load('items'),
                    ]);
                }

                $this->assertOpenOrder($lockedOrder, (int) $validated['version']);

                if (! empty($validated['method']) && ! empty($validated['amount'])) {
                    $payment = $this->capturePaymentOnLockedOrder($lockedOrder, $shift, $user, [
                        'method' => $validated['method'],
                        'amount' => $validated['amount'],
                        'tendered_amount' => $validated['tendered_amount'] ?? null,
                        'provider_reference' => $validated['provider_reference'] ?? null,
                    ]);
                    $this->refreshShiftExpectedCash($shift);
                    Audit::record($request, 'payment.captured', $payment, after: $payment->toArray());
                }

                OrderTotals::recalculateOrder($lockedOrder);
                $lockedOrder->refresh();

                abort_if(
                    (float) $lockedOrder->total <= 0,
                    409,
                    'Cannot checkout an order with a zero total.',
                );

                abort_if(
                    (float) $lockedOrder->balance_due > 0.001,
                    409,
                    "Cannot finalize invoice while a balance of ৳{$lockedOrder->balance_due} remains unpaid.",
                );

                $lockedOrder->loadMissing(['table', 'waiter', 'items.modifiers', 'payments']);

                $invoiceNumber = $this->allocateInvoiceNumber($user->restaurant_id);
                $capturedPayments = $lockedOrder->payments()->where('status', 'CAPTURED')->orderBy('id')->get();

                $tenderedCash = (float) $capturedPayments
                    ->where('method', 'CASH')
                    ->sum(fn (Payment $p) => (float) ($p->tendered_amount ?? $p->amount));
                $changeGiven = (float) $capturedPayments->sum(fn (Payment $p) => (float) $p->change_given);

                $invoice = Invoice::query()->create([
                    'restaurant_id' => $user->restaurant_id,
                    'order_id' => $lockedOrder->id,
                    'invoice_number' => $invoiceNumber,
                    'status' => 'ISSUED',
                    'guest_count' => $lockedOrder->guest_count,
                    'table_code' => $lockedOrder->table?->code ?? 'TBL',
                    'table_name' => $lockedOrder->table?->display_name ?? 'Table',
                    'waiter_name' => $lockedOrder->waiter?->name ?? 'Staff',
                    'cashier_name' => $user->name,
                    'subtotal' => $lockedOrder->subtotal,
                    'discount_amount' => $lockedOrder->discount_amount,
                    'service_charge' => $lockedOrder->service_charge,
                    'tax_amount' => $lockedOrder->tax_amount,
                    'total' => $lockedOrder->total,
                    'paid_amount' => $lockedOrder->paid_amount,
                    'tendered_cash' => number_format($tenderedCash, 2, '.', ''),
                    'change_given' => number_format($changeGiven, 2, '.', ''),
                    'issued_by' => $user->id,
                    'issued_at' => now(),
                    'payment_snapshot' => $capturedPayments->map(fn (Payment $p) => [
                        'id' => $p->id,
                        'method' => $p->method,
                        'amount' => $p->amount,
                        'tendered_amount' => $p->tendered_amount,
                        'change_given' => $p->change_given,
                        'provider_reference' => $p->provider_reference,
                        'captured_at' => $p->captured_at?->toIso8601String(),
                    ])->values()->all(),
                ]);

                foreach ($lockedOrder->items->where('status', '!=', 'CANCELLED') as $line) {
                    $effectiveQty = max(0.0, (float) $line->quantity - (float) $line->cancelled_quantity);
                    if ($effectiveQty <= 0) {
                        continue;
                    }

                    InvoiceItem::query()->create([
                        'invoice_id' => $invoice->id,
                        'sku' => $line->sku,
                        'item_name' => $line->item_name,
                        'variant_name' => $line->variant_name,
                        'quantity' => number_format($effectiveQty, 3, '.', ''),
                        'unit_price' => $line->unit_price,
                        'modifier_unit_total' => $line->modifier_unit_total,
                        'modifiers_snapshot' => $line->modifiers->map(fn ($m) => [
                            'name' => $m->name,
                            'unit_price' => $m->unit_price,
                        ])->values()->all(),
                        'line_subtotal' => $line->line_subtotal,
                        'line_tax' => $line->line_tax,
                        'line_total' => $line->line_total,
                    ]);
                }

                $lockedOrder->update([
                    'status' => 'PAID',
                    'closed_at' => now(),
                    'version' => $lockedOrder->version + 1,
                ]);

                Audit::record($request, 'invoice.issued', $invoice, after: [
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $lockedOrder->id,
                    'total' => $invoice->total,
                ]);

                return response()->json([
                    'data' => $this->loadOrderWithBilling($lockedOrder->fresh()),
                    'invoice' => $invoice->load('items'),
                ], 201);
            });
        });
    }

    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('take-payment') || $user->can('view-reports') || $user->can('refund-payment') || $user->can('manage-floor'),
            403,
        );
        $invoices = Invoice::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['items', 'refunds.processor:id,name'])
            ->latest('issued_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $invoices]);
    }

    public function reprintInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('take-payment');
        abort_unless($invoice->restaurant_id === $request->user()->restaurant_id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $before = ['reprint_count' => $invoice->reprint_count];
        $invoice->update([
            'reprint_count' => $invoice->reprint_count + 1,
            'last_reprinted_at' => now(),
            'last_reprint_reason' => trim($validated['reason']),
        ]);

        Audit::record(
            $request,
            'invoice.reprinted',
            $invoice,
            $before,
            ['reprint_count' => $invoice->reprint_count],
            trim($validated['reason']),
        );

        return response()->json(['data' => $invoice->fresh()->load(['items', 'refunds.processor:id,name'])]);
    }

    public function voidInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        $canVoid = $user->can('refund-payment') || $user->can('manage-floor') || $user->can('manage-menu');
        abort_unless($canVoid, 403, 'Manager permission is required to void an issued invoice.');
        abort_unless($invoice->restaurant_id === $user->restaurant_id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $user, $invoice, $validated) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedInvoice->status === 'VOIDED', 409, 'This invoice has already been voided.');
            abort_if(
                $lockedInvoice->refunds()->exists(),
                409,
                'Cannot void an invoice that already has recorded refunds.',
            );

            $before = ['status' => $lockedInvoice->status];

            $lockedInvoice->update([
                'status' => 'VOIDED',
                'void_reason' => trim($validated['reason']),
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            $payments = Payment::query()
                ->where('order_id', $lockedInvoice->order_id)
                ->where('status', 'CAPTURED')
                ->get();

            $shiftIds = [];
            foreach ($payments as $payment) {
                $payment->update(['status' => 'VOIDED']);
                if ($payment->cashier_shift_id) {
                    $shiftIds[$payment->cashier_shift_id] = true;
                }
            }

            foreach (array_keys($shiftIds) as $shiftId) {
                $shift = CashierShift::query()->find($shiftId);
                if ($shift && $shift->status === 'OPEN') {
                    $this->refreshShiftExpectedCash($shift);
                }
            }

            Audit::record(
                $request,
                'invoice.voided',
                $lockedInvoice,
                $before,
                ['status' => 'VOIDED', 'invoice_number' => $lockedInvoice->invoice_number],
                trim($validated['reason']),
            );

            return response()->json([
                'data' => $lockedInvoice->fresh()->load(['items', 'refunds.processor:id,name']),
            ]);
        });
    }

    public function refundInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        $canRefund = $user->can('refund-payment') || $user->can('manage-floor') || $user->can('manage-menu');
        abort_unless($canRefund, 403, 'Manager permission is required to process a refund.');
        abort_unless($invoice->restaurant_id === $user->restaurant_id, 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
        ]);

        return Idempotency::handle($request, "invoices.{$invoice->id}.refunds", function () use ($request, $user, $invoice, $validated) {
            return DB::transaction(function () use ($request, $user, $invoice, $validated) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                abort_if($lockedInvoice->status === 'VOIDED', 409, 'Cannot refund a voided invoice.');

                $refundableBalance = round((float) $lockedInvoice->paid_amount - (float) $lockedInvoice->refunded_amount, 2);
                $requestedAmount = round((float) $validated['amount'], 2);

                abort_if(
                    $requestedAmount > $refundableBalance + 0.001,
                    409,
                    "Refund amount (৳{$requestedAmount}) exceeds the remaining refundable balance (৳{$refundableBalance}).",
                );

                $openShift = CashierShift::query()
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('status', 'OPEN')
                    ->latest('opened_at')
                    ->first();

                $refundNumber = $this->allocateRefundNumber($user->restaurant_id);

                $refund = Refund::query()->create([
                    'restaurant_id' => $user->restaurant_id,
                    'invoice_id' => $lockedInvoice->id,
                    'order_id' => $lockedInvoice->order_id,
                    'cashier_shift_id' => $openShift?->id,
                    'refund_number' => $refundNumber,
                    'method' => Str::upper($validated['method']),
                    'amount' => number_format($requestedAmount, 2, '.', ''),
                    'reason' => trim($validated['reason']),
                    'provider_reference' => isset($validated['provider_reference']) ? trim($validated['provider_reference']) : null,
                    'processed_by' => $user->id,
                    'refunded_at' => now(),
                ]);

                $newRefundedAmount = round((float) $lockedInvoice->refunded_amount + $requestedAmount, 2);
                $newStatus = abs($newRefundedAmount - (float) $lockedInvoice->paid_amount) < 0.001
                    ? 'REFUNDED'
                    : 'PARTIALLY_REFUNDED';

                $before = [
                    'status' => $lockedInvoice->status,
                    'refunded_amount' => $lockedInvoice->refunded_amount,
                ];

                $lockedInvoice->update([
                    'refunded_amount' => number_format($newRefundedAmount, 2, '.', ''),
                    'status' => $newStatus,
                ]);

                if ($openShift) {
                    $this->refreshShiftExpectedCash($openShift);
                }

                Audit::record(
                    $request,
                    'invoice.refunded',
                    $refund,
                    $before,
                    [
                        'refund_number' => $refund->refund_number,
                        'invoice_number' => $lockedInvoice->invoice_number,
                        'amount' => $refund->amount,
                        'method' => $refund->method,
                        'invoice_status' => $newStatus,
                    ],
                    trim($validated['reason']),
                );

                return response()->json([
                    'data' => $lockedInvoice->fresh()->load(['items', 'refunds.processor:id,name']),
                    'refund' => $refund->load('processor:id,name'),
                ], 201);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function capturePaymentOnLockedOrder(
        Order $lockedOrder,
        CashierShift $shift,
        User $user,
        array $data,
    ): Payment {
        OrderTotals::recalculateOrder($lockedOrder);
        $lockedOrder->refresh();

        $balanceDue = round((float) $lockedOrder->balance_due, 2);
        abort_if($balanceDue <= 0, 409, 'This order is already paid in full.');

        $method = Str::upper((string) $data['method']);
        $requestedAmount = round((float) $data['amount'], 2);
        $providerRef = isset($data['provider_reference']) ? trim((string) $data['provider_reference']) : null;

        if ($method !== 'CASH') {
            abort_if(
                $providerRef === null || mb_strlen($providerRef) < 3,
                422,
                "A provider/transaction reference is required for {$method} payments.",
            );
            abort_if(
                $requestedAmount > $balanceDue + 0.001,
                409,
                "Non-cash payment (৳{$requestedAmount}) cannot exceed the remaining balance due (৳{$balanceDue}).",
            );

            $capturedAmount = $requestedAmount;
            $tenderedAmount = $requestedAmount;
            $changeGiven = 0.0;
        } else {
            $tenderedAmount = isset($data['tendered_amount']) && $data['tendered_amount'] !== null
                ? round((float) $data['tendered_amount'], 2)
                : $requestedAmount;

            abort_if(
                $tenderedAmount + 0.001 < min($requestedAmount, $balanceDue),
                422,
                'Tendered cash cannot be less than the payment amount.',
            );

            if ($requestedAmount >= $balanceDue || $tenderedAmount > $balanceDue && $requestedAmount >= $balanceDue) {
                $capturedAmount = $balanceDue;
                $changeGiven = max(0.0, round($tenderedAmount - $balanceDue, 2));
            } else {
                $capturedAmount = $requestedAmount;
                $changeGiven = max(0.0, round($tenderedAmount - $capturedAmount, 2));
            }
        }

        $payment = Payment::query()->create([
            'restaurant_id' => $lockedOrder->restaurant_id,
            'order_id' => $lockedOrder->id,
            'cashier_shift_id' => $shift->id,
            'method' => $method,
            'amount' => number_format($capturedAmount, 2, '.', ''),
            'tendered_amount' => number_format($tenderedAmount, 2, '.', ''),
            'change_given' => number_format($changeGiven, 2, '.', ''),
            'status' => 'CAPTURED',
            'provider_reference' => $providerRef ?: null,
            'received_by' => $user->id,
            'captured_at' => now(),
        ]);

        OrderTotals::recalculateOrder($lockedOrder);
        $lockedOrder->refresh();

        $newStatus = (float) $lockedOrder->balance_due > 0.001 ? 'PARTIALLY_PAID' : $lockedOrder->status;
        $lockedOrder->update([
            'status' => $newStatus,
            'version' => $lockedOrder->version + 1,
        ]);

        return $payment;
    }

    private function requireOpenShift(User $user): CashierShift
    {
        $shift = CashierShift::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('cashier_id', $user->id)
            ->where('status', 'OPEN')
            ->latest('opened_at')
            ->first();

        abort_if($shift === null, 409, 'Open a cashier shift before recording payments or finalizing invoices.');

        return $shift;
    }

    private function refreshShiftExpectedCash(CashierShift $shift): void
    {
        $cashSales = (float) $shift->payments()
            ->where('method', 'CASH')
            ->where('status', 'CAPTURED')
            ->sum('amount');

        $cashRefunds = (float) $shift->refunds()
            ->where('method', 'CASH')
            ->sum('amount');

        $expected = round((float) $shift->opening_float + $cashSales - $cashRefunds, 2);
        $shift->update(['expected_cash' => number_format($expected, 2, '.', '')]);
    }

    private function allocateInvoiceNumber(int $restaurantId): string
    {
        $next = Invoice::query()->where('restaurant_id', $restaurantId)->count() + 1;
        $invoiceNumber = sprintf('INV-%04d', $next);

        while (Invoice::query()->where('restaurant_id', $restaurantId)->where('invoice_number', $invoiceNumber)->exists()) {
            $next++;
            $invoiceNumber = sprintf('INV-%04d', $next);
        }

        return $invoiceNumber;
    }

    private function allocateRefundNumber(int $restaurantId): string
    {
        $next = Refund::query()->where('restaurant_id', $restaurantId)->count() + 1;
        $refundNumber = sprintf('REF-%04d', $next);

        while (Refund::query()->where('restaurant_id', $restaurantId)->where('refund_number', $refundNumber)->exists()) {
            $next++;
            $refundNumber = sprintf('REF-%04d', $next);
        }

        return $refundNumber;
    }

    private function loadOrderWithBilling(Order $order): Order
    {
        return $order->load([
            'table.zone:id,name',
            'waiter:id,name',
            'items' => fn ($q) => $q->with('modifiers')->orderBy('id'),
            'kots' => fn ($q) => $q->with(['round:id,round_number,sent_by,sent_at', 'items'])->latest('sent_at'),
            'discounts',
            'payments' => fn ($q) => $q->with('receiver:id,name')->orderBy('id'),
            'invoice.items',
            'transfers' => fn ($q) => $q->with(['fromTable:id,code,display_name', 'toTable:id,code,display_name', 'actor:id,name'])->latest('transferred_at'),
        ]);
    }

    private function assertRestaurant(Request $request, Order $order): void
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 404);
    }

    private function assertOpenOrder(Order $order, int $expectedVersion): void
    {
        abort_unless(
            in_array($order->status, Order::OPEN_STATUSES, true),
            409,
            "Order {$order->order_number} is no longer open.",
        );

        abort_if(
            $order->version !== $expectedVersion,
            409,
            "Order {$order->order_number} was changed by another user (current version {$order->version}). Reload and review before saving.",
        );
    }
}
