import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import type { InvoiceRecord } from './InvoicePrintModal.tsx'
import type { OrderRecord } from './OrderWorkspace.tsx'

export type CashierShiftRecord = {
  id: number
  cashier_id: number
  cashier_name: string
  status: 'OPEN' | 'CLOSED' | 'APPROVED'
  opening_float: string
  expected_cash: string
  counted_cash: string | null
  variance: string | null
  notes: string | null
  opened_at: string
  closed_at: string | null
  approved_by_name: string | null
  approved_at: string | null
  totals_by_method: {
    CASH: string
    CARD: string
    BKASH: string
    NAGAD: string
    TOTAL: string
  }
}

type Props = {
  restaurantName: string
  orders: OrderRecord[]
  selectedOrder: OrderRecord | null
  currentShift: CashierShiftRecord | null
  recentShifts: CashierShiftRecord[]
  recentInvoices: InvoiceRecord[]
  canApproveShift: boolean
  busy: boolean
  onSelectOrder: (orderId: number) => Promise<void>
  onOpenShift: (openingFloat: number, notes: string) => Promise<boolean>
  onCloseShift: (shiftId: number, countedCash: number, notes: string) => Promise<boolean>
  onApproveShift: (shiftId: number) => Promise<void>
  onApplyDiscount: (
    order: OrderRecord,
    type: 'PERCENTAGE' | 'FIXED',
    value: number,
    reason: string,
  ) => Promise<boolean>
  onRemoveDiscount: (order: OrderRecord) => Promise<void>
  onRecordPayment: (
    order: OrderRecord,
    payload: {
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      amount: number
      tendered_amount?: number
      provider_reference?: string
    },
  ) => Promise<boolean>
  onCheckoutOrder: (
    order: OrderRecord,
    payload?: {
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      amount: number
      tendered_amount?: number
      provider_reference?: string
    },
  ) => Promise<boolean>
  onViewInvoice: (invoice: InvoiceRecord) => void
  onPrintGuestBill?: (order: OrderRecord) => void
}

