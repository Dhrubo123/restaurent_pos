import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import type { InvoiceRecord, RefundRecord } from './InvoicePrintModal.tsx'

function cafeDate(timestamp: number): string {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Dhaka',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date(timestamp))
  const value = (type: string) => parts.find((part) => part.type === type)?.value ?? ''
  return `${value('year')}-${value('month')}-${value('day')}`
}

export type ReportSummaryData = {
  range: { from: string; to: string }
  kpis: {
    invoices_count: number
    voided_invoices_count: number
    guest_count: number
    gross_subtotal: string
    discount_total: string
    tax_total: string
    billed_total: string
    collected_total: string
    owner_due_total: string
    refunded_total: string
    voided_total: string
    net_revenue: string
    avg_check: string
  }
  payments_by_method: Record<
    'CASH' | 'CARD' | 'BKASH' | 'NAGAD',
    { captured: string; refunded: string; net: string }
  >
  station_sales: Record<'KITCHEN' | 'BAR' | 'UNASSIGNED', {
    billed_total: string
    gross_subtotal: string
    quantity_sold: string
    daily_breakdown: { date: string; billed_total: string; quantity_sold: string }[]
    items: {
    sku: string
    item_name: string
    quantity_sold: string
    gross_revenue: string
    }[]
  }>
  daily_breakdown: {
    date: string
    invoices_count: number
    billed_total: string
    discount_total: string
    tax_total: string
    refunded_total: string
    net_revenue: string
  }[]
  invoices: InvoiceRecord[]
  refunds: RefundRecord[]
  voided_items: {
    id: number
    order_item_id: number
    order_number: string
    table_code: string
    table_name: string | null
    item_name: string
    variant_name: string | null
    station: 'KITCHEN' | 'BAR' | null
    quantity: string
    original_total: string
    reason: string | null
    voided_by: string
    voided_at: string
    kots: { id: number; kot_number: string; station: 'KITCHEN' | 'BAR'; status: string }[]
    replacement_items?: {
      id: number
      item_name: string
      variant_name: string | null
      quantity: string
      line_total: string
    }[]
    replacement_kots?: { id: number; kot_number: string; station: 'KITCHEN' | 'BAR'; status: string }[]
  }[]
}

export type AuditEventRecord = {
  id: number
  action: string
  actor_name: string
  auditable_type: string
  auditable_id: number
  reason: string | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  created_at: string
}

type Props = {
  restaurantName: string
  report: ReportSummaryData | null
  auditEvents: AuditEventRecord[]
  canRefundOrVoid: boolean
  canViewAllReportDates: boolean
  busy: boolean
  onLoadReport: (from: string, to: string) => Promise<void>
  onLoadAudit: (action: string, search: string) => Promise<void>
  onExportCsv: (from: string, to: string) => void
  onVoidInvoice: (invoice: InvoiceRecord, reason: string) => Promise<boolean>
  onRefundInvoice: (
    invoice: InvoiceRecord,
    payload: {
      amount: number
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      reason: string
      provider_reference?: string
    },
  ) => Promise<boolean>
  onViewInvoice: (invoice: InvoiceRecord) => void
}

