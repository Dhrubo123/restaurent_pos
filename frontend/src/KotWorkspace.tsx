import { useMemo, useState } from 'react'
import type { KotLineRecord, KotRecord } from './KotPrintModal.tsx'

type Props = {
  restaurantName: string
  kots: KotRecord[]
  allowedStations: string[]
  permissions: string[]
  includeCompleted: boolean
  busy: boolean
  onToggleCompleted: (include: boolean) => Promise<void>
  onRefresh: () => Promise<void>
  onStartKot: (kot: KotRecord) => Promise<void>
  onReadyKot: (kot: KotRecord) => Promise<void>
  onServeItem: (item: KotLineRecord) => Promise<void>
  onOpenPrintModal: (kot: KotRecord) => void
}

export default function KotWorkspace({
  restaurantName,
  kots,
  allowedStations,
  permissions,
  includeCompleted,
  busy,
  onToggleCompleted,
  onRefresh,
  onStartKot,
  onReadyKot,
  onServeItem,
  onOpenPrintModal,
}: Props) {
  const [stationFilter, setStationFilter] = useState<'ALL' | 'KITCHEN' | 'BAR'>(
    allowedStations.length === 1 ? (allowedStations[0] as 'KITCHEN' | 'BAR') : 'ALL',
  )

  const canActOnStation = (station: 'KITCHEN' | 'BAR') =>
    permissions.includes('manage-floor') ||
    permissions.includes('manage-menu') ||
    (station === 'KITCHEN' && permissions.includes('manage-kitchen')) ||
    (station === 'BAR' && permissions.includes('manage-bar'))

  const canServe = permissions.includes('manage-orders')

  const visibleKots = useMemo(
    () => kots.filter((k) => stationFilter === 'ALL' || k.station === stationFilter),
    [kots, stationFilter],
  )

  return (
    <>
      <header>
        <div>
          <p className="eyebrow coral">KITCHEN & BAR DISPLAY (KDS)</p>
          <h1>Live station preparation queue.</h1>
          <p className="muted">{restaurantName} · Price-free station tickets & 80mm KOT printing</p>
        </div>
        <div className="header-actions">
          <button className="secondary compact" disabled={busy} onClick={() => void onRefresh()}>
            ↻ Refresh queue
          </button>
          <label className="checkbox-pill">
            <input
              type="checkbox"
              checked={includeCompleted}
              onChange={(e) => void onToggleCompleted(e.target.checked)}
            />
            <span>Show served / cancelled</span>
          </label>
        </div>
      </header>

      <section className="summary-grid">
        <article>
          <span className="summary-icon amber">◷</span>
          <div>
            <strong>{visibleKots.filter((k) => k.status === 'PENDING').length}</strong>
            <p>New / Pending</p>
          </div>
        </article>
        <article>
          <span className="summary-icon blue">♨</span>
          <div>
            <strong>{visibleKots.filter((k) => k.status === 'PREPARING').length}</strong>
            <p>Preparing</p>
          </div>
        </article>
        <article>
          <span className="summary-icon green">✓</span>
          <div>
            <strong>{visibleKots.filter((k) => k.status === 'READY').length}</strong>
            <p>Ready to serve</p>
          </div>
        </article>
        <article>
          <span className="summary-icon ink">#</span>
          <div>
            <strong>{visibleKots.length}</strong>
            <p>Visible KOTs</p>
          </div>
        </article>
      </section>

      {allowedStations.length > 1 && (
        <div className="menu-toolbar">
          <div className="station-pills">
            {(['ALL', 'KITCHEN', 'BAR'] as const).map((st) => (
              <button
                key={st}
                className={stationFilter === st ? 'active' : ''}
                onClick={() => setStationFilter(st)}
              >
                {st === 'ALL' ? 'All Stations' : `${st} Station`}
              </button>
            ))}
          </div>
        </div>
      )}

      {visibleKots.length === 0 ? (
        <section className="empty-state">
          <div>♨</div>
          <h2>Station queue is clear</h2>
          <p>New Kitchen and Bar Order Tickets appear here automatically when waiters send rounds.</p>
        </section>
      ) : (
        <div className="kds-grid">
          {visibleKots.map((kot) => {
            const canTransition = canActOnStation(kot.station)
            return (
              <article key={kot.id} className={`kds-card kds-${kot.status.toLowerCase()}`}>
                <div className="kds-card-head">
                  <div>
                    <div className="kds-badges">
                      <span className="kds-table-pill">
                        {kot.table?.code ?? 'TBL'} · {kot.table?.display_name}
                      </span>
                      <span className={`station-badge station-${kot.station.toLowerCase()}`}>{kot.station}</span>
                      <span className="kds-round-pill">Round #{kot.round_number}</span>
                    </div>
                    <h3>{kot.kot_number}</h3>
                    <p className="kds-meta">
                      {kot.order_number} · Waiter: {kot.waiter_name} · {kot.guest_count} guests ·{' '}
                      {new Date(kot.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </p>
                  </div>
                  <span className={`kds-status-badge kds-badge-${kot.status.toLowerCase()}`}>{kot.status}</span>
                </div>

                {kot.reprint_count > 0 && (
                  <div className="kds-reprint-tag">
                    REPRINT #{kot.reprint_count} ({kot.last_reprint_reason ?? 'Logged'})
                  </div>
                )}

                {kot.order_note && <div className="kds-order-note">Order Note: {kot.order_note}</div>}

                <div className="kds-items">
                  {kot.items.map((item) => (
                    <div
                      key={item.id}
                      className={`kds-item-row ${item.status === 'CANCELLED' ? 'kds-item-cancelled' : ''}`}
                    >
                      <div className="kds-item-main">
                        <strong className="kds-qty">{Number(item.quantity)}×</strong>
                        <div>
                          <div className="kds-item-title">
                            <strong>{item.item_name}</strong>
                            {item.variant_name && <span className="variant-pill">{item.variant_name}</span>}
                          </div>
                          {item.modifiers && item.modifiers.length > 0 && (
                            <div className="ticket-line-mods">
                              {item.modifiers.map((m, idx) => (
                                <span key={idx}>＋ {m.name}</span>
                              ))}
                            </div>
                          )}
                          {item.note && <p className="ticket-line-note">Note: {item.note}</p>}
                          {item.status === 'CANCELLED' && (
                            <p className="ticket-line-note">CANCELLED: {item.cancel_reason}</p>
                          )}
                        </div>
                      </div>

                      <div className="kds-item-right">
                        <span className={`line-state-badge state-${item.status.toLowerCase()}`}>{item.status}</span>
                        {canServe && item.status === 'READY' && (
                          <button
                            type="button"
                            className="activate-link kds-serve-btn"
                            disabled={busy}
                            onClick={() => void onServeItem(item)}
                          >
                            Served ✓
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>

                <div className="kds-actions">
                  <button
                    type="button"
                    className="secondary compact"
                    onClick={() => onOpenPrintModal(kot)}
                  >
                    🖨 80mm KOT
                  </button>

                  {canTransition && kot.status === 'PENDING' && (
                    <button
                      type="button"
                      className="primary compact kds-start-btn"
                      disabled={busy}
                      onClick={() => void onStartKot(kot)}
                    >
                      ▶ Start Preparing
                    </button>
                  )}

                  {canTransition && (kot.status === 'PENDING' || kot.status === 'PREPARING') && (
                    <button
                      type="button"
                      className="primary compact kds-ready-btn"
                      disabled={busy}
                      onClick={() => void onReadyKot(kot)}
                    >
                      ✓ Mark Ready
                    </button>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      )}
    </>
  )
}
