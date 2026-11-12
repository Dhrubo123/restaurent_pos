<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        $restaurantId = $request->user()->restaurant_id;

        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('issued_at', [$fromDate, $toDate])
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
        $invoicesCount = $activeInvoices->count();
        $guestCount = (int) $activeInvoices->sum('guest_count');
        $avgCheck = $invoicesCount > 0 ? round($netRevenue / $invoicesCount, 2) : 0.0;

        $activeOrderIds = $activeInvoices->pluck('order_id')->all();
        $activeInvoiceIds = $activeInvoices->pluck('id')->all();

        $capturedByMethod = empty($activeOrderIds)
            ? collect()
            : Payment::query()
                ->where('restaurant_id', $restaurantId)
                ->whereIn('order_id', $activeOrderIds)
                ->where('status', 'CAPTURED')
                ->selectRaw('method, SUM(amount) as total_amount')
                ->groupBy('method')
                ->pluck('total_amount', 'method');

        $refundedByMethod = empty($activeInvoiceIds)
            ? collect()
            : Refund::query()
                ->where('restaurant_id', $restaurantId)
                ->whereIn('invoice_id', $activeInvoiceIds)
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

        $topItems = empty($activeInvoiceIds)
            ? []
            : InvoiceItem::query()
                ->whereIn('invoice_id', $activeInvoiceIds)
                ->selectRaw('sku, item_name, SUM(quantity) as quantity_sold, SUM(line_subtotal) as gross_revenue')
                ->groupBy('sku', 'item_name')
                ->orderByDesc('gross_revenue')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'sku' => $row->sku,
                    'item_name' => $row->item_name,
                    'quantity_sold' => number_format((float) $row->quantity_sold, 0, '.', ''),
                    'gross_revenue' => number_format((float) $row->gross_revenue, 2, '.', ''),
                ])
                ->values()
                ->all();

        $dailyBreakdown = $activeInvoices
            ->groupBy(fn (Invoice $inv) => $inv->issued_at?->format('Y-m-d') ?? now()->format('Y-m-d'))
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

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
                ],
                'kpis' => [
                    'invoices_count' => $invoicesCount,
                    'voided_invoices_count' => $voidedInvoices->count(),
                    'guest_count' => $guestCount,
                    'gross_subtotal' => number_format($grossSubtotal, 2, '.', ''),
                    'discount_total' => number_format($discountTotal, 2, '.', ''),
                    'tax_total' => number_format($taxTotal, 2, '.', ''),
                    'billed_total' => number_format($billedTotal, 2, '.', ''),
                    'refunded_total' => number_format($refundedTotal, 2, '.', ''),
                    'voided_total' => number_format($voidedTotal, 2, '.', ''),
                    'net_revenue' => number_format($netRevenue, 2, '.', ''),
                    'avg_check' => number_format($avgCheck, 2, '.', ''),
                ],
                'payments_by_method' => $paymentsByMethod,
                'top_items' => $topItems,
                'daily_breakdown' => $dailyBreakdown,
                'invoices' => $invoices->values()->all(),
                'refunds' => $refunds,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeReports($request);
        $restaurantId = $request->user()->restaurant_id;

        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('issued_at', [$fromDate, $toDate])
            ->orderBy('issued_at')
            ->get();

        $filename = sprintf('sales-report-%s-to-%s.csv', $fromDate->format('Y-m-d'), $toDate->format('Y-m-d'));

        return response()->streamDownload(function () use ($invoices) {
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
                'Void Reason',
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
                    $invoice->void_reason ?? '',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Report Footer', 'Developed by Tech Base', 'Generated At', now()->toIso8601String()]);

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
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay()
            : now()->subDays(6)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::createFromFormat('Y-m-d', $validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function authorizeReports(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->can('view-reports') || $user->can('manage-floor') || $user->can('manage-menu'),
            403,
            'Manager permission is required to view financial reports and audit logs.',
        );
    }
}