export default function ReportsWorkspace({
  restaurantName,
  report,
  auditEvents,
  canRefundOrVoid,
  canViewAllReportDates,
  busy,
  onLoadReport,
  onLoadAudit,
  onExportCsv,
  onVoidInvoice,
  onRefundInvoice,
  onViewInvoice,
}: Props) {
  const todayStr = cafeDate(Date.now())
  const sevenDaysAgoStr = cafeDate(Date.now() - 6 * 86400000)

  const [subTab, setSubTab] = useState<'overview' | 'kitchen' | 'bar' | 'invoices' | 'voids' | 'audit'>('overview')
  const [fromDate, setFromDate] = useState(report?.range.from ?? sevenDaysAgoStr)
  const [toDate, setToDate] = useState(report?.range.to ?? todayStr)
  const invalidSupervisorRange = !canViewAllReportDates && (
    fromDate < sevenDaysAgoStr || toDate > todayStr || fromDate > toDate
  )

  const [invoiceSearch, setInvoiceSearch] = useState('')
  const [auditAction, setAuditAction] = useState('')
  const [auditSearch, setAuditSearch] = useState('')

  const [voidModal, setVoidModal] = useState<{ invoice: InvoiceRecord; reason: string } | null>(null)
  const [refundModal, setRefundModal] = useState<{
    invoice: InvoiceRecord
    amount: string
    method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
    reason: string
    providerReference: string
  } | null>(null)

  const applyPreset = (daysBack: number) => {
    const end = cafeDate(Date.now())
    const start = cafeDate(Date.now() - daysBack * 86400000)
    setFromDate(start)
    setToDate(end)
    void onLoadReport(start, end)
  }

  const applyYesterday = () => {
    const y = cafeDate(Date.now() - 86400000)
    setFromDate(y)
    setToDate(y)
    void onLoadReport(y, y)
  }

  const applyThisMonth = () => {
    const end = cafeDate(Date.now())
    const start = `${end.slice(0, 7)}-01`
    setFromDate(start)
    setToDate(end)
    void onLoadReport(start, end)
  }

  const handleFilterSubmit = (event: FormEvent) => {
    event.preventDefault()
    void onLoadReport(fromDate, toDate)
  }

  const handleAuditFilter = (nextAction: string, nextSearch = auditSearch) => {
    setAuditAction(nextAction)
    void onLoadAudit(nextAction, nextSearch)
  }

  const filteredInvoices = useMemo(() => {
    const list = report?.invoices ?? []
    const q = invoiceSearch.trim().toLowerCase()
    if (!q) return list
    return list.filter(
      (inv) =>
        inv.invoice_number.toLowerCase().includes(q) ||
        inv.table_code.toLowerCase().includes(q) ||
        inv.status.toLowerCase().includes(q) ||
        (inv.cashier_name ?? '').toLowerCase().includes(q),
    )
  }, [report?.invoices, invoiceSearch])

  const submitVoid = async (event: FormEvent) => {
    event.preventDefault()
    if (!voidModal) return
    const ok = await onVoidInvoice(voidModal.invoice, voidModal.reason)
    if (ok) setVoidModal(null)
  }

  const submitRefund = async (event: FormEvent) => {
    event.preventDefault()
    if (!refundModal) return
    const ok = await onRefundInvoice(refundModal.invoice, {
      amount: Number(refundModal.amount),
      method: refundModal.method,
      reason: refundModal.reason,
      provider_reference: refundModal.providerReference.trim() || undefined,
    })
    if (ok) setRefundModal(null)
  }

  const kpis = report?.kpis

  return (
    <div className="reports-workspace">
      <header>
        <div>
          <p className="eyebrow coral">REPORTS, REFUNDS & AUDIT TRAIL</p>
          <h1>Financial reconciliation & control.</h1>
          <p className="muted">
            {restaurantName} · Server-authoritative sales, immutable voids/refunds & audit log
          </p>
        </div>
        <div className="header-actions">
          <button
            type="button"
            className="secondary compact"
            disabled={busy}
            onClick={() => window.print()}
          >
            🖨 Print Report
          </button>
          <button
            type="button"
            className="secondary compact"
            disabled={busy || invalidSupervisorRange}
            onClick={() => onExportCsv(fromDate, toDate)}
          >
            ⬇ Export CSV ({fromDate} → {toDate})
          </button>
          <button
            type="button"
            className="primary compact"
            disabled={busy || invalidSupervisorRange}
            onClick={() => {
              void onLoadReport(fromDate, toDate)
              void onLoadAudit(auditAction, auditSearch)
            }}
          >
            ↻ Refresh
          </button>
        </div>
      </header>

      <div className="reports-toolbar">
        <div className="zone-tabs" style={{ marginBottom: 0 }}>
          <button
            type="button"
            className={subTab === 'overview' ? 'active' : ''}
            onClick={() => setSubTab('overview')}
          >
            ◒ Sales & Tender Summary
          </button>
          <button type="button" className={subTab === 'kitchen' ? 'active' : ''} onClick={() => setSubTab('kitchen')}>
            Kitchen Sales
          </button>
          <button type="button" className={subTab === 'bar' ? 'active' : ''} onClick={() => setSubTab('bar')}>
            Bar Sales
          </button>
          <button
            type="button"
            className={subTab === 'invoices' ? 'active' : ''}
            onClick={() => setSubTab('invoices')}
          >
            🧾 Invoices & Refunds{canViewAllReportDates ? ' & Voids' : ''}
            {report && <span>{report.invoices.length}</span>}
          </button>
          {canViewAllReportDates && (
            <button
              type="button"
              className={subTab === 'voids' ? 'active' : ''}
              onClick={() => setSubTab('voids')}
            >
              ⊘ Voided Items
              {report && <span>{report.voided_items.length}</span>}
            </button>
          )}
          <button
            type="button"
            className={subTab === 'audit' ? 'active' : ''}
            onClick={() => {
              setSubTab('audit')
              void onLoadAudit(auditAction, auditSearch)
            }}
          >
            🛡 Immutable Audit Log
            <span>{auditEvents.length}</span>
          </button>
        </div>

        {subTab !== 'audit' && (
          <form className="date-range-bar" onSubmit={handleFilterSubmit}>
            <div className="quick-cash-pills">
              <button type="button" onClick={() => applyPreset(0)}>
                Today
              </button>
              <button type="button" onClick={applyYesterday}>
                Yesterday
              </button>
              <button type="button" onClick={() => applyPreset(6)}>
                7 Days
              </button>
              {canViewAllReportDates && <button type="button" onClick={() => applyPreset(29)}>30 Days</button>}
              {canViewAllReportDates && <button type="button" onClick={applyThisMonth}>This Month</button>}
            </div>
            <input
              type="date"
              value={fromDate}
              min={canViewAllReportDates ? undefined : sevenDaysAgoStr}
              max={canViewAllReportDates ? undefined : todayStr}
              onChange={(e) => setFromDate(e.target.value)}
              aria-label="From date"
            />
            <span className="muted">to</span>
            <input
              type="date"
              value={toDate}
              min={canViewAllReportDates ? undefined : sevenDaysAgoStr}
              max={canViewAllReportDates ? undefined : todayStr}
              onChange={(e) => setToDate(e.target.value)}
              aria-label="To date"
            />
            <button type="submit" className="secondary compact" disabled={busy || invalidSupervisorRange}>
              Apply
            </button>
            {!canViewAllReportDates && <span className="muted">Supervisor: most recent 7 days only</span>}
          </form>
        )}
      </div>

      {subTab === 'overview' && kpis && (
        <>
          <section className="summary-grid reports-summary-grid">
            <article>
              <span className="summary-icon blue">▤</span>
              <div>
                <strong>৳{Number(kpis.billed_total).toFixed(0)}</strong>
                <p>Billed Sales · {kpis.invoices_count} checks</p>
              </div>
            </article>
            <article>
              <span className="summary-icon green">৳</span>
              <div>
                <strong>৳{Number(kpis.collected_total).toFixed(0)}</strong>
                <p>Collected in selected period · after refunds</p>
              </div>
            </article>
            <article>
              <span className="summary-icon amber">◷</span>
              <div>
                <strong>৳{Number(kpis.owner_due_total).toFixed(0)}</strong>
                <p>Owner Due · outstanding on bills in range</p>
              </div>
            </article>
            <article>
              <span className="summary-icon green">↗</span>
              <div>
                <strong>৳{Number(kpis.net_revenue).toFixed(0)}</strong>
                <p>Net Revenue · billed less refunds</p>
              </div>
            </article>
            <article>
              <span className="summary-icon amber">%</span>
              <div>
                <strong>৳{Number(kpis.discount_total).toFixed(0)}</strong>
                <p>Discounts · Tax ৳{Number(kpis.tax_total).toFixed(0)}</p>
              </div>
            </article>
            <article>
              <span className="summary-icon ink">↩</span>
              <div>
                <strong>
                  ৳{(Number(kpis.refunded_total) + Number(kpis.voided_total)).toFixed(0)}
                </strong>
                <p>
                  Refunded ৳{Number(kpis.refunded_total).toFixed(0)}
                  {canViewAllReportDates && ` · ${kpis.voided_invoices_count} Voided`}
                </p>
              </div>
            </article>
            {canViewAllReportDates && (
              <article className={report.voided_items.length > 0 ? 'void-alert-card' : ''}>
                <span className="summary-icon danger">⊘</span>
                <div>
                  <strong>{report.voided_items.length}</strong>
                  <p>Voided Items · owner/admin control</p>
                  <button type="button" className="link-button" onClick={() => setSubTab('voids')}>Review reasons →</button>
                </div>
              </article>
            )}
          </section>

          <div className="reports-two-col">
            <section className="zone-section">
              <div className="zone-title">
                <h2>Daily Collections by Payment Method</h2>
                <span>Payments and refunds dated in the selected period</span>
              </div>
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>Method</th>
                      <th>Captured</th>
                      <th>Refunded</th>
                      <th>Net Collected</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(['CASH', 'CARD', 'BKASH', 'NAGAD'] as const).map((m) => {
                      const row = report.payments_by_method[m]
                      return (
                        <tr key={m}>
                          <td>
                            <strong>{m}</strong>
                          </td>
                          <td>৳{Number(row.captured).toFixed(2)}</td>
                          <td className={Number(row.refunded) > 0 ? 'danger-link' : ''}>
                            {Number(row.refunded) > 0 ? `−৳${Number(row.refunded).toFixed(2)}` : '৳0.00'}
                          </td>
                          <td>
                            <strong>৳{Number(row.net).toFixed(2)}</strong>
                          </td>
                        </tr>
                      )
                    })}
                    <tr>
                      <td><strong>Total Collected</strong></td>
                      <td colSpan={2}>All payment methods after refunds</td>
                      <td><strong>৳{Number(kpis.collected_total).toFixed(2)}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section className="zone-section">
              <div className="zone-title">
                <h2>Sales by Station</h2>
                <span>Open each report for its own daily and item sales</span>
              </div>
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead><tr><th>Station</th><th>Items sold</th><th>Billed sales</th><th>Report</th></tr></thead>
                  <tbody>
                    {(['KITCHEN', 'BAR'] as const).map((station) => (
                      <tr key={station}>
                        <td><strong>{station === 'KITCHEN' ? 'Kitchen' : 'Bar'}</strong></td>
                        <td>{Number(report.station_sales[station].quantity_sold).toLocaleString()}</td>
                        <td><strong>৳{Number(report.station_sales[station].billed_total).toFixed(2)}</strong></td>
                        <td><button type="button" className="secondary compact" onClick={() => setSubTab(station === 'KITCHEN' ? 'kitchen' : 'bar')}>View</button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {Number(report.station_sales.UNASSIGNED.billed_total) > 0 && (
                <p className="muted">৳{Number(report.station_sales.UNASSIGNED.billed_total).toFixed(2)} from older bills could not be assigned to a station.</p>
              )}
            </section>
          </div>

          <section className="zone-section">
            <div className="zone-title">
              <h2>Daily Sales Breakdown</h2>
              <span>{report.daily_breakdown.length} active days</span>
            </div>
            {report.daily_breakdown.length === 0 ? (
              <div className="inline-empty">No invoices issued during the selected period.</div>
            ) : (
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Invoices</th>
                      <th>Discounts</th>
                      <th>VAT / Tax</th>
                      <th>Billed Total</th>
                      <th>Refunded</th>
                      <th>Net Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.daily_breakdown.map((day) => (
                      <tr key={day.date}>
                        <td>
                          <strong>{day.date}</strong>
                        </td>
                        <td>{day.invoices_count}</td>
                        <td>৳{Number(day.discount_total).toFixed(2)}</td>
                        <td>৳{Number(day.tax_total).toFixed(2)}</td>
                        <td>৳{Number(day.billed_total).toFixed(2)}</td>
                        <td>
                          {Number(day.refunded_total) > 0
                            ? `−৳${Number(day.refunded_total).toFixed(2)}`
                            : '৳0.00'}
                        </td>
                        <td>
                          <strong>৳{Number(day.net_revenue).toFixed(2)}</strong>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}

      {(subTab === 'kitchen' || subTab === 'bar') && report && (() => {
        const station = subTab === 'kitchen' ? 'KITCHEN' : 'BAR'
        const sales = report.station_sales[station]
        return (
          <>
            <section className="summary-grid reports-summary-grid">
              <article><span className="summary-icon blue">৳</span><div><strong>৳{Number(sales.billed_total).toFixed(2)}</strong><p>{station === 'KITCHEN' ? 'Kitchen' : 'Bar'} billed sales</p></div></article>
              <article><span className="summary-icon green">#</span><div><strong>{Number(sales.quantity_sold).toLocaleString()}</strong><p>Items sold</p></div></article>
              <article><span className="summary-icon amber">▤</span><div><strong>৳{Number(sales.gross_subtotal).toFixed(2)}</strong><p>Item subtotal before bill discount and tax</p></div></article>
            </section>
            <p className="muted">Billed sales include each item's share of bill discount, tax and service charge. Payments and refunds remain in the overall report because they are recorded for the whole invoice.</p>
            <div className="reports-two-col">
              <section className="zone-section">
                <div className="zone-title"><h2>{station === 'KITCHEN' ? 'Kitchen' : 'Bar'} Daily Sales</h2><span>Non-voided invoices</span></div>
                {sales.daily_breakdown.length === 0 ? <div className="inline-empty">No sales for this station in the selected dates.</div> : (
                  <div className="reports-table-wrap"><table className="reports-table">
                    <thead><tr><th>Date</th><th>Items sold</th><th>Billed sales</th></tr></thead>
                    <tbody>{sales.daily_breakdown.map((day) => <tr key={day.date}><td><strong>{day.date}</strong></td><td>{Number(day.quantity_sold).toLocaleString()}</td><td><strong>৳{Number(day.billed_total).toFixed(2)}</strong></td></tr>)}</tbody>
                  </table></div>
                )}
              </section>
              <section className="zone-section">
                <div className="zone-title"><h2>{station === 'KITCHEN' ? 'Kitchen' : 'Bar'} Item Sales</h2><span>Item sales before bill discount and tax</span></div>
                {sales.items.length === 0 ? <div className="inline-empty">No items sold for this station in the selected dates.</div> : (
                  <div className="reports-table-wrap"><table className="reports-table">
                    <thead><tr><th>SKU</th><th>Item</th><th>Qty</th><th>Item subtotal</th></tr></thead>
                    <tbody>{sales.items.map((item) => <tr key={`${item.sku}-${item.item_name}`}><td><span className="sku-tag">{item.sku}</span></td><td><strong>{item.item_name}</strong></td><td>{Number(item.quantity_sold).toLocaleString()}</td><td><strong>৳{Number(item.gross_revenue).toFixed(2)}</strong></td></tr>)}</tbody>
                  </table></div>
                )}
              </section>
            </div>
          </>
        )
      })()}

      {subTab === 'invoices' && report && (
        <>
          <section className="zone-section">
            <div className="zone-title">
              <h2>Issued Invoices — Reprint, Refund & Void Control</h2>
              <input
                className="reports-search-input"
                placeholder="Search INV-XXXX, table, cashier or status…"
                value={invoiceSearch}
                onChange={(e) => setInvoiceSearch(e.target.value)}
              />
            </div>

            {filteredInvoices.length === 0 ? (
              <div className="inline-empty">No matching invoices found.</div>
            ) : (
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>Invoice #</th>
                      <th>Issued</th>
                      <th>Table</th>
                      <th>Cashier</th>
                      <th>Status</th>
                      <th>Billed</th>
                      <th>Refunded</th>
                      <th>Net</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredInvoices.map((inv) => {
                      const billed = Number(inv.total)
                      const refunded = Number(inv.refunded_amount ?? 0)
                      const remaining = Math.max(0, Number((billed - refunded).toFixed(2)))
                      const isVoided = inv.status === 'VOIDED'
                      const net = isVoided ? 0 : remaining

                      return (
                        <tr key={inv.id} className={isVoided ? 'voided-invoice-row' : ''}>
                          <td>
                            <strong>{inv.invoice_number}</strong>
                          </td>
                          <td>
                            {new Date(inv.issued_at).toLocaleString([], {
                              month: 'short',
                              day: '2-digit',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </td>
                          <td>
                            {inv.table_code} ({inv.guest_count}p)
                          </td>
                          <td>{inv.cashier_name ?? 'Cashier'}</td>
                          <td>
                            <span className={`invoice-status-pill status-${inv.status.toLowerCase()}`}>
                              {inv.status}
                            </span>
                            {inv.void_reason && (
                              <div className="invoice-reason-sub">Void: “{inv.void_reason}”</div>
                            )}
                          </td>
                          <td>৳{billed.toFixed(2)}</td>
                          <td>{refunded > 0 ? `−৳${refunded.toFixed(2)}` : '—'}</td>
                          <td>
                            <strong>৳{net.toFixed(2)}</strong>
                          </td>
                          <td>
                            <div className="invoice-row-actions">
                              <button
                                type="button"
                                className="secondary compact"
                                onClick={() => onViewInvoice(inv)}
                              >
                                🖨 Receipt
                              </button>
                              {canRefundOrVoid && !isVoided && remaining > 0.001 && (
                                <button
                                  type="button"
                                  className="secondary compact"
                                  disabled={busy}
                                  onClick={() =>
                                    setRefundModal({
                                      invoice: inv,
                                      amount: remaining.toFixed(2),
                                      method: 'CASH',
                                      reason: '',
                                      providerReference: '',
                                    })
                                  }
                                >
                                  ↩ Refund
                                </button>
                              )}
                              {canRefundOrVoid && inv.status === 'ISSUED' && refunded <= 0 && (
                                <button
                                  type="button"
                                  className="secondary compact danger-link"
                                  disabled={busy}
                                  onClick={() => setVoidModal({ invoice: inv, reason: '' })}
                                >
                                  ⊘ Void
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          {report.refunds.length > 0 && (
            <section className="zone-section">
              <div className="zone-title">
                <h2>Processed Refunds Ledger</h2>
                <span>{report.refunds.length} refunds</span>
              </div>
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>Refund #</th>
                      <th>Invoice</th>
                      <th>Method</th>
                      <th>Amount</th>
                      <th>Processed By</th>
                      <th>Mandatory Reason</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.refunds.map((ref) => (
                      <tr key={ref.id}>
                        <td>
                          <strong>{ref.refund_number}</strong>
                        </td>
                        <td>{ref.invoice?.invoice_number ?? `#${ref.invoice_id}`}</td>
                        <td>{ref.method}</td>
                        <td>
                          <strong className="danger-link">−৳{Number(ref.amount).toFixed(2)}</strong>
                        </td>
                        <td>{ref.processor?.name ?? 'Manager'}</td>
                        <td>“{ref.reason}”</td>
                        <td>
                          {new Date(ref.refunded_at).toLocaleString([], {
                            month: 'short',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </>
      )}

      {subTab === 'voids' && canViewAllReportDates && report && (
        <section className="zone-section void-control-panel">
          <div className="zone-title">
            <div>
              <p className="eyebrow coral">OWNER / ADMIN CONTROL</p>
              <h2>Voided Item Ledger</h2>
              <p className="muted">Permanent record of sent Kitchen and Bar items removed by a supervisor.</p>
            </div>
            <span>{report.voided_items.length} voided items in selected dates</span>
          </div>

          {report.voided_items.length === 0 ? (
            <div className="inline-empty">No sent items were voided during the selected period.</div>
          ) : (
            <div className="reports-table-wrap">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Supervisor</th>
                    <th>Table / Order</th>
                    <th>Voided Item</th>
                    <th>Void KOT &amp; New KOT</th>
                    <th>Qty</th>
                    <th>Original Value</th>
                    <th>Changed To / Active Items</th>
                    <th>Mandatory Reason</th>
                  </tr>
                </thead>
                <tbody>
                  {report.voided_items.map((item) => (
                    <tr key={item.id}>
                      <td>{new Date(item.voided_at).toLocaleString([], { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })}</td>
                      <td><strong>{item.voided_by}</strong></td>
                      <td><strong>{item.table_code}</strong><div className="invoice-reason-sub">{item.order_number}</div></td>
                      <td><strong>{item.item_name}</strong>{item.variant_name && <div className="invoice-reason-sub">{item.variant_name}</div>}<div className="invoice-reason-sub">{item.station ?? 'Station unknown'}</div></td>
                      <td>
                        <div>
                          {item.kots.length > 0 ? item.kots.map((kot) => <span key={kot.id} className="void-kot-tag">VOID: {kot.kot_number}</span>) : '—'}
                        </div>
                        {item.replacement_kots && item.replacement_kots.length > 0 && (
                          <div style={{ marginTop: '4px' }}>
                            {item.replacement_kots.map((rk) => (
                              <span key={rk.id} className="void-kot-tag" style={{ background: '#eff6ff', color: '#1d4ed8' }}>
                                NEW: {rk.kot_number}
                              </span>
                            ))}
                          </div>
                        )}
                      </td>
                      <td>{Number(item.quantity).toLocaleString()}</td>
                      <td><strong>৳{Number(item.original_total).toFixed(2)}</strong></td>
                      <td>
                        {item.replacement_items && item.replacement_items.length > 0 ? (
                          <div style={{ fontSize: '0.78rem', fontWeight: 700, color: '#1e3a8a' }}>
                            {item.replacement_items.map((rep) => (
                              <div key={rep.id}>
                                ⇄ {Number(rep.quantity)}x {rep.item_name} (৳{Number(rep.line_total).toFixed(2)})
                              </div>
                            ))}
                          </div>
                        ) : (
                          <span className="muted">No replacement item</span>
                        )}
                      </td>
                      <td><span className="void-reason-chip">“{item.reason ?? 'No reason recorded'}”</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {subTab === 'audit' && (
        <section className="zone-section">
          <div className="zone-title">
            <h2>Immutable Security & Operational Audit Trail</h2>
            <div className="audit-filter-bar">
              <div className="quick-cash-pills">
                {[
                  { label: 'All Events', value: '' },
                  { label: 'Invoices & Refunds', value: 'invoice.' },
                  { label: 'Discounts', value: 'order.discount' },
                  { label: 'Cash Register', value: 'shift.' },
                  { label: 'Orders & Transfers', value: 'order.' },
                  ...(canViewAllReportDates ? [{ label: 'Voided Items', value: 'order_item.sent_cancelled' }] : []),
                  { label: 'KOTs', value: 'kot' },
                  { label: 'Menu & Pricing', value: 'menu' },
                ].map((f) => (
                  <button
                    key={f.label}
                    type="button"
                    className={auditAction === f.value ? 'active-pill' : ''}
                    onClick={() => handleAuditFilter(f.value)}
                  >
                    {f.label}
                  </button>
                ))}
              </div>
              <input
                className="reports-search-input"
                placeholder="Search action or reason…"
                value={auditSearch}
                onChange={(e) => {
                  setAuditSearch(e.target.value)
                  void onLoadAudit(auditAction, e.target.value)
                }}
              />
            </div>
          </div>

          {auditEvents.length === 0 ? (
            <div className="inline-empty">No audit events match this filter.</div>
          ) : (
            <div className="reports-table-wrap">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>Reason / Details</th>
                  </tr>
                </thead>
                <tbody>
                  {auditEvents.map((ev) => (
                    <tr key={ev.id}>
                      <td>
                        {ev.created_at
                          ? new Date(ev.created_at).toLocaleString([], {
                              month: 'short',
                              day: '2-digit',
                              hour: '2-digit',
                              minute: '2-digit',
                              second: '2-digit',
                            })
                          : '—'}
                      </td>
                      <td>
                        <strong>{ev.actor_name}</strong>
                      </td>
                      <td>
                        <span className="sku-tag">{ev.action}</span>
                      </td>
                      <td>
                        {ev.auditable_type} #{ev.auditable_id}
                      </td>
                      <td>
                        {ev.reason ? (
                          <strong>“{ev.reason}”</strong>
                        ) : ev.after ? (
                          <code className="audit-json-snippet">{JSON.stringify(ev.after)}</code>
                        ) : (
                          '—'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {voidModal && (
        <div className="modal-backdrop" onMouseDown={() => setVoidModal(null)}>
          <form className="modal" onSubmit={submitVoid} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">VOID ISSUED INVOICE</p>
                <h2>Void {voidModal.invoice.invoice_number}?</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setVoidModal(null)}>
                ×
              </button>
            </div>
            <p className="muted" style={{ margin: 0 }}>
              Voiding marks {voidModal.invoice.invoice_number} (৳
              {Number(voidModal.invoice.total).toFixed(2)}) as <strong>VOIDED</strong>, reverses its captured
              its captured payments, and permanently retains the invoice number in the audit sequence.
            </p>
            <label>
              Mandatory manager reason for voiding
              <input
                autoFocus
                required
                minLength={3}
                placeholder="e.g. Settled wrong table check by mistake"
                value={voidModal.reason}
                onChange={(e) => setVoidModal({ ...voidModal, reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setVoidModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Voiding…' : `Confirm Void · ${voidModal.invoice.invoice_number}`}
              </button>
            </div>
          </form>
        </div>
      )}

      {refundModal && (
        <div className="modal-backdrop" onMouseDown={() => setRefundModal(null)}>
          <form className="modal" onSubmit={submitRefund} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">ISSUE PARTIAL OR FULL REFUND</p>
                <h2>Refund {refundModal.invoice.invoice_number}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setRefundModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Refund Amount (BDT ৳)
                <input
                  required
                  type="number"
                  min="0.01"
                  max={(
                    Number(refundModal.invoice.total) -
                    Number(refundModal.invoice.refunded_amount ?? 0)
                  ).toFixed(2)}
                  step="0.01"
                  value={refundModal.amount}
                  onChange={(e) => setRefundModal({ ...refundModal, amount: e.target.value })}
                />
              </label>
              <label>
                Refund Method
                <select
                  value={refundModal.method}
                  onChange={(e) =>
                    setRefundModal({
                      ...refundModal,
                      method: e.target.value as 'CASH' | 'CARD' | 'BKASH' | 'NAGAD',
                    })
                  }
                >
                  <option value="CASH">CASH (Drawer Payout)</option>
                  <option value="CARD">CARD Reversal</option>
                  <option value="BKASH">bKash Refund</option>
                  <option value="NAGAD">Nagad Refund</option>
                </select>
              </label>
            </div>

            {refundModal.method !== 'CASH' && (
              <label>
                Provider / Reversal Reference (optional)
                <input
                  placeholder="e.g. Reversal TrxID"
                  value={refundModal.providerReference}
                  onChange={(e) =>
                    setRefundModal({ ...refundModal, providerReference: e.target.value })
                  }
                />
              </label>
            )}

            <label>
              Mandatory manager reason for refund
              <input
                required
                minLength={3}
                placeholder="e.g. Guest returned 1 item due to delay"
                value={refundModal.reason}
                onChange={(e) => setRefundModal({ ...refundModal, reason: e.target.value })}
              />
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setRefundModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Processing…' : `Issue Refund · ৳${Number(refundModal.amount || 0).toFixed(2)}`}
              </button>
            </div>
          </form>
        </div>
      )}

      <footer className="report-official-footer">
        <div>
          <strong>{restaurantName} · Official Financial & Operational Report</strong>
          <span>
            Period: {fromDate} to {toDate} · Generated {new Date().toLocaleDateString()}
          </span>
        </div>
        <div className="tech-base-badge">Powered by Clique IT</div>
      </footer>
    </div>
  )
}
