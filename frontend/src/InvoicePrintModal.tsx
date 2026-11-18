import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import type { OrderRecord } from './OrderWorkspace.tsx'

export function printThermalPaper(paperWrapEl: HTMLElement | null, docTitle = 'Clique Cafe 80mm Slip'): void {
  const isMobileOrTablet =
    typeof navigator !== 'undefined' &&
    (/Android|iPhone|iPad|iPod|Mobile|Tablet|Silk/i.test(navigator.userAgent) ||
      (navigator.maxTouchPoints > 1 && window.innerWidth <= 1280))

  if (!paperWrapEl) {
    const prevTitle = document.title
    document.title = docTitle
    window.print()
    setTimeout(() => {
      document.title = prevTitle
    }, 500)
    return
  }

  if (isMobileOrTablet) {
    document.querySelector('.thermal-print-root')?.remove()

    const printRoot = document.createElement('div')
    printRoot.className = 'thermal-print-root'
    printRoot.setAttribute('aria-hidden', 'true')
    printRoot.innerHTML = paperWrapEl.innerHTML
    document.body.appendChild(printRoot)
    document.body.classList.add('thermal-print-active')
    document.documentElement.classList.add('thermal-print-active')

    const prevTitle = document.title
    let cleaned = false
    const cleanup = () => {
      if (cleaned) return
      cleaned = true
      document.title = prevTitle
      document.body.classList.remove('thermal-print-active')
      document.documentElement.classList.remove('thermal-print-active')
      printRoot.remove()
    }

    window.addEventListener('afterprint', cleanup, { once: true })
    document.title = docTitle
    window.print()

    // Some Android WebViews do not dispatch afterprint. This is only a safety cleanup;
    // the cloned receipt is hidden outside print media and cannot affect the POS screen.
    setTimeout(cleanup, 120000)
    return
  }

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow?.document
  if (!doc) {
    document.body.removeChild(iframe)
    window.print()
    return
  }

  doc.open()
  doc.write(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title></title>
  <style>
    @page { size: 80mm auto; margin: 0; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #000;
      font-family: "Courier New", Courier, monospace;
      font-size: 12px;
      line-height: 1.35;
    }
    .kot-paper {
      width: 76mm;
      margin: 0 auto;
      padding: 4mm 3mm;
      background: #fff;
      color: #000;
    }
    .kot-paper-second-copy {
      page-break-before: always;
      break-before: page;
      margin-top: 6mm;
      padding-top: 6mm;
      border-top: 2px dashed #000;
    }
    .kot-paper-reprint-banner, .kot-paper-void-banner {
      display: grid;
      gap: 2px;
      margin-bottom: 8px;
      padding: 6px;
      border: 2px solid #000;
      text-align: center;
      font-weight: 700;
    }
    .kot-paper-station {
      margin-bottom: 6px;
      text-align: center;
      font-size: 15px;
      font-weight: 900;
      letter-spacing: 0.06em;
    }
    .receipt-brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-bottom: 6px;
    }
    .receipt-brand-logo {
      width: 32px;
      height: 32px;
      object-fit: contain;
    }
    .receipt-brand .kot-paper-station { margin-bottom: 0; }
    .kot-paper-hero {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }
    .kot-paper-hero > div { display: grid; gap: 1px; }
    .kot-paper-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; }
    .kot-paper-table { font-size: 16px; line-height: 1.15; }
    .kot-paper-sub { font-size: 10px; }
    .kot-paper-right { text-align: right; }
    .kot-paper-number { font-size: 14px; }
    .kot-paper-round { font-size: 10px; font-weight: 700; }
    .kot-paper-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2px 8px;
      margin-bottom: 6px;
      font-size: 10px;
    }
    .kot-paper-meta > div { display: grid; }
    .kot-paper-meta > div:last-child { text-align: right; }
    .kot-paper-divider {
      overflow: hidden;
      white-space: nowrap;
      letter-spacing: -0.06em;
    }
    .kot-paper-colhead, .receipt-colhead {
      display: grid;
      grid-template-columns: 32px 1fr 68px;
      padding: 3px 0;
      font-size: 10px;
      font-weight: 900;
    }
    .kot-paper-colhead { grid-template-columns: 34px 1fr; }
    .kot-paper-items { display: grid; gap: 7px; padding: 7px 0; }
    .kot-paper-line { display: grid; gap: 2px; }
    .kot-paper-line-row { display: grid; grid-template-columns: 34px 1fr; }
    .receipt-line-row { display: grid; grid-template-columns: 32px 1fr 68px; align-items: start; }
    .receipt-line-row .kot-paper-item-body { min-width: 0; overflow-wrap: anywhere; }
    .kot-paper-qty { font-size: 13px; }
    .kot-paper-item-body { display: grid; gap: 2px; }
    .kot-paper-item-name { font-size: 13px; line-height: 1.2; }
    .kot-paper-mods { margin: 1px 0 0; padding-left: 14px; font-size: 10px; }
    .kot-paper-note { font-size: 10px; font-weight: 700; }
    .kot-line-cancelled { text-decoration: line-through; opacity: 0.65; }
    .kot-paper-cancel-tag { font-size: 10px; font-weight: 900; }
    .kot-paper-order-note { padding: 6px 0; font-size: 11px; font-weight: 800; }
    .receipt-totals { display: grid; gap: 4px; padding: 6px 0; font-size: 11px; }
    .receipt-totals > div { display: flex; justify-content: space-between; gap: 8px; }
    .receipt-totals > div > :first-child { min-width: 0; overflow-wrap: anywhere; }
    .receipt-totals > div > :last-child { flex-shrink: 0; text-align: right; }
    .receipt-grand-total {
      margin-top: 4px;
      padding-top: 5px;
      border-top: 1px dashed #000;
      font-size: 13px;
      font-weight: 900;
    }
    .kot-paper-footer {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      padding-top: 5px;
      font-size: 10px;
      font-weight: 900;
    }
    .receipt-dev-credit {
      margin-top: 6px;
      padding-top: 4px;
      border-top: 1px dotted #444;
      text-align: center;
      font-size: 9.5px;
      font-weight: 800;
    }
  </style>
