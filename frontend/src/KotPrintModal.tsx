import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { printThermalPaper } from './InvoicePrintModal.tsx'

export type KotLineRecord = {
  id: number
  order_item_id: number
  sku: string
  item_name: string
  variant_name: string | null
  quantity: string
  cancelled_quantity: string
  note: string | null
  modifiers: { name: string; quantity: number }[]
  status: 'PENDING' | 'PREPARING' | 'READY' | 'SERVED' | 'CANCELLED'
  cancel_reason: string | null
  cancelled_by_name?: string | null
}

export type KotRecord = {
  id: number
  kot_number: string
  station: 'KITCHEN' | 'BAR'
  status: 'PENDING' | 'PREPARING' | 'READY' | 'SERVED' | 'CANCELLED'
  round_number: number
  order_id: number
  order_number?: string
  order_note?: string | null
  guest_count: number
  restaurant_name?: string
  table?: {
    id: number
    code: string
    display_name: string
    zone: string
  } | null
  waiter_name?: string
  reprint_count: number
  last_reprinted_at?: string | null
  last_reprint_reason?: string | null
  sent_at: string
  started_at?: string | null
  ready_at?: string | null
  items: KotLineRecord[]
}

type Props = {
  kots: KotRecord[]
  busy: boolean
  onClose: () => void
  onReprint?: (kot: KotRecord, reason: string) => Promise<KotRecord | null>
}

