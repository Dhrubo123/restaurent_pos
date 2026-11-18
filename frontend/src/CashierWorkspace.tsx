import { useEffect, useMemo, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { printThermalPaper, type InvoiceRecord } from './InvoicePrintModal.tsx'
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
  ownerInvoices: InvoiceRecord[]
  canChargeOwner: boolean
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
  onChargeOwner: (order: OrderRecord) => Promise<boolean>
  onPayOwnerInvoice: (
    invoice: InvoiceRecord,
    payload: { method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'; amount: number; tendered_amount?: number; provider_reference?: string },
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
  ownerInvoices,
  canChargeOwner,
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
  onChargeOwner,
  onPayOwnerInvoice,
  onViewInvoice,
  onPrintGuestBill,
}: Props) {
  const [method, setMethod] = useState<'CASH' | 'CARD' | 'BKASH' | 'NAGAD'>('CASH')
  const [amount, setAmount] = useState('')
  const [tendered, setTendered] = useState('')
  const [providerRef, setProviderRef] = useState('')
  const [ownerPaymentInvoice, setOwnerPaymentInvoice] = useState<InvoiceRecord | null>(null)
  const [ownerPaymentAmount, setOwnerPaymentAmount] = useState('')
  const [ownerPaymentMethod, setOwnerPaymentMethod] = useState<'CASH' | 'CARD' | 'BKASH' | 'NAGAD'>('CASH')
  const [ownerPaymentReference, setOwnerPaymentReference] = useState('')
  const [ownerStatementMonth, setOwnerStatementMonth] = useState(() => {
    const today = new Date()
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`
  })
  const [splitGuestInput, setSplitGuestInput] = useState<string | null>(null)
  const [equalSplit, setEqualSplit] = useState<{
    orderId: number
    balanceInPaisa: number
    guestsRemaining: number
  } | null>(null)

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
  const [showZReportModal, setShowZReportModal] = useState(false)
  const zReportWrapRef = useRef<HTMLDivElement | null>(null)
  const activeOrLatestShift = currentShift ?? recentShifts[0] ?? null

  const balanceDue = Number(selectedOrder?.balance_due ?? 0)
  const monthlyOwnerInvoices = ownerInvoices.filter((invoice) => {
    const issuedAt = new Date(invoice.issued_at)
    return `${issuedAt.getFullYear()}-${String(issuedAt.getMonth() + 1).padStart(2, '0')}` === ownerStatementMonth
  })
  const ownerOutstanding = ownerInvoices.reduce(
    (sum, invoice) => sum + Math.max(0, Number(invoice.total) - Number(invoice.paid_amount)),
    0,
  )
  const balanceInPaisa = Math.round(balanceDue * 100)
  const guestsRemaining =
    equalSplit && equalSplit.orderId === selectedOrder?.id && equalSplit.balanceInPaisa === balanceInPaisa
      ? equalSplit.guestsRemaining
      : 0
  const suggestedAmount = (Math.ceil(balanceInPaisa / (guestsRemaining || 1)) / 100).toFixed(2)
  const splitGuestCount = Number(splitGuestInput)
  const canApplyEqualSplit =
    Number.isInteger(splitGuestCount) && splitGuestCount >= 2 && splitGuestCount <= Math.min(100, balanceInPaisa)

  useEffect(() => {
    setAmount(suggestedAmount)
    setTendered(suggestedAmount)
    setProviderRef('')
    setSplitGuestInput(null)
  }, [selectedOrder?.id, balanceInPaisa, guestsRemaining, suggestedAmount])

  const applyEqualSplit = () => {
    if (!selectedOrder || busy || !canApplyEqualSplit) return
    const share = (Math.ceil(balanceInPaisa / splitGuestCount) / 100).toFixed(2)
    setEqualSplit({ orderId: selectedOrder.id, balanceInPaisa, guestsRemaining: splitGuestCount })
    setAmount(share)
    setTendered(share)
    setSplitGuestInput(null)
  }

  const activeItems = useMemo(
    () => (selectedOrder?.items ?? []).filter((i) => i.status !== 'CANCELLED'),
    [selectedOrder],
  )

  const numAmount = Number(amount || 0)
  const numTendered = method === 'CASH' ? Number(tendered) : numAmount
  const isPartialPayment = numAmount > 0 && numAmount < balanceDue
  const isValidPayment =
    numAmount > 0 &&
    numAmount <= balanceDue &&
    (method === 'CASH' ? numTendered >= numAmount : providerRef.trim().length >= 3)
  const changePreview =
    method === 'CASH' ? Math.max(0, numTendered - Math.min(numAmount, balanceDue)) : 0
  const discountValue = Number(discountModal?.value ?? 0)
  const discountSubtotal = Number(selectedOrder?.subtotal ?? 0)
  const discountValueValid = Boolean(discountModal) &&
    discountValue > 0 &&
    (discountModal?.type === 'PERCENTAGE' ? discountValue <= 100 : discountValue <= discountSubtotal)
  const discountPreview = discountValueValid
    ? Math.round((discountModal?.type === 'PERCENTAGE'
      ? discountSubtotal * discountValue / 100
      : discountValue) * 100) / 100
    : 0

  const handleSplitPayment = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || busy || !isPartialPayment || !isValidPayment) return
    const ok = await onRecordPayment(selectedOrder, {
      method,
      amount: numAmount,
      tendered_amount: method === 'CASH' ? numTendered : undefined,
      provider_reference: method !== 'CASH' ? providerRef.trim() : undefined,
    })
    if (ok) {
      setEqualSplit(
        guestsRemaining > 1 && numAmount === Number(suggestedAmount)
          ? {
              orderId: selectedOrder.id,
              balanceInPaisa: balanceInPaisa - Math.round(numAmount * 100),
              guestsRemaining: guestsRemaining - 1,
            }
          : null,
      )
      setProviderRef('')
    }
  }

  const handlePayAndFinalize = async () => {
    if (!selectedOrder || busy || activeItems.length === 0) return
    if (balanceDue <= 0.001) {
      await onCheckoutOrder(selectedOrder)
      return
    }
    if (!isValidPayment || isPartialPayment) return
    await onCheckoutOrder(selectedOrder, {
      method,
      amount: numAmount,
      tendered_amount: method === 'CASH' ? numTendered : undefined,
      provider_reference: method !== 'CASH' ? providerRef.trim() : undefined,
    })
  }

  const submitOwnerPayment = async (event: FormEvent) => {
    event.preventDefault()
    if (!ownerPaymentInvoice) return
    const ok = await onPayOwnerInvoice(ownerPaymentInvoice, {
      method: ownerPaymentMethod,
      amount: Number(ownerPaymentAmount),
      tendered_amount: ownerPaymentMethod === 'CASH' ? Number(ownerPaymentAmount) : undefined,
      provider_reference: ownerPaymentMethod !== 'CASH' ? ownerPaymentReference.trim() : undefined,
    })
    if (ok) setOwnerPaymentInvoice(null)
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
          {(currentShift || recentShifts.length > 0 || recentInvoices.length > 0) && (
            <button
              type="button"
              className="secondary compact"
              onClick={() => setShowZReportModal(true)}
            >
              🖨 80mm Closing Slip
            </button>
          )}
          {recentInvoices.length > 0 && (
            <button
              type="button"
              className="secondary compact"
              onClick={() => onViewInvoice(recentInvoices[0])}
              title="Printer jammed or paper empty? Instantly reopen and reprint the last finalized invoice"
            >
              🖨 Reprint Last Invoice ({recentInvoices[0].invoice_number})
            </button>
          )}
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

          <div className={`owner-account-summary ${ownerOutstanding > 0 ? 'has-due' : ''}`}>
            <div>
              <h2>Owner account</h2>
              <span>{ownerOutstanding > 0 ? 'Payment due' : 'All bills settled'}</span>
            </div>
            <strong>৳{ownerOutstanding.toFixed(2)}</strong>
          </div>
          <label>
            Statement month
            <input type="month" value={ownerStatementMonth} onChange={(event) => setOwnerStatementMonth(event.target.value)} />
          </label>
          <p className="muted">
            This month · Billed ৳{monthlyOwnerInvoices.reduce((sum, inv) => sum + Number(inv.total), 0).toFixed(2)}
            {' '}· Paid ৳{monthlyOwnerInvoices.reduce((sum, inv) => sum + Number(inv.paid_amount), 0).toFixed(2)}
            {' '}· Due ৳{monthlyOwnerInvoices.reduce((sum, inv) => sum + Math.max(0, Number(inv.total) - Number(inv.paid_amount)), 0).toFixed(2)}
          </p>
          {monthlyOwnerInvoices.length === 0 ? (
            <div className="inline-empty">No owner account charges for this month.</div>
          ) : (
            <div className="zone-list">
              {monthlyOwnerInvoices.map((inv) => {
                const due = Math.max(0, Number(inv.total) - Number(inv.paid_amount))
                return (
                  <div className={`zone-row owner-invoice-row ${due > 0 ? 'has-due' : 'is-settled'}`} key={inv.id}>
                    <div>
                      <strong>{inv.invoice_number} · {new Date(inv.issued_at).toLocaleDateString()}</strong>
                      <span>Bill ৳{Number(inv.total).toFixed(2)} · Paid ৳{Number(inv.paid_amount).toFixed(2)}</span>
                    </div>
                    <strong className="owner-invoice-status">
                      {due > 0 ? `Due ৳${due.toFixed(2)}` : 'Paid in full'}
                    </strong>
                    <div>
                      <button type="button" onClick={() => onViewInvoice(inv)}>View</button>
                      {due > 0 && currentShift && (
                        <button type="button" onClick={() => {
                          setOwnerPaymentInvoice(inv)
                          setOwnerPaymentAmount(due.toFixed(2))
                          setOwnerPaymentMethod('CASH')
                          setOwnerPaymentReference('')
                        }}>Record payment</button>
                      )}
                    </div>
                  </div>
                )
              })}
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
                        onClick={() => setDiscountModal({ type: 'PERCENTAGE', value: '', reason: '' })}
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
                <div className="summary-row">
                  <span>Amount Paid</span>
                  <strong>৳{Number(selectedOrder.paid_amount ?? 0).toFixed(2)}</strong>
                </div>
                <div className="summary-row">
                  <span>Remaining Balance</span>
                  <strong>৳{balanceDue.toFixed(2)}</strong>
                </div>
              </div>

              {selectedOrder.payments && selectedOrder.payments.length > 0 && (
                <div className="ticket-kots-strip">
                  <span className="kots-strip-label">Recorded Payments</span>
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
                    <span>
                      Open a cashier shift to receive payment. You can charge this bill to the owner account below without opening a shift.
                    </span>
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
                  <div className="tender-section-heading">
                    <span className="picker-label">Payment Method</span>
                    <span className="tender-balance-pill">Due ৳{balanceDue.toFixed(2)}</span>
                  </div>
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
                    {balanceInPaisa >= 2 && (
                      <button
                        type="button"
                        disabled={busy}
                        aria-expanded={splitGuestInput !== null}
                        onClick={() => setSplitGuestInput(
                          splitGuestInput === null ? String(Math.max(2, guestsRemaining || selectedOrder.guest_count)) : null,
                        )}
                      >
                        Equal Split
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => {
                        const exact = balanceDue.toFixed(2)
                        setEqualSplit(null)
                        setSplitGuestInput(null)
                        setAmount(exact)
                        setTendered(exact)
                      }}
                    >
                      Full balance (৳{balanceDue.toFixed(2)})
                    </button>
                    {method === 'CASH' && [500, 1000, 2000].map((preset) => (
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

                  {splitGuestInput !== null && (
                    <div className="field-row">
                      <label>
                        Guests sharing the remaining balance
                        <input
                          inputMode="numeric"
                          value={splitGuestInput}
                          onChange={(event) => setSplitGuestInput(event.target.value)}
                          onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                              event.preventDefault()
                              applyEqualSplit()
                            }
                          }}
                        />
                      </label>
                      <button
                        type="button"
                        className="secondary compact"
                        disabled={busy || !canApplyEqualSplit}
                        onClick={applyEqualSplit}
                      >
                        Divide Balance
                      </button>
                    </div>
                  )}
                  {guestsRemaining > 0 && (
                    <span className="muted">
                      {guestsRemaining} remaining {guestsRemaining === 1 ? 'guest' : 'guests'} ·
                      Next share ৳{suggestedAmount}. You can edit the payment amount below.
                    </span>
                  )}

                  <div className="tender-amount-grid">
                    <label className="tender-amount-card">
                      <span className="tender-amount-title">Amount to pay</span>
                      <span className="tender-amount-input-wrap">
                        <span aria-hidden="true">৳</span>
                        <input
                          className="tender-amount-input"
                          aria-label="Payment amount in taka"
                          type="number"
                          inputMode="decimal"
                          step="0.01"
                          min="0.01"
                          max={balanceDue}
                          required
                          value={amount}
                          onChange={(e) => {
                            setAmount(e.target.value)
                            if (tendered === amount) {
                              setTendered(e.target.value)
                            }
                          }}
                        />
                      </span>
                      <small>Applied to this bill</small>
                    </label>

                    {method === 'CASH' ? (
                      <label className="tender-amount-card tender-cash-card">
                        <span className="tender-amount-title">Cash received</span>
                        <span className="tender-amount-input-wrap">
                          <span aria-hidden="true">৳</span>
                          <input
                            className="tender-amount-input"
                            aria-label="Cash received from customer in taka"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            required
                            value={tendered}
                            onChange={(e) => setTendered(e.target.value)}
                          />
                        </span>
                        <small>Money handed over by guest</small>
                      </label>
                    ) : (
                      <label className="tender-amount-card">
                        <span className="tender-amount-title">{method} Reference / TrxID</span>
                        <input
                          className="tender-reference-input"
                          required
                          minLength={3}
                          placeholder={`Enter ${method} reference`}
                          value={providerRef}
                          onChange={(e) => setProviderRef(e.target.value)}
                        />
                        <small>Required to record this payment</small>
                      </label>
                    )}
                  </div>

                  {method === 'CASH' && (
                    <div className="change-callout" aria-live="polite">
                      <span>Change to return</span>
                      <strong>৳{changePreview.toFixed(2)}</strong>
                    </div>
                  )}

                  <div className="cashier-cta-stack">
                    {balanceDue > 0 && splitGuestInput === null && (
                      <span className="muted" id="split-payment-hint">
                        {numAmount === balanceDue
                          ? 'Enter a smaller payment amount or choose Equal Split. Use Pay & Finalize Invoice for the full balance.'
                          : !isPartialPayment
                            ? 'Enter a payment amount greater than zero and below the remaining balance.'
                            : !isValidPayment
                              ? method === 'CASH'
                                ? 'Cash tendered must cover this payment amount.'
                                : 'Enter the payment reference to record this split.'
                              : 'Record this guest’s payment to update the remaining balance.'}
                      </span>
                    )}
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
                    {balanceDue > 0 && (
                      <button
                        type="submit"
                        className="secondary ticket-primary-cta"
                        disabled={busy || !isPartialPayment || !isValidPayment}
                        aria-describedby={splitGuestInput === null ? 'split-payment-hint' : undefined}
                      >
                        Record Split Payment
                      </button>
                    )}
                    <button
                      type="button"
                      className="primary ticket-primary-cta"
                      disabled={
                        busy ||
                        activeItems.length === 0 ||
                        (balanceDue > 0 && (!isValidPayment || isPartialPayment))
                      }
                      onClick={() => void handlePayAndFinalize()}
                    >
                      {balanceDue > 0
                        ? `Pay & Finalize Invoice · ৳${balanceDue.toFixed(2)}`
                        : 'Finalize Invoice'}
                    </button>
                  </div>
                </form>
              )}
              {canChargeOwner && balanceDue > 0 && (
                <button
                  type="button"
                  className="secondary ticket-primary-cta"
                  disabled={busy || activeItems.length === 0}
                  onClick={() => void onChargeOwner(selectedOrder)}
                >
                  Charge ৳{balanceDue.toFixed(2)} to Owner Account & Release Table
                </button>
              )}
            </>
          )}
        </aside>
      </div>

      {ownerPaymentInvoice && (
        <div className="modal-backdrop" onMouseDown={() => setOwnerPaymentInvoice(null)}>
          <form className="modal" onSubmit={submitOwnerPayment} onMouseDown={(event) => event.stopPropagation()}>
            <div className="modal-head">
              <h2>Pay owner invoice {ownerPaymentInvoice.invoice_number}</h2>
              <button type="button" className="icon-button" onClick={() => setOwnerPaymentInvoice(null)}>×</button>
            </div>
            <p>Balance due ৳{(Number(ownerPaymentInvoice.total) - Number(ownerPaymentInvoice.paid_amount)).toFixed(2)}</p>
            <label>Payment method
              <select value={ownerPaymentMethod} onChange={(event) => setOwnerPaymentMethod(event.target.value as typeof ownerPaymentMethod)}>
                <option value="CASH">Cash</option><option value="CARD">Card</option><option value="BKASH">bKash</option><option value="NAGAD">Nagad</option>
              </select>
            </label>
            <label>Amount received
              <input type="number" min="0.01" max={Number(ownerPaymentInvoice.total) - Number(ownerPaymentInvoice.paid_amount)} step="0.01" required value={ownerPaymentAmount} onChange={(event) => setOwnerPaymentAmount(event.target.value)} />
            </label>
            {ownerPaymentMethod !== 'CASH' && <label>Transaction reference
              <input required minLength={3} value={ownerPaymentReference} onChange={(event) => setOwnerPaymentReference(event.target.value)} />
            </label>}
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setOwnerPaymentInvoice(null)}>Cancel</button>
              <button className="primary" disabled={busy}>Record payment</button>
            </div>
          </form>
        </div>
      )}

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
            <div className="discount-type-options" role="group" aria-label="Discount type">
              <button
                type="button"
                className={discountModal.type === 'PERCENTAGE' ? 'active' : ''}
                aria-pressed={discountModal.type === 'PERCENTAGE'}
                onClick={() => setDiscountModal({ ...discountModal, type: 'PERCENTAGE', value: '' })}
              >
                <strong>Percentage</strong>
                <span>e.g. 10% off</span>
              </button>
              <button
                type="button"
                className={discountModal.type === 'FIXED' ? 'active' : ''}
                aria-pressed={discountModal.type === 'FIXED'}
                onClick={() => setDiscountModal({ ...discountModal, type: 'FIXED', value: '' })}
              >
                <strong>Fixed amount</strong>
                <span>e.g. ৳100 off</span>
              </button>
            </div>
            <label>
              {discountModal.type === 'PERCENTAGE' ? 'Enter percentage (%)' : 'Enter discount amount (৳)'}
              <input
                type="number"
                inputMode="decimal"
                step="0.01"
                min="0.01"
                max={discountModal.type === 'PERCENTAGE' ? 100 : discountSubtotal}
                placeholder={discountModal.type === 'PERCENTAGE' ? 'e.g. 10' : 'e.g. 100'}
                required
                value={discountModal.value}
                onChange={(e) => setDiscountModal({ ...discountModal, value: e.target.value })}
              />
            </label>
            <div className="discount-preview" aria-live="polite">
              <span>Items subtotal ৳{discountSubtotal.toFixed(2)}</span>
              <strong>Discount −৳{discountPreview.toFixed(2)}</strong>
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
              <button className="primary" disabled={busy || !discountValueValid || discountModal.reason.trim().length < 3}>
                {busy ? 'Applying…' : 'Apply discount'}
              </button>
            </div>
          </form>
        </div>
      )}

      {showZReportModal && (
        <div className="modal-backdrop print-backdrop" onMouseDown={() => setShowZReportModal(false)}>
          <div className="kot-print-dialog" onMouseDown={(e) => e.stopPropagation()}>
            <div className="kot-print-toolbar no-print">
              <div>
                <p className="eyebrow coral">80MM THERMAL CLOSING SLIP (Z-REPORT)</p>
                <h3>{activeOrLatestShift ? `Shift #${activeOrLatestShift.id} · ${activeOrLatestShift.cashier_name}` : 'Daily Cashier Summary'}</h3>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowZReportModal(false)}>
                ×
              </button>
            </div>

            <div className="kot-paper-wrap" ref={zReportWrapRef}>
              <article className="kot-paper" aria-label="80mm Z-Report Closing Slip">
                <div className="kot-paper-station">[{restaurantName.toUpperCase()}]</div>
                <div className="receipt-subhead">80MM SHIFT / DAILY CLOSING SLIP</div>
                <div className="receipt-copy-pill">OFFICIAL Z-REPORT SUMMARY</div>
                <div className="kot-paper-divider">======================================</div>

                <div className="kot-paper-meta">
                  <div>
                    <span>Printed: {new Date().toLocaleDateString()}</span>
                    <span>Time: {new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                  </div>
                  <div>
                    <span>Shift: {activeOrLatestShift ? `#${activeOrLatestShift.id} (${activeOrLatestShift.status})` : 'N/A'}</span>
                    <span>Cashier: {activeOrLatestShift?.cashier_name ?? 'All Staff'}</span>
                  </div>
                </div>

                {activeOrLatestShift && (
                  <>
                    <div className="kot-paper-divider">--------------------------------------</div>
                    <div className="kot-paper-totals">
                      <div className="kot-total-row">
                        <span>Opening Drawer Float</span>
                        <span>৳{Number(activeOrLatestShift.opening_float).toFixed(2)}</span>
                      </div>
                      <div className="kot-total-row">
                        <span>Cash Sales Captured</span>
                        <span>৳{Number(activeOrLatestShift.totals_by_method.CASH).toFixed(2)}</span>
                      </div>
                      <div className="kot-total-row">
                        <span>Card Sales Captured</span>
                        <span>৳{Number(activeOrLatestShift.totals_by_method.CARD).toFixed(2)}</span>
                      </div>
                      <div className="kot-total-row">
                        <span>bKash Sales Captured</span>
                        <span>৳{Number(activeOrLatestShift.totals_by_method.BKASH).toFixed(2)}</span>
                      </div>
                      <div className="kot-total-row">
                        <span>Nagad Sales Captured</span>
                        <span>৳{Number(activeOrLatestShift.totals_by_method.NAGAD).toFixed(2)}</span>
                      </div>
                      <div className="kot-total-row kot-grand-total">
                        <strong>TOTAL SHIFT SALES</strong>
                        <strong>৳{Number(activeOrLatestShift.totals_by_method.TOTAL).toFixed(2)}</strong>
                      </div>
                      <div className="kot-total-row">
                        <span>Expected Cash in Drawer</span>
                        <strong>৳{Number(activeOrLatestShift.expected_cash).toFixed(2)}</strong>
                      </div>
                      {activeOrLatestShift.counted_cash !== null && (
                        <>
                          <div className="kot-total-row">
                            <span>Counted Physical Cash</span>
                            <span>৳{Number(activeOrLatestShift.counted_cash).toFixed(2)}</span>
                          </div>
                          <div className="kot-total-row">
                            <span>Drawer Variance</span>
                            <strong>৳{Number(activeOrLatestShift.variance ?? 0).toFixed(2)}</strong>
                          </div>
                        </>
                      )}
                    </div>
                  </>
                )}

                <div className="kot-paper-divider">--------------------------------------</div>
                <div className="kot-paper-totals">
                  <div className="kot-total-row">
                    <span>Recent Finalized Invoices</span>
                    <span>{recentInvoices.length}</span>
                  </div>
                  <div className="kot-total-row">
                    <span>Open Unsettled Checks</span>
                    <span>{orders.length}</span>
                  </div>
                </div>

                <div className="kot-paper-divider">======================================</div>
                <div className="kot-paper-footer">
                  <span>Manager Sign: ___________________</span>
                  <span>{restaurantName}</span>
                </div>
                <div className="receipt-dev-credit">Developed by Tech Base</div>
              </article>
            </div>

            <div className="modal-actions no-print">
              <button type="button" className="secondary" onClick={() => setShowZReportModal(false)}>
                Close
              </button>
              <button
                type="button"
                className="primary"
                onClick={() =>
                  printThermalPaper(
                    zReportWrapRef.current,
                    `${restaurantName} · 80mm Closing Slip`,
                  )
                }
              >
                🖨 Print 80mm Closing Slip
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
