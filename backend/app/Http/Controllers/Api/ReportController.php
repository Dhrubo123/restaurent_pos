<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        $restaurantId = $request->user()->restaurant_id;
        $restaurantTimezone = $request->user()->restaurant?->timezone ?? 'Asia/Dhaka';

        [$fromDate, $toDate] = $this->resolveDateRange($request, $restaurantTimezone);
        $canViewVoids = $this->hasUnlimitedReportAccess($request);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('issued_at', [$fromDate, $toDate])
            ->when(! $canViewVoids, fn ($query) => $query->where('status', '!=', 'VOIDED'))
            ->with(['items', 'refunds.processor:id,name'])
            ->latest('issued_at')
            ->get();

        $activeInvoices = $invoices->where('status', '!=', 'VOIDED');
        $voidedInvoices = $invoices->where('status', 'VOIDED');

        $grossSubtotal = round((float) $activeInvoices->sum(fn (Invoice $i) => (float) $i->subtotal), 2);
        $discountTotal = round((float) $activeInvoices->sum(fn (Invoice $i) => (float) $i->discount_amount), 2);
        $taxTotal = round((float) $activeInvoices->sum(fn (Invoice $i) => (float) $i->tax_amount), 2);
        $billedTotal = round((float) $activeInvoices->sum(fn (Invoice $i) => (float) $i->total), 2);
        $refundedTotal = round((float) $activeInvoices->sum(fn (Invoice $i) => (float) $i->refunded_amount), 2);
        $voidedTotal = round((float) $voidedInvoices->sum(fn (Invoice $i) => (float) $i->total), 2);
        $netRevenue = round($billedTotal - $refundedTotal, 2);
        $ownerDueTotal = round((float) $activeInvoices
            ->filter(fn (Invoice $invoice) => $invoice->owner_account)
            ->sum(fn (Invoice $invoice) => max(0.0, (float) $invoice->total - (float) $invoice->paid_amount)), 2);
        $invoicesCount = $activeInvoices->count();
        $guestCount = (int) $activeInvoices->sum('guest_count');
        $avgCheck = $invoicesCount > 0 ? round($netRevenue / $invoicesCount, 2) : 0.0;

        $capturedByMethod = Payment::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'CAPTURED')
            ->whereBetween('captured_at', [$fromDate, $toDate])
            ->whereHas('order.invoice', fn ($query) => $query->where('status', '!=', 'VOIDED'))
            ->selectRaw('method, SUM(amount) as total_amount')
            ->groupBy('method')
            ->pluck('total_amount', 'method');

        $refundedByMethod = Refund::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('refunded_at', [$fromDate, $toDate])
            ->selectRaw('method, SUM(amount) as total_amount')
            ->groupBy('method')
            ->pluck('total_amount', 'method');

        $paymentsByMethod = [];
        foreach (Payment::METHODS as $method) {
            $captured = round((float) ($capturedByMethod[$method] ?? 0), 2);
            $refunded = round((float) ($refundedByMethod[$method] ?? 0), 2);
            $paymentsByMethod[$method] = [
                'captured' => number_format($captured, 2, '.', ''),
                'refunded' => number_format($refunded, 2, '.', ''),
                'net' => number_format(round($captured - $refunded, 2), 2, '.', ''),
            ];
        }
        $collectedTotal = round(array_sum(array_map(
            fn (array $methodTotals): float => (float) $methodTotals['net'],
            $paymentsByMethod,
        )), 2);

        $stationSales = $this->stationSales($activeInvoices, $restaurantTimezone);

        $dailyBreakdown = $activeInvoices
            ->groupBy(fn (Invoice $inv) => $inv->issued_at?->copy()->setTimezone($restaurantTimezone)->format('Y-m-d') ?? now($restaurantTimezone)->format('Y-m-d'))
            ->map(function ($dayInvoices, $date) {
                $billed = round((float) $dayInvoices->sum(fn (Invoice $i) => (float) $i->total), 2);
                $disc = round((float) $dayInvoices->sum(fn (Invoice $i) => (float) $i->discount_amount), 2);
                $tax = round((float) $dayInvoices->sum(fn (Invoice $i) => (float) $i->tax_amount), 2);
                $ref = round((float) $dayInvoices->sum(fn (Invoice $i) => (float) $i->refunded_amount), 2);

                return [
                    'date' => $date,
                    'invoices_count' => $dayInvoices->count(),
                    'billed_total' => number_format($billed, 2, '.', ''),
                    'discount_total' => number_format($disc, 2, '.', ''),
                    'tax_total' => number_format($tax, 2, '.', ''),
                    'refunded_total' => number_format($ref, 2, '.', ''),
                    'net_revenue' => number_format(round($billed - $ref, 2), 2, '.', ''),
                ];
            })
            ->sortKeysDesc()
            ->values()
            ->all();

        $refunds = Refund::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('refunded_at', [$fromDate, $toDate])
            ->with(['invoice:id,invoice_number,table_code', 'processor:id,name'])
            ->latest('refunded_at')
            ->limit(50)
            ->get();

        $voidedItems = $canViewVoids
            ? $this->voidedItemLedger($restaurantId, $fromDate, $toDate)
            : [];

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $fromDate->copy()->setTimezone($restaurantTimezone)->format('Y-m-d'),
                    'to' => $toDate->copy()->setTimezone($restaurantTimezone)->format('Y-m-d'),
                ],
                'kpis' => [
                    'invoices_count' => $invoicesCount,
                    'voided_invoices_count' => $voidedInvoices->count(),
                    'guest_count' => $guestCount,
                    'gross_subtotal' => number_format($grossSubtotal, 2, '.', ''),
                    'discount_total' => number_format($discountTotal, 2, '.', ''),
                    'tax_total' => number_format($taxTotal, 2, '.', ''),
                    'billed_total' => number_format($billedTotal, 2, '.', ''),
                    'collected_total' => number_format($collectedTotal, 2, '.', ''),
                    'owner_due_total' => number_format($ownerDueTotal, 2, '.', ''),
                    'refunded_total' => number_format($refundedTotal, 2, '.', ''),
                    'voided_total' => number_format($voidedTotal, 2, '.', ''),
                    'net_revenue' => number_format($netRevenue, 2, '.', ''),
                    'avg_check' => number_format($avgCheck, 2, '.', ''),
                ],
                'payments_by_method' => $paymentsByMethod,
                'station_sales' => $stationSales,
                'daily_breakdown' => $dailyBreakdown,
                'invoices' => $invoices->values()->all(),
                'refunds' => $refunds,
                'voided_items' => $voidedItems,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeReports($request);
        $restaurantId = $request->user()->restaurant_id;
        $restaurantTimezone = $request->user()->restaurant?->timezone ?? 'Asia/Dhaka';

        [$fromDate, $toDate] = $this->resolveDateRange($request, $restaurantTimezone);
        $canViewVoids = $this->hasUnlimitedReportAccess($request);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('issued_at', [$fromDate, $toDate])
            ->when(! $canViewVoids, fn ($query) => $query->where('status', '!=', 'VOIDED'))
            ->orderBy('issued_at')
            ->get();

        $filename = sprintf(
            'sales-report-%s-to-%s.csv',
            $fromDate->copy()->setTimezone($restaurantTimezone)->format('Y-m-d'),
            $toDate->copy()->setTimezone($restaurantTimezone)->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($invoices, $canViewVoids) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Invoice Number',
                'Issued At',
                'Status',
                'Table',
                'Guests',
                'Waiter',
                'Cashier',
                'Subtotal (BDT)',
                'Discount (BDT)',
                'Tax (BDT)',
                'Billed Total (BDT)',
                'Refunded (BDT)',
                'Net Revenue (BDT)',
                ...($canViewVoids ? ['Void Reason'] : []),
            ]);

            foreach ($invoices as $invoice) {
                $isVoided = $invoice->status === 'VOIDED';
                $net = $isVoided
                    ? 0.0
                    : round((float) $invoice->total - (float) $invoice->refunded_amount, 2);

                fputcsv($out, [
                    $invoice->invoice_number,
                    $invoice->issued_at?->toIso8601String(),
                    $invoice->status,
                    $invoice->table_code,
                    $invoice->guest_count,
                    $invoice->waiter_name,
                    $invoice->cashier_name,
                    $invoice->subtotal,
                    $invoice->discount_amount,
                    $invoice->tax_amount,
                    $invoice->total,
                    $invoice->refunded_amount,
                    number_format($net, 2, '.', ''),
                    ...($canViewVoids ? [$invoice->void_reason ?? ''] : []),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Report Footer', 'Powered by Clique IT', 'Generated At', now()->toIso8601String()]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function auditEvents(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        $restaurantId = $request->user()->restaurant_id;

        $actionFilter = trim((string) $request->query('action', ''));
        $search = trim((string) $request->query('search', ''));

        $query = AuditEvent::query()
            ->where('restaurant_id', $restaurantId)
            ->with('actor:id,name')
            ->latest('id');

        if (! $this->hasUnlimitedReportAccess($request)) {
            $restaurantTimezone = $request->user()->restaurant?->timezone ?? 'Asia/Dhaka';
            $query->where('created_at', '>=', now($restaurantTimezone)->subDays(6)->startOfDay()->utc());
            $query->whereNotIn('action', ['order_item.sent_cancelled', 'order.cancelled', 'invoice.voided']);
        }

        if ($actionFilter !== '') {
            $query->where('action', 'like', $actionFilter.'%');
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('action', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%');
            });
        }

        $events = $query->limit(100)->get()->map(fn (AuditEvent $e) => [
            'id' => $e->id,
            'action' => $e->action,
            'actor_name' => $e->actor?->name ?? 'System',
            'auditable_type' => class_basename($e->auditable_type),
            'auditable_id' => $e->auditable_id,
            'reason' => $e->reason,
            'before' => $e->before,
            'after' => $e->after,
            'created_at' => $e->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Build the owner/admin-only ledger for sent items voided by supervisors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function voidedItemLedger(int $restaurantId, Carbon $fromDate, Carbon $toDate): array
    {
        $events = AuditEvent::query()
            ->where('restaurant_id', $restaurantId)
            ->where('action', 'order_item.sent_cancelled')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with('actor:id,name')
            ->latest('id')
            ->limit(200)
            ->get();

        $items = OrderItem::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $events->pluck('auditable_id'))
            ->with([
                'order.table:id,code,display_name',
                'order.items' => fn ($q) => $q->where('status', '!=', 'CANCELLED')->orderBy('id'),
                'order.kots' => fn ($q) => $q->orderBy('id'),
                'kotItems.kot:id,kot_number,station,status',
            ])
            ->get()
            ->keyBy('id');

        return $events->map(function (AuditEvent $event) use ($items): array {
            /** @var OrderItem|null $item */
            $item = $items->get($event->auditable_id);
            $kots = $item?->kotItems
                ->map(fn ($kotItem) => $kotItem->kot)
                ->filter()
                ->unique('id')
                ->values() ?? collect();

            $voidKotIds = $kots->pluck('id')->all();
            $replacementItems = ($item?->order?->items ?? collect())
                ->where('id', '!=', $item?->id)
                ->map(fn (OrderItem $activeLine): array => [
                    'id' => $activeLine->id,
                    'item_name' => $activeLine->item_name,
                    'variant_name' => $activeLine->variant_name,
                    'quantity' => (string) $activeLine->quantity,
                    'line_total' => (string) $activeLine->line_total,
                ])
                ->values()
                ->all();

            $replacementKots = ($item?->order?->kots ?? collect())
                ->whereNotIn('id', $voidKotIds)
                ->map(fn ($kot): array => [
                    'id' => $kot->id,
                    'kot_number' => $kot->kot_number,
                    'station' => $kot->station,
                    'status' => $kot->status,
                ])
                ->values()
                ->all();

            return [
                'id' => $event->id,
                'order_item_id' => $event->auditable_id,
                'order_number' => $item?->order?->order_number ?? 'Unknown order',
                'table_code' => $item?->order?->table?->code ?? 'Takeaway',
                'table_name' => $item?->order?->table?->display_name,
                'item_name' => $item?->item_name ?? 'Deleted menu item',
                'variant_name' => $item?->variant_name,
                'station' => $item?->station ?? $kots->first()?->station,
                'quantity' => (string) ($item?->cancelled_quantity ?? $event->after['cancelled_quantity'] ?? 0),
                'original_total' => number_format((float) ($event->before['line_total'] ?? 0), 2, '.', ''),
                'reason' => $event->reason,
                'voided_by' => $event->actor?->name ?? 'System',
                'voided_at' => $event->created_at?->toIso8601String(),
                'kots' => $kots->map(fn ($kot): array => [
                    'id' => $kot->id,
                    'kot_number' => $kot->kot_number,
                    'station' => $kot->station,
                    'status' => $kot->status,
                ])->all(),
                'replacement_items' => $replacementItems,
                'replacement_kots' => $replacementKots,
            ];
        })->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function stationSales(Collection $invoices, string $restaurantTimezone): array
    {
        $sales = [];
        foreach (['KITCHEN', 'BAR', 'UNASSIGNED'] as $station) {
            $sales[$station] = [
                'billed_cents' => 0,
                'gross_cents' => 0,
                'quantity' => 0.0,
                'days' => [],
                'items' => [],
            ];
        }

        foreach ($invoices as $invoice) {
            $lines = $invoice->items->values();
            $basis = (float) $lines->sum('line_subtotal');
            $remainingCents = (int) round((float) $invoice->total * 100);
            $date = $invoice->issued_at?->copy()->setTimezone($restaurantTimezone)->format('Y-m-d');

            if ($lines->isEmpty()) {
                $sales['UNASSIGNED']['billed_cents'] += $remainingCents;
                if ($date) {
                    $sales['UNASSIGNED']['days'][$date] ??= ['billed_cents' => 0, 'quantity' => 0.0];
                    $sales['UNASSIGNED']['days'][$date]['billed_cents'] += $remainingCents;
                }

                continue;
            }

            foreach ($lines as $index => $line) {
                $grossCents = (int) round((float) $line->line_subtotal * 100);
                $billedCents = $index === $lines->count() - 1
                    ? $remainingCents
                    : ($basis > 0 ? (int) round((float) $invoice->total * 100 * (float) $line->line_subtotal / $basis) : 0);
                $remainingCents -= $billedCents;
                $station = in_array($line->station, ['KITCHEN', 'BAR'], true) ? $line->station : 'UNASSIGNED';
                $sales[$station]['billed_cents'] += $billedCents;
                $sales[$station]['gross_cents'] += $grossCents;
                $sales[$station]['quantity'] += (float) $line->quantity;

                if ($date) {
                    $sales[$station]['days'][$date] ??= ['billed_cents' => 0, 'quantity' => 0.0];
                    $sales[$station]['days'][$date]['billed_cents'] += $billedCents;
                    $sales[$station]['days'][$date]['quantity'] += (float) $line->quantity;
                }

                $key = $line->sku."\0".$line->item_name;
                $sales[$station]['items'][$key] ??= [
                    'sku' => $line->sku,
                    'item_name' => $line->item_name,
                    'quantity' => 0.0,
                    'gross_cents' => 0,
                ];
                $sales[$station]['items'][$key]['quantity'] += (float) $line->quantity;
                $sales[$station]['items'][$key]['gross_cents'] += $grossCents;
            }
        }

        foreach ($sales as &$station) {
            $days = $station['days'];
            krsort($days);
            $items = array_values($station['items']);
            usort($items, fn (array $a, array $b): int => $b['gross_cents'] <=> $a['gross_cents']);

            $station = [
                'billed_total' => number_format($station['billed_cents'] / 100, 2, '.', ''),
                'gross_subtotal' => number_format($station['gross_cents'] / 100, 2, '.', ''),
                'quantity_sold' => number_format($station['quantity'], 3, '.', ''),
                'daily_breakdown' => collect($days)->map(fn (array $day, string $date): array => [
                    'date' => $date,
                    'billed_total' => number_format($day['billed_cents'] / 100, 2, '.', ''),
                    'quantity_sold' => number_format($day['quantity'], 3, '.', ''),
                ])->values()->all(),
                'items' => collect($items)->map(fn (array $item): array => [
                    'sku' => $item['sku'],
                    'item_name' => $item['item_name'],
                    'quantity_sold' => number_format($item['quantity'], 3, '.', ''),
                    'gross_revenue' => number_format($item['gross_cents'] / 100, 2, '.', ''),
                ])->all(),
            ];
        }
        unset($station);

        return $sales;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request, string $restaurantTimezone): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::createFromFormat('Y-m-d', $validated['from'], $restaurantTimezone)->startOfDay()
            : now($restaurantTimezone)->subDays(6)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::createFromFormat('Y-m-d', $validated['to'], $restaurantTimezone)->endOfDay()
            : now($restaurantTimezone)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if (! $this->hasUnlimitedReportAccess($request)) {
            $today = now($restaurantTimezone)->endOfDay();
            $earliestDay = $today->copy()->subDays(6)->startOfDay();
            abort_if(
                $from->lt($earliestDay) || $to->gt($today),
                403,
                'Supervisors can view reports only for dates within the most recent 7 days.',
            );
        }

        return [$from->utc(), $to->utc()];
    }

    private function hasUnlimitedReportAccess(Request $request): bool
    {
        return $request->user()->roles()->where('key', 'admin')->exists();
    }

    private function authorizeReports(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->can('view-reports') || $user->can('manage-floor') || $user->can('manage-menu'),
            403,
            'Report permission is required to view financial reports and audit logs.',
        );
    }
}
