import { useState } from 'react'
import type { FormEvent } from 'react'

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
  const [selectedIndex, setSelectedIndex] = useState(0)
  const [reprintReason, setReprintReason] = useState('')
  const [showReprintForm, setShowReprintForm] = useState(false)

  const currentKot = kots[selectedIndex] ?? kots[0]
  if (!currentKot) return null

  const totalQty = currentKot.items
    .filter((i) => i.status !== 'CANCELLED')
    .reduce((sum, i) => sum + Math.max(0, Number(i.quantity) - Number(i.cancelled_quantity)), 0)

  const handleBrowserPrint = () => {
    window.print()
  }

  const submitReprint = async (event: FormEvent) => {
    event.preventDefault()
    if (!onReprint || !reprintReason.trim()) return
    const updated = await onReprint(currentKot, reprintReason.trim())
    if (updated) {
      setShowReprintForm(false)
      setReprintReason('')
      setTimeout(() => window.print(), 150)
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

        <div className="kot-paper-wrap">
          <article className="kot-paper" aria-label="80mm KOT thermal slip">
            {currentKot.reprint_count > 0 && (
              <div className="kot-paper-reprint-banner">
                <strong>*** REPRINT #{currentKot.reprint_count} — DO NOT DOUBLE PREPARE ***</strong>
                {currentKot.last_reprint_reason && <span>Reason: {currentKot.last_reprint_reason}</span>}
              </div>
            )}

            {currentKot.status === 'CANCELLED' && (
              <div className="kot-paper-void-banner">
                <strong>*** CANCELLED TICKET — DO NOT PREPARE ***</strong>
              </div>
            )}

            <div className="kot-paper-station">[{currentKot.station} KOT]</div>

            <div className="kot-paper-hero">
              <div>
                <span className="kot-paper-label">TABLE</span>
                <strong className="kot-paper-table">
                  {currentKot.table?.code ?? 'TBL'} ({currentKot.table?.display_name ?? 'Table'})
                </strong>
                <span className="kot-paper-sub">{currentKot.table?.zone ?? 'Dining'}</span>
              </div>
              <div className="kot-paper-right">
                <strong className="kot-paper-number">{currentKot.kot_number}</strong>
                <span className="kot-paper-round">ROUND #{currentKot.round_number}</span>
              </div>
            </div>

            <div className="kot-paper-meta">
              <div>
                <span>Order: {currentKot.order_number ?? `#${currentKot.order_id}`}</span>
                <span>Guests: {currentKot.guest_count}</span>
              </div>
              <div>
                <span>Waiter: {currentKot.waiter_name ?? 'Staff'}</span>
                <span>Sent: {new Date(currentKot.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            </div>

            <div className="kot-paper-divider">======================================</div>
            <div className="kot-paper-colhead">
              <span>QTY</span>
              <span>ITEM & PREPARATION NOTES</span>
            </div>
            <div className="kot-paper-divider">--------------------------------------</div>

            <div className="kot-paper-items">
              {currentKot.items.map((item) => {
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
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>

            {currentKot.order_note && (
              <>
                <div className="kot-paper-divider">--------------------------------------</div>
                <div className="kot-paper-order-note">ORDER NOTE: {currentKot.order_note}</div>
              </>
            )}

            <div className="kot-paper-divider">======================================</div>
            <div className="kot-paper-footer">
              <span>Total {currentKot.station} Qty: {totalQty}</span>
              <span>NO PRICES — KITCHEN COPY</span>
            </div>
            <div className="receipt-dev-credit">Developed by Tech Base</div>
          </article>
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