export default function KotPrintModal({ kots, busy, onClose, onReprint }: Props) {
  const [selectedIndex, setSelectedIndex] = useState<number | 'ALL'>(kots.length > 1 ? 'ALL' : 0)
  const [reprintReason, setReprintReason] = useState('')
  const [showReprintForm, setShowReprintForm] = useState(false)
  const paperWrapRef = useRef<HTMLDivElement | null>(null)

  const currentKot = selectedIndex === 'ALL' ? kots[0] : (kots[selectedIndex] ?? kots[0])
  if (!currentKot) return null

  const kotsToRender = selectedIndex === 'ALL' ? kots : [currentKot]

  const handleBrowserPrint = () => {
    printThermalPaper(
      paperWrapRef.current,
      selectedIndex === 'ALL' && kots.length > 1
        ? `KOT All Stations (${kots.map((k) => k.kot_number).join(' + ')})`
        : `${currentKot.kot_number} · ${currentKot.station}`,
    )
  }

  const submitReprint = async (event: FormEvent) => {
    event.preventDefault()
    if (!onReprint || !reprintReason.trim()) return
    const updated = await onReprint(currentKot, reprintReason.trim())
    if (updated) {
      setShowReprintForm(false)
      setReprintReason('')
      setTimeout(() => {
        printThermalPaper(
          paperWrapRef.current,
          `${currentKot.kot_number} · ${currentKot.station} (Reprint)`,
        )
      }, 120)
    }
  }

  return (
    <div className="modal-backdrop print-backdrop" onMouseDown={onClose}>
      <div className="kot-print-dialog" onMouseDown={(e) => e.stopPropagation()}>
        <div className="kot-print-toolbar no-print">
          <div>
            <p className="eyebrow coral">80MM THERMAL KOT SLIP</p>
            <h3>
              {kots.length > 1
                ? `${kots.length} Station Tickets Generated`
                : `${currentKot.kot_number} · ${currentKot.station}`}
            </h3>
          </div>
          <button type="button" className="icon-button" onClick={onClose}>
            ×
          </button>
        </div>

        {kots.length > 1 && (
          <div className="kot-slip-switcher no-print">
            <button
              type="button"
              className={selectedIndex === 'ALL' ? 'active' : ''}
              onClick={() => {
                setSelectedIndex('ALL')
                setShowReprintForm(false)
              }}
            >
              All Tickets ({kots.length})
            </button>
            {kots.map((k, idx) => (
              <button
                key={k.id}
                type="button"
                className={idx === selectedIndex ? 'active' : ''}
                onClick={() => {
                  setSelectedIndex(idx)
                  setShowReprintForm(false)
                }}
              >
                {k.station} · {k.kot_number}
              </button>
            ))}
          </div>
        )}

        <div className="kot-paper-wrap" ref={paperWrapRef}>
          {kotsToRender.map((kot, kotIdx) => {
            const totalQty = kot.items
              .filter((i) => i.status !== 'CANCELLED')
              .reduce((sum, i) => sum + Math.max(0, Number(i.quantity) - Number(i.cancelled_quantity)), 0)

            return (
              <article
                key={kot.id}
                className={`kot-paper ${kotIdx > 0 ? 'kot-paper-second-copy' : ''}`}
                aria-label="80mm KOT thermal slip"
              >
                {kot.reprint_count > 0 && (
                  <div className="kot-paper-reprint-banner">
                    <strong>*** REPRINT #{kot.reprint_count} — DO NOT DOUBLE PREPARE ***</strong>
                    {kot.last_reprint_reason && <span>Reason: {kot.last_reprint_reason}</span>}
                  </div>
                )}

                {kot.status === 'CANCELLED' && (
                  <div className="kot-paper-void-banner">
                    <strong>*** CANCELLED TICKET — DO NOT PREPARE ***</strong>
                  </div>
                )}

                <div className="kot-paper-station">[{kot.station} KOT]</div>

                <div className="kot-paper-hero">
                  <div>
                    <span className="kot-paper-label">TABLE</span>
                    <strong className="kot-paper-table">
                      {kot.table?.code ?? 'TBL'} ({kot.table?.display_name ?? 'Table'})
                    </strong>
                    <span className="kot-paper-sub">{kot.table?.zone ?? 'Dining'}</span>
                  </div>
                  <div className="kot-paper-right">
                    <strong className="kot-paper-number">{kot.kot_number}</strong>
                    <span className="kot-paper-round">ROUND #{kot.round_number}</span>
                  </div>
                </div>

                <div className="kot-paper-meta">
                  <div>
                    <span>Order: {kot.order_number ?? `#${kot.order_id}`}</span>
                    <span>Guests: {kot.guest_count}</span>
                  </div>
                  <div>
                    <span>Waiter: {kot.waiter_name ?? 'Staff'}</span>
                    <span>Sent: {new Date(kot.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                  </div>
                </div>

                <div className="kot-paper-divider">======================================</div>
                <div className="kot-paper-colhead">
                  <span>QTY</span>
                  <span>ITEM & PREPARATION NOTES</span>
                </div>
                <div className="kot-paper-divider">--------------------------------------</div>

                <div className="kot-paper-items">
                  {kot.items.map((item) => {
                    const qty = Number(item.quantity)
                    const isCancelled = item.status === 'CANCELLED'
                    return (
                      <div key={item.id} className={`kot-paper-line ${isCancelled ? 'kot-line-cancelled' : ''}`}>
                        <div className="kot-paper-line-row">
                          <strong className="kot-paper-qty">{isCancelled ? `-${qty}x` : `${qty}x`}</strong>
                          <div className="kot-paper-item-body">
                            <strong className="kot-paper-item-name">
                              {item.item_name}
                              {item.variant_name ? ` (${item.variant_name})` : ''}
                            </strong>
                            {item.modifiers && item.modifiers.length > 0 && (
                              <ul className="kot-paper-mods">
                                {item.modifiers.map((m, idx) => (
                                  <li key={idx}>+ {m.name}</li>
                                ))}
                              </ul>
                            )}
                            {item.note && <div className="kot-paper-note">* NOTE: {item.note}</div>}
                            {isCancelled && (
                              <div className="kot-paper-cancel-tag">
                                [CANCELLED: {item.cancel_reason ?? 'Authorized void'}]
                                {item.cancelled_by_name && <div>Authorized by: {item.cancelled_by_name}</div>}
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    )
                  })}
                </div>

                {kot.order_note && (
                  <>
                    <div className="kot-paper-divider">--------------------------------------</div>
                    <div className="kot-paper-order-note">ORDER NOTE: {kot.order_note}</div>
                  </>
                )}

                <div className="kot-paper-divider">======================================</div>
                <div className="kot-paper-footer">
                  <span>Total {kot.station} Qty: {totalQty}</span>
                  <span>NO PRICES — KITCHEN COPY</span>
                </div>
                <div className="receipt-dev-credit">Developed by Tech Base</div>
              </article>
            )
          })}
        </div>

        {showReprintForm && onReprint ? (
          <form className="kot-reprint-form no-print" onSubmit={submitReprint}>
            <input
              autoFocus
              required
              minLength={3}
              placeholder="Mandatory reason for reprint (e.g. Paper roll replaced)…"
              value={reprintReason}
              onChange={(e) => setReprintReason(e.target.value)}
            />
            <button type="submit" className="primary compact" disabled={busy}>
              Log & Reprint
            </button>
            <button type="button" className="secondary compact" onClick={() => setShowReprintForm(false)}>
              Cancel
            </button>
          </form>
        ) : (
          <div className="modal-actions no-print">
            {onReprint && (
              <button type="button" className="secondary" onClick={() => setShowReprintForm(true)}>
                Log Reprint with Reason
              </button>
            )}
            <button type="button" className="secondary" onClick={onClose}>
              Close
            </button>
            <button type="button" className="primary" onClick={handleBrowserPrint}>
              🖨 Print 80mm KOT
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