</head>
<body>${paperWrapEl.innerHTML}</body>
</html>`)
  doc.close()
  doc.title = docTitle

  const imagesReady = Promise.all(Array.from(doc.images, (img) => {
    if (img.complete) return Promise.resolve()
    return new Promise<void>((resolve) => {
      img.addEventListener('load', () => resolve(), { once: true })
      img.addEventListener('error', () => resolve(), { once: true })
    })
  }))

  void Promise.race([imagesReady, new Promise((resolve) => setTimeout(resolve, 1500))]).then(() => {
    try {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    } finally {
      setTimeout(() => {
        if (iframe.parentNode) iframe.parentNode.removeChild(iframe)
      }, 2000)
    }
  })
}

export type InvoiceItemRecord = {
  id: number
  sku: string
  item_name: string
  variant_name: string | null
  quantity: string
  unit_price: string
  modifier_unit_total: string
  modifiers_snapshot: { name: string; unit_price: string }[] | null
  line_subtotal: string
  line_tax: string
  line_total: string
}

export type InvoicePaymentSnapshot = {
  id: number
  method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
  amount: string
  tendered_amount?: string | null
  change_given?: string | null
  provider_reference?: string | null
  captured_at?: string
}

export type RefundRecord = {
  id: number
  invoice_id: number
  refund_number: string
  method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
  amount: string
  reason: string
  provider_reference: string | null
  refunded_at: string
  processor?: { id: number; name: string }
  invoice?: { id: number; invoice_number: string; table_code: string }
}

export type InvoiceRecord = {
  id: number
  order_id: number
  invoice_number: string
  status: 'ISSUED' | 'OWNER_DUE' | 'VOIDED' | 'PARTIALLY_REFUNDED' | 'REFUNDED' | 'PROFORMA_BILL'
  owner_account?: boolean
  guest_count: number
  table_code: string
  table_name: string
  waiter_name: string | null
  cashier_name: string | null
  subtotal: string
  discount_amount: string
  service_charge: string
  tax_amount: string
  total: string
  paid_amount: string
  refunded_amount?: string
  tendered_cash: string
  change_given: string
  reprint_count: number
  last_reprinted_at: string | null
  last_reprint_reason: string | null
  issued_at: string
  void_reason?: string | null
  voided_at?: string | null
  payment_snapshot: InvoicePaymentSnapshot[] | null
  items: InvoiceItemRecord[]
  refunds?: RefundRecord[]
}

export function buildGuestBillFromOrder(order: OrderRecord): InvoiceRecord {
  const activeLines = order.items.filter((i) => i.status !== 'CANCELLED')
  return {
    id: -order.id,
    order_id: order.id,
    invoice_number: `BILL · ${order.order_number}`,
    status: 'PROFORMA_BILL',
    guest_count: order.guest_count,
    table_code: order.table?.code ?? 'TBL',
    table_name: order.table?.display_name ?? 'Table',
    waiter_name: order.waiter?.name ?? 'Waiter',
    cashier_name: null,
    subtotal: order.subtotal,
    discount_amount: order.discount_amount,
    service_charge: order.service_charge,
    tax_amount: order.tax_amount,
    total: order.total,
    paid_amount: order.paid_amount,
    tendered_cash: '0.00',
    change_given: '0.00',
    reprint_count: 0,
    last_reprinted_at: null,
    last_reprint_reason: null,
    issued_at: new Date().toISOString(),
    payment_snapshot: (order.payments ?? []).map((p) => ({
      id: p.id,
      method: p.method,
      amount: p.amount,
      tendered_amount: p.tendered_amount,
      change_given: p.change_given,
      provider_reference: p.provider_reference,
      captured_at: p.paid_at,
    })),
    items: activeLines.map((line) => ({
      id: line.id,
      sku: line.sku,
      item_name: line.item_name,
      variant_name: line.variant_name,
      quantity: String(Math.max(0, Number(line.quantity) - Number(line.cancelled_quantity))),
      unit_price: line.unit_price,
      modifier_unit_total: line.modifier_unit_total,
      modifiers_snapshot: line.modifiers.map((m) => ({
        name: m.name,
        unit_price: m.unit_price,
      })),
      line_subtotal: line.line_subtotal,
      line_tax: line.line_tax,
      line_total: line.line_total,
    })),
  }
}

type CopyMode = 'CUSTOMER' | 'COUNTER' | 'BOTH'

type Props = {
  restaurantName: string
  invoice: InvoiceRecord
  busy: boolean
  onClose: () => void
  onReprint?: (invoice: InvoiceRecord, reason: string) => Promise<InvoiceRecord | null>
}

export default function InvoicePrintModal({
  restaurantName,
  invoice,
  busy,
  onClose,
  onReprint,
}: Props) {
  const isProformaBill = invoice.status === 'PROFORMA_BILL'
  const [copyMode, setCopyMode] = useState<CopyMode>(isProformaBill ? 'COUNTER' : 'BOTH')
  const [reprintReason, setReprintReason] = useState('')
  const [showReprintForm, setShowReprintForm] = useState(false)
  const paperWrapRef = useRef<HTMLDivElement | null>(null)

  const handlePrint = () => {
    printThermalPaper(paperWrapRef.current, `${invoice.invoice_number} - ${restaurantName}`)
  }

  const submitReprint = async (event: FormEvent) => {
    event.preventDefault()
    if (!onReprint || !reprintReason.trim()) return
    const updated = await onReprint(invoice, reprintReason.trim())
    if (updated) {
      setShowReprintForm(false)
      setReprintReason('')
      setTimeout(() => printThermalPaper(paperWrapRef.current, `${invoice.invoice_number} - Reprint`), 120)
    }
  }

  const copiesToRender: ('CUSTOMER' | 'COUNTER')[] = isProformaBill
    ? ['COUNTER']
    : copyMode === 'BOTH'
      ? ['CUSTOMER', 'COUNTER']
      : [copyMode]

  const balanceDue = Math.max(0, Number(invoice.total) - Number(invoice.paid_amount))

  return (
    <div className="modal-backdrop print-backdrop" onMouseDown={onClose}>
      <div className="kot-print-dialog" onMouseDown={(e) => e.stopPropagation()}>
        <div className="kot-print-toolbar no-print">
          <div>
            <div className="invoice-toolbar-brand">
              <img src="/clique-cafe-logo.jpg" alt="" />
              <strong className="invoice-toolbar-name">{restaurantName}</strong>
            </div>
            <p className="eyebrow coral">
              {isProformaBill ? '80MM GUEST TABLE BILL (PRE-PAYMENT CHECK)' : '80MM CUSTOMER & COUNTER BILL COPIES'}
            </p>
            <h3>
              {invoice.invoice_number} · {invoice.table_code} ({invoice.table_name})
            </h3>
          </div>
          <button type="button" className="icon-button" onClick={onClose}>
            ×
          </button>
        </div>

        {!isProformaBill && (
          <div className="kot-slip-switcher no-print">
            <button
              type="button"
              className={copyMode === 'BOTH' ? 'active' : ''}
              onClick={() => setCopyMode('BOTH')}
            >
              🖨 Both Copies (Customer + Bill Copy)
            </button>
            <button
              type="button"
              className={copyMode === 'CUSTOMER' ? 'active' : ''}
              onClick={() => setCopyMode('CUSTOMER')}
            >
              👤 Customer Copy Only
            </button>
            <button
              type="button"
              className={copyMode === 'COUNTER' ? 'active' : ''}
              onClick={() => setCopyMode('COUNTER')}
            >
              🏪 Counter / Bill Copy Only
            </button>
          </div>
        )}

        <div ref={paperWrapRef} className="kot-paper-wrap" style={{ gap: '20px' }}>
          {copiesToRender.map((whichCopy, copyIdx) => (
            <article
              key={whichCopy}
              className={`kot-paper ${copyIdx > 0 ? 'kot-paper-second-copy' : ''}`}
              aria-label={`80mm ${whichCopy.toLowerCase()} receipt`}
            >
              {invoice.status === 'VOIDED' && (
                <div className="kot-paper-void-banner">
                  <strong>*** VOIDED INVOICE ***</strong>
                  {invoice.void_reason && <span>Reason: {invoice.void_reason}</span>}
                </div>
              )}
              {invoice.reprint_count > 0 && (
                <div className="kot-paper-reprint-banner">
                  <strong>*** DUPLICATE RECEIPT COPY #{invoice.reprint_count} ***</strong>
                  {invoice.last_reprint_reason && <span>Reason: {invoice.last_reprint_reason}</span>}
                </div>
              )}

              <div className="receipt-brand">
                <img className="receipt-brand-logo" src={`${window.location.origin}/clique-cafe-logo.jpg`} alt="" />
                <div className="kot-paper-station">{restaurantName}</div>
              </div>
              <div style={{ textAlign: 'center', fontSize: '9.5px', fontWeight: 700, marginBottom: '4px' }}>
                COFFEE • BISTRO • DESSERTS
              </div>
              <div
                style={{
                  textAlign: 'center',
                  fontSize: '10.5px',
                  fontWeight: 900,
                  padding: '3px 6px',
                  marginBottom: '8px',
                  border: '1.5px solid #111',
                }}
              >
                {isProformaBill
                  ? '*** GUEST BILL COPY (TABLE CHECK — UNPAID) ***'
                  : invoice.status === 'OWNER_DUE'
                    ? '*** OWNER ACCOUNT — BALANCE DUE ***'
                    : whichCopy === 'CUSTOMER'
                    ? '*** CUSTOMER COPY — OFFICIAL TAX RECEIPT ***'
                    : '*** COUNTER / BILL COPY — OFFICE RECORD ***'}
              </div>

              <div className="kot-paper-hero">
                <div>
                  <span className="kot-paper-label">TABLE</span>
                  <strong className="kot-paper-table">
                    {invoice.table_code} ({invoice.table_name})
                  </strong>
                  <span className="kot-paper-sub">Guests: {invoice.guest_count}</span>
                </div>
                <div className="kot-paper-right">
                  <strong className="kot-paper-number">{invoice.invoice_number}</strong>
                  <span className="kot-paper-round">
                    {isProformaBill ? 'BILL DUE' : whichCopy === 'CUSTOMER' ? 'CUSTOMER COPY' : 'BILL COPY'}
                  </span>
                </div>
              </div>

              <div className="kot-paper-meta">
                <div>
                  <span>Waiter: {invoice.waiter_name ?? 'Staff'}</span>
                  <span>Cashier: {invoice.cashier_name ?? (isProformaBill ? 'Pending' : 'Cashier')}</span>
                </div>
                <div>
                  <span>Date: {new Date(invoice.issued_at).toLocaleDateString()}</span>
                  <span>
                    Time: {new Date(invoice.issued_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              </div>

              <div className="kot-paper-divider">======================================</div>
              <div className="receipt-colhead">
                <span>QTY</span>
                <span>ITEM</span>
                <span>AMT</span>
              </div>
              <div className="kot-paper-divider">--------------------------------------</div>

              <div className="kot-paper-items">
                {invoice.items.map((item) => (
                  <div key={item.id} className="kot-paper-line">
                    <div className="receipt-line-row">
                      <strong>{Number(item.quantity)}x</strong>
                      <div className="kot-paper-item-body">
                        <strong>
                          {item.item_name}
                          {item.variant_name ? ` (${item.variant_name})` : ''}
                        </strong>
                        {item.modifiers_snapshot && item.modifiers_snapshot.length > 0 && (
                          <ul className="kot-paper-mods">
                            {item.modifiers_snapshot.map((m, idx) => (
                              <li key={idx}>
                                + {m.name} (৳{Number(m.unit_price).toFixed(0)})
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                      <strong style={{ textAlign: 'right' }}>৳{Number(item.line_subtotal).toFixed(2)}</strong>
                    </div>
                  </div>
                ))}
              </div>

              <div className="kot-paper-divider">--------------------------------------</div>
              <div className="receipt-totals">
                <div>
                  <span>Subtotal</span>
                  <span>৳{Number(invoice.subtotal).toFixed(2)}</span>
                </div>
                {Number(invoice.discount_amount) > 0 && (
                  <div>
                    <span>Discount</span>
                    <span>-৳{Number(invoice.discount_amount).toFixed(2)}</span>
                  </div>
                )}
                {Number(invoice.service_charge) > 0 && (
                  <div>
                    <span>Service charge</span>
                    <span>৳{Number(invoice.service_charge).toFixed(2)}</span>
                  </div>
                )}
                <div>
                  <span>VAT / Tax</span>
                  <span>৳{Number(invoice.tax_amount).toFixed(2)}</span>
                </div>
                <div className="receipt-grand-total">
                  <strong>{isProformaBill ? 'TOTAL BILL' : 'TOTAL'}</strong>
                  <strong>৳{Number(invoice.total).toFixed(2)}</strong>
                </div>
              </div>

              <div className="kot-paper-divider">--------------------------------------</div>
              <div className="receipt-totals">
                {(invoice.payment_snapshot ?? []).map((pay, idx) => (
                  <div key={idx}>
                    <span>
                      {pay.method}
                      {pay.provider_reference ? ` (${pay.provider_reference})` : ''}
                    </span>
                    <span>৳{Number(pay.amount).toFixed(2)}</span>
                  </div>
                ))}
                {isProformaBill || invoice.status === 'OWNER_DUE' ? (
                  <div className="receipt-grand-total">
                    <strong>{invoice.status === 'OWNER_DUE' ? 'OWNER ACCOUNT DUE' : 'AMOUNT DUE TO PAY'}</strong>
                    <strong>৳{balanceDue.toFixed(2)}</strong>
                  </div>
                ) : (
                  <>
                    {Number(invoice.tendered_cash) > 0 && (
                      <div>
                        <span>Cash Tendered</span>
                        <span>৳{Number(invoice.tendered_cash).toFixed(2)}</span>
                      </div>
                    )}
                    {Number(invoice.change_given) > 0 && (
                      <div className="receipt-grand-total">
                        <strong>CHANGE GIVEN</strong>
                        <strong>৳{Number(invoice.change_given).toFixed(2)}</strong>
                      </div>
                    )}
                    {Number(invoice.refunded_amount ?? 0) > 0 && (
                      <div className="receipt-grand-total">
                        <strong>REFUNDED</strong>
                        <strong>-৳{Number(invoice.refunded_amount).toFixed(2)}</strong>
                      </div>
                    )}
                  </>
                )}
              </div>

              {(whichCopy === 'COUNTER' && !isProformaBill) && (
                <div style={{ marginTop: '10px', paddingTop: '8px', borderTop: '1px dashed #333', fontSize: '10px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '14px' }}>
                    <span>Cashier Sign: _____________</span>
                    <span>Drawer Copy</span>
                  </div>
                </div>
              )}

              <div className="kot-paper-divider">======================================</div>
              <div className="kot-paper-footer">
                <span>
                  {isProformaBill
                    ? 'PLEASE PAY AT COUNTER OR TO WAITER'
                    : whichCopy === 'CUSTOMER'
                      ? 'THANK YOU FOR DINING WITH US!'
                      : 'COUNTER / BILL RECORD COPY'}
                </span>
                <span>
                  {isProformaBill
                    ? 'UNPAID'
                    : invoice.status === 'ISSUED' && balanceDue <= 0.001
                      ? 'PAID'
                      : invoice.status.replaceAll('_', ' ')}
                </span>
              </div>
              <div className="receipt-dev-credit">Developed by Tech Base</div>
            </article>
          ))}
        </div>

        {showReprintForm && onReprint && !isProformaBill ? (
          <form className="kot-reprint-form no-print" onSubmit={submitReprint}>
            <input
              autoFocus
              required
              minLength={3}
              placeholder="Reason for duplicate receipt reprint…"
              value={reprintReason}
              onChange={(e) => setReprintReason(e.target.value)}
            />
            <button type="submit" className="primary compact" disabled={busy}>
              Log & Print Copy
            </button>
            <button type="button" className="secondary compact" onClick={() => setShowReprintForm(false)}>
              Cancel
            </button>
          </form>
        ) : (
          <div className="modal-actions no-print">
            {onReprint && !isProformaBill && (
              <button type="button" className="secondary" onClick={() => setShowReprintForm(true)}>
                Log Duplicate Reprint
              </button>
            )}
            <button type="button" className="secondary" onClick={onClose}>
              Close
            </button>
            <button type="button" className="primary" onClick={handlePrint}>
              {isProformaBill
                ? '🖨 Print 80mm Guest Bill Copy'
                : copyMode === 'BOTH'
                  ? '🖨 Print Both Copies (Customer + Bill)'
                  : copyMode === 'CUSTOMER'
                    ? '🖨 Print Customer Copy'
                    : '🖨 Print Counter / Bill Copy'}
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