export default function CashierWorkspace({
  restaurantName,
  orders,
  selectedOrder,
  currentShift,
  recentShifts,
  recentInvoices,
  canApproveShift,
  busy,
  onSelectOrder,
  onOpenShift,
  onCloseShift,
  onApproveShift,
  onApplyDiscount,
  onRemoveDiscount,
  onRecordPayment,
  onCheckoutOrder,
  onViewInvoice,
  onPrintGuestBill,
}: Props) {
  const [method, setMethod] = useState<'CASH' | 'CARD' | 'BKASH' | 'NAGAD'>('CASH')
  const [amount, setAmount] = useState('')
  const [tendered, setTendered] = useState('')
  const [providerRef, setProviderRef] = useState('')

  const [showOpenShiftModal, setShowOpenShiftModal] = useState(false)
  const [openingFloatInput, setOpeningFloatInput] = useState('1000')
  const [shiftNotes, setShiftNotes] = useState('')

  const [showCloseShiftModal, setShowCloseShiftModal] = useState(false)
  const [countedCashInput, setCountedCashInput] = useState('')
  const [closeNotes, setCloseNotes] = useState('')

  const [discountModal, setDiscountModal] = useState<{
    type: 'PERCENTAGE' | 'FIXED'
    value: string
    reason: string
  } | null>(null)

  const balanceDue = Number(selectedOrder?.balance_due ?? 0)

  useEffect(() => {
    if (selectedOrder) {
      const bal = Number(selectedOrder.balance_due).toFixed(2)
      setAmount(bal)
      setTendered(bal)
      setProviderRef('')
    }
  }, [selectedOrder?.id, selectedOrder?.balance_due])

  const activeItems = useMemo(
    () => (selectedOrder?.items ?? []).filter((i) => i.status !== 'CANCELLED'),
    [selectedOrder],
  )

  const numAmount = Number(amount || 0)
  const numTendered = method === 'CASH' ? Number(tendered || amount || 0) : numAmount
  const changePreview =
    method === 'CASH' ? Math.max(0, numTendered - Math.min(numAmount, balanceDue)) : 0

  const handleSplitPayment = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || numAmount <= 0) return
    const ok = await onRecordPayment(selectedOrder, {
      method,
      amount: numAmount,
      tendered_amount: method === 'CASH' ? numTendered : undefined,
      provider_reference: method !== 'CASH' ? providerRef.trim() : undefined,
    })
    if (ok) {
      setProviderRef('')
    }
  }

  const handlePayAndFinalize = async () => {
    if (!selectedOrder) return
    if (balanceDue <= 0.001) {
      await onCheckoutOrder(selectedOrder)
      return
    }
    await onCheckoutOrder(selectedOrder, {
      method,
      amount: Math.min(numAmount || balanceDue, balanceDue),
      tendered_amount: method === 'CASH' ? Math.max(numTendered, balanceDue) : undefined,
      provider_reference: method !== 'CASH' ? providerRef.trim() : undefined,
    })
  }

  const submitOpenShift = async (event: FormEvent) => {
    event.preventDefault()
    const ok = await onOpenShift(Number(openingFloatInput), shiftNotes)
    if (ok) {
      setShowOpenShiftModal(false)
      setShiftNotes('')
    }
  }

  const submitCloseShift = async (event: FormEvent) => {
    event.preventDefault()
    if (!currentShift) return
    const ok = await onCloseShift(currentShift.id, Number(countedCashInput), closeNotes)
    if (ok) {
      setShowCloseShiftModal(false)
      setCloseNotes('')
    }
  }

  const submitDiscount = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !discountModal) return
    const ok = await onApplyDiscount(
      selectedOrder,
      discountModal.type,
      Number(discountModal.value),
      discountModal.reason,
    )
    if (ok) {
      setDiscountModal(null)
    }
  }

  return (
    <>
      <header>
        <div>
          <p className="eyebrow coral">CASHIER & SHIFT BILLING</p>
          <h1>Accurate settlement & drawer control.</h1>
          <p className="muted">{restaurantName} · Split tender, cash change & sequential fiscal invoices</p>
        </div>
        <div className="header-actions">
          {currentShift ? (
            <>
              <span className="live">
                <i /> Shift Open · Drawer ৳{Number(currentShift.expected_cash).toFixed(2)}
              </span>
              <button
                type="button"
                className="secondary compact"
                onClick={() => {
                  setCountedCashInput(currentShift.expected_cash)
                  setShowCloseShiftModal(true)
                }}
              >
                Close shift
              </button>
            </>
          ) : (
            <button
              type="button"
              className="primary compact"
              onClick={() => setShowOpenShiftModal(true)}
            >
              ＋ Open cashier shift
            </button>
          )}
        </div>
      </header>

      <section className="summary-grid">
        <article>
          <span className="summary-icon green">৳</span>
          <div>
            <strong>৳{currentShift ? Number(currentShift.expected_cash).toFixed(0) : '0'}</strong>
            <p>Expected drawer cash</p>
          </div>
        </article>
        <article>
          <span className="summary-icon blue">▤</span>
          <div>
            <strong>৳{currentShift ? Number(currentShift.totals_by_method.TOTAL).toFixed(0) : '0'}</strong>
            <p>Shift captured sales</p>
          </div>
        </article>
        <article>
          <span className="summary-icon amber">◷</span>
          <div>
            <strong>{orders.filter((o) => o.status === 'BILL_REQUESTED' || o.status === 'PARTIALLY_PAID').length}</strong>
            <p>Awaiting settlement</p>
          </div>
        </article>
        <article>
          <span className="summary-icon ink">#</span>
          <div>
            <strong>{recentInvoices.length}</strong>
            <p>Recent invoices</p>
          </div>
        </article>
      </section>

      <div className="pos-split">
        <section className="pos-catalog">
          <div className="zone-title">
            <h2>Open checks ready for billing</h2>
            <span>{orders.length} open</span>
          </div>

          {orders.length === 0 ? (
            <div className="inline-empty">No open table checks right now.</div>
          ) : (
            <div className="cashier-checks-grid">
              {orders.map((ord) => (
                <button
                  key={ord.id}
                  type="button"
                  className={`order-chip ${selectedOrder?.id === ord.id ? 'active' : ''}`}
                  onClick={() => void onSelectOrder(ord.id)}
                >
                  <div className="order-chip-top">
                    <strong>
                      {ord.table?.code} · {ord.table?.display_name}
                    </strong>
                    <span className={`line-state-badge state-${ord.status.toLowerCase()}`}>
                      {ord.status.replace('_', ' ')}
                    </span>
                  </div>
                  <div className="order-chip-bottom">
                    <span>{ord.order_number}</span>
                    <strong>Due: ৳{Number(ord.balance_due).toFixed(2)}</strong>
                  </div>
                </button>
              ))}
            </div>
          )}

          <div className="zone-title" style={{ marginTop: '28px' }}>
            <h2>Recent finalized invoices</h2>
            <span>Tap to view or reprint 80mm receipt</span>
          </div>

          {recentInvoices.length === 0 ? (
            <div className="inline-empty">No invoices have been finalized yet.</div>
          ) : (
            <div className="zone-list">
              {recentInvoices.slice(0, 8).map((inv) => (
                <div className="zone-row" key={inv.id}>
                  <div>
                    <strong>
                      {inv.invoice_number} · {inv.table_code} ({inv.table_name})
                    </strong>
                    <span>
                      ৳{Number(inv.total).toFixed(2)} · Cashier: {inv.cashier_name} ·{' '}
                      {new Date(inv.issued_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                  <div>
                    <button type="button" onClick={() => onViewInvoice(inv)}>
                      🖨 View / Reprint Receipt
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {recentShifts.length > 0 && (
            <>
              <div className="zone-title" style={{ marginTop: '28px' }}>
                <h2>Shift history & variance review</h2>
                <span>Manager reconciliation</span>
              </div>
              <div className="zone-list">
                {recentShifts.slice(0, 6).map((sh) => (
                  <div className="zone-row" key={sh.id}>
                    <div>
                      <strong>
                        Shift #{sh.id} · {sh.cashier_name} ({sh.status})
                      </strong>
                      <span>
                        Float: ৳{Number(sh.opening_float).toFixed(2)} · Expected Cash: ৳
                        {Number(sh.expected_cash).toFixed(2)}
                        {sh.counted_cash !== null &&
                          ` · Counted: ৳${Number(sh.counted_cash).toFixed(2)} (Variance: ৳${Number(sh.variance).toFixed(2)})`}
                      </span>
                    </div>
                    <div>
                      {sh.status === 'CLOSED' && canApproveShift && (
                        <button
                          type="button"
                          className="activate-link"
                          disabled={busy}
                          onClick={() => void onApproveShift(sh.id)}
                        >
                          ✓ Approve shift
                        </button>
                      )}
                      {sh.approved_by_name && <span>Approved by {sh.approved_by_name}</span>}
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
        </section>

        <aside className="pos-ticket">
          {!selectedOrder ? (
            <div className="ticket-empty" style={{ padding: '60px 24px' }}>
              <p>Select an open check on the left to settle payment.</p>
              <span>You can apply discounts, record split tenders, and issue sequential tax invoices.</span>
            </div>
          ) : (
            <>
              <div className="ticket-header">
                <div>
                  <div className="ticket-badges">
                    <span className="ticket-table-badge">
                      {selectedOrder.table?.code} · {selectedOrder.table?.display_name}
                    </span>
                    <span className={`ticket-status-pill status-pill-${selectedOrder.status.toLowerCase()}`}>
                      {selectedOrder.status.replace('_', ' ')}
                    </span>
                  </div>
                  <h2>{selectedOrder.order_number}</h2>
                  <p className="muted">
                    {activeItems.length} lines · {selectedOrder.guest_count} guests
                  </p>
                </div>
                <div className="cashier-due-badge">
                  <span>BALANCE DUE</span>
                  <strong>৳{balanceDue.toFixed(2)}</strong>
                </div>
              </div>

              <div className="ticket-summary">
                <div className="summary-row">
                  <span>Subtotal</span>
                  <strong>৳{Number(selectedOrder.subtotal).toFixed(2)}</strong>
                </div>
                <div className="summary-row">
                  <span>
                    Discount{' '}
                    {Number(selectedOrder.discount_amount) > 0 ? (
                      <button
                        type="button"
                        className="inline-link-btn"
                        disabled={busy}
                        onClick={() => void onRemoveDiscount(selectedOrder)}
                      >
                        (Remove)
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="inline-link-btn"
                        disabled={busy}
                        onClick={() =>
                          setDiscountModal({ type: 'PERCENTAGE', value: '5', reason: '' })
                        }
                      >
                        (＋ Add discount)
                      </button>
                    )}
                  </span>
                  <strong>−৳{Number(selectedOrder.discount_amount).toFixed(2)}</strong>
                </div>
                <div className="summary-row">
                  <span>VAT / Tax</span>
                  <strong>৳{Number(selectedOrder.tax_amount).toFixed(2)}</strong>
                </div>
                <div className="summary-row total-row">
                  <span>Order Total</span>
                  <strong>৳{Number(selectedOrder.total).toFixed(2)}</strong>
                </div>
                {Number(selectedOrder.paid_amount) > 0 && (
                  <div className="summary-row" style={{ color: '#21745d', fontWeight: 700 }}>
                    <span>Captured Payments</span>
                    <strong>−৳{Number(selectedOrder.paid_amount).toFixed(2)}</strong>
                  </div>
                )}
              </div>

              {selectedOrder.payments && selectedOrder.payments.length > 0 && (
                <div className="ticket-kots-strip">
                  <span className="kots-strip-label">Captured Split Payments:</span>
                  <div className="kots-strip-pills">
                    {selectedOrder.payments.map((p) => (
                      <span key={p.id} className="kot-mini-pill kot-mini-ready">
                        ✓ {p.method}: ৳{Number(p.amount).toFixed(2)}
                        {p.provider_reference ? ` (${p.provider_reference})` : ''}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {!currentShift ? (
                <div className="ticket-actions">
                  <div className="alert" style={{ margin: 0 }}>
                    <span>Open a cashier shift to record payments and issue invoices.</span>
                  </div>
                  <button
                    type="button"
                    className="primary ticket-primary-cta"
                    onClick={() => setShowOpenShiftModal(true)}
                  >
                    ＋ Open Cashier Shift
                  </button>
                </div>
              ) : (
                <form className="cashier-tender-box" onSubmit={handleSplitPayment}>
                  <span className="picker-label">Payment Method</span>
                  <div className="tender-methods">
                    {(['CASH', 'CARD', 'BKASH', 'NAGAD'] as const).map((m) => (
                      <button
                        key={m}
                        type="button"
                        className={method === m ? 'active' : ''}
                        onClick={() => setMethod(m)}
                      >
                        {m}
                      </button>
                    ))}
                  </div>

                  <div className="quick-cash-pills">
                    <button
                      type="button"
                      onClick={() => {
                        const exact = balanceDue.toFixed(2)
                        setAmount(exact)
                        setTendered(exact)
                      }}
                    >
                      Exact (৳{balanceDue.toFixed(0)})
                    </button>
                    {[500, 1000, 2000].map((preset) => (
                      <button
                        key={preset}
                        type="button"
                        onClick={() => {
                          setAmount(Math.min(balanceDue, preset).toFixed(2))
                          setTendered(String(preset))
                        }}
                      >
                        ৳{preset}
                      </button>
                    ))}
                  </div>

                  <div className="field-row">
                    <label>
                      Payment amount (৳)
                      <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                        value={amount}
                        onChange={(e) => {
                          setAmount(e.target.value)
                          if (method === 'CASH' && Number(tendered) < Number(e.target.value)) {
                            setTendered(e.target.value)
                          }
                        }}
                      />
                    </label>

                    {method === 'CASH' ? (
                      <label>
                        Cash tendered (৳)
                        <input
                          type="number"
                          step="0.01"
                          min="0.01"
                          required
                          value={tendered}
                          onChange={(e) => setTendered(e.target.value)}
                        />
                      </label>
                    ) : (
                      <label>
                        {method} Reference / TrxID
                        <input
                          required
                          minLength={3}
                          placeholder={`Enter ${method} reference`}
                          value={providerRef}
                          onChange={(e) => setProviderRef(e.target.value)}
                        />
                      </label>
                    )}
                  </div>

                  {method === 'CASH' && changePreview > 0 && (
                    <div className="change-callout">
                      <span>Change to return to guest:</span>
                      <strong>৳{changePreview.toFixed(2)}</strong>
                    </div>
                  )}

                  <div className="cashier-cta-stack">
                    {onPrintGuestBill && activeItems.length > 0 && (
                      <button
                        type="button"
                        className="secondary ticket-primary-cta"
                        disabled={busy}
                        onClick={() => onPrintGuestBill(selectedOrder)}
                      >
                        🖨 Print Guest Bill Copy (Table Check)
                      </button>
                    )}
                    {numAmount > 0 && numAmount < balanceDue - 0.009 && (
                      <button type="submit" className="secondary ticket-primary-cta" disabled={busy}>
                        ＋ Record Partial / Split Payment (৳{numAmount.toFixed(2)})
                      </button>
                    )}
                    <button
                      type="button"
                      className="primary ticket-primary-cta"
                      disabled={
                        busy ||
                        activeItems.length === 0 ||
                        (balanceDue > 0.001 && method !== 'CASH' && providerRef.trim().length < 3)
                      }
                      onClick={() => void handlePayAndFinalize()}
                    >
                      ✓ Pay & Finalize Invoice · ৳{balanceDue.toFixed(2)}
                    </button>
                  </div>
                </form>
              )}
            </>
          )}
        </aside>
      </div>

      {showOpenShiftModal && (
        <div className="modal-backdrop" onMouseDown={() => setShowOpenShiftModal(false)}>
          <form className="modal" onSubmit={submitOpenShift} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">CASHIER SHIFT</p>
                <h2>Open cashier shift</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowOpenShiftModal(false)}>
                ×
              </button>
            </div>
            <label>
              Opening cash drawer float (BDT ৳)
              <input
                type="number"
                step="0.01"
                min="0"
                required
                value={openingFloatInput}
                onChange={(e) => setOpeningFloatInput(e.target.value)}
              />
            </label>
            <label>
              Shift note (optional)
              <input
                placeholder="e.g. Morning counter register"
                value={shiftNotes}
                onChange={(e) => setShiftNotes(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setShowOpenShiftModal(false)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Opening…' : 'Open shift'}
              </button>
            </div>
          </form>
        </div>
      )}

      {showCloseShiftModal && currentShift && (
        <div className="modal-backdrop" onMouseDown={() => setShowCloseShiftModal(false)}>
          <form className="modal" onSubmit={submitCloseShift} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">CLOSE CASHIER SHIFT</p>
                <h2>Reconcile cash drawer</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowCloseShiftModal(false)}>
                ×
              </button>
            </div>

            <div className="price-history-box">
              <strong>Expected Shift Summary</strong>
              <ul>
                <li>
                  <span>Opening</span>
                  <span>Drawer Float</span>
                  <span>৳{Number(currentShift.opening_float).toFixed(2)}</span>
                </li>
                <li>
                  <span>Cash Sales</span>
                  <span>Captured</span>
                  <span>৳{Number(currentShift.totals_by_method.CASH).toFixed(2)}</span>
                </li>
                <li>
                  <span>Expected</span>
                  <span>Total Cash in Drawer</span>
                  <strong>৳{Number(currentShift.expected_cash).toFixed(2)}</strong>
                </li>
              </ul>
            </div>

            <label>
              Counted physical cash in drawer (BDT ৳)
              <input
                type="number"
                step="0.01"
                min="0"
                required
                value={countedCashInput}
                onChange={(e) => setCountedCashInput(e.target.value)}
              />
            </label>
            <label>
              Closing notes / variance explanation
              <input
                placeholder="Optional note on drawer count"
                value={closeNotes}
                onChange={(e) => setCloseNotes(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setShowCloseShiftModal(false)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Closing…' : 'Close shift'}
              </button>
            </div>
          </form>
        </div>
      )}

      {discountModal && selectedOrder && (
        <div className="modal-backdrop" onMouseDown={() => setDiscountModal(null)}>
          <form className="modal" onSubmit={submitDiscount} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">ORDER DISCOUNT</p>
                <h2>Apply discount to {selectedOrder.order_number}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setDiscountModal(null)}>
                ×
              </button>
            </div>
            <div className="field-row">
              <label>
                Discount type
                <select
                  value={discountModal.type}
                  onChange={(e) =>
                    setDiscountModal({
                      ...discountModal,
                      type: e.target.value as 'PERCENTAGE' | 'FIXED',
                    })
                  }
                >
                  <option value="PERCENTAGE">Percentage (%)</option>
                  <option value="FIXED">Fixed Amount (৳)</option>
                </select>
              </label>
              <label>
                {discountModal.type === 'PERCENTAGE' ? 'Percentage (%)' : 'Amount (৳)'}
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  value={discountModal.value}
                  onChange={(e) => setDiscountModal({ ...discountModal, value: e.target.value })}
                />
              </label>
            </div>
            <label>
              Reason for discount (mandatory; &gt;5% requires Manager role)
              <input
                required
                minLength={3}
                placeholder="e.g. Staff meal / manager courtesy"
                value={discountModal.reason}
                onChange={(e) => setDiscountModal({ ...discountModal, reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setDiscountModal(null)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Applying…' : 'Apply discount'}
              </button>
            </div>
          </form>
        </div>
      )}
    </>
  )
}
