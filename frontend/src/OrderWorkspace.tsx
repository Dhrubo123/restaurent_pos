import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import {
  buildFallbackDishSvg,
  resolveMenuItemImage,
  type ItemVariant,
  type MenuCategoryRecord,
  type MenuItemRecord,
  type ModifierOption,
} from './MenuWorkspace.tsx'

export type OrderItemModifierRecord = {
  id: number
  modifier_option_id: number | null
  name: string
  unit_price: string
  quantity: number
}

export type OrderItemRecord = {
  id: number
  menu_item_id: number | null
  item_variant_id: number | null
  sku: string
  item_name: string
  variant_name: string | null
  station: 'KITCHEN' | 'BAR'
  unit_price: string
  modifier_unit_total: string
  tax_rate: string
  tax_inclusive: boolean
  quantity: string
  sent_quantity: string
  cancelled_quantity: string
  line_subtotal: string
  line_tax: string
  line_total: string
  note: string | null
  status: string
  modifiers: OrderItemModifierRecord[]
}

export type TableTransferRecord = {
  id: number
  reason: string
  transferred_at: string
  from_table?: { id: number; code: string; display_name: string }
  to_table?: { id: number; code: string; display_name: string }
  actor?: { id: number; name: string }
}

export type OrderRecord = {
  id: number
  dining_table_id: number
  order_number: string
  status: 'OPEN' | 'BILL_REQUESTED' | 'PARTIALLY_PAID' | 'PAID' | 'CANCELLED' | 'MERGED'
  guest_count: number
  version: number
  note: string | null
  subtotal: string
  discount_amount: string
  service_charge: string
  tax_amount: string
  total: string
  paid_amount: string
  balance_due: string
  opened_at: string
  table?: {
    id: number
    code: string
    display_name: string
    capacity: number
    zone?: { id: number; name: string }
  }
  waiter?: { id: number; name: string }
  items: OrderItemRecord[]
  transfers?: TableTransferRecord[]
  kots?: {
    id: number
    kot_number: string
    station: 'KITCHEN' | 'BAR'
    status: string
    reprint_count: number
    sent_at: string
    round?: { round_number: number }
  }[]
  discounts?: {
    id: number
    type: 'PERCENTAGE' | 'FLAT'
    value: string
    calculated_amount: string
    reason: string
  }[]
  payments?: {
    id: number
    method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
    amount: string
    tendered_amount: string | null
    change_given: string
    provider_reference: string | null
    status: string
    paid_at: string
  }[]
}

export type FloorTableOption = {
  id: number
  code: string
  display_name: string
  capacity: number
  zoneName: string
  active: boolean
  status: 'AVAILABLE' | 'OPEN' | 'BILL_REQUESTED' | 'PARTIALLY_PAID' | 'INACTIVE'
  orderId?: number
  orderNumber?: string
}

type CustomizeDraft = {
  item: MenuItemRecord
  variantId: number | null
  modifierOptionIds: number[]
  quantity: number
  note: string
}

type Props = {
  orders: OrderRecord[]
  selectedOrder: OrderRecord | null
  tables: FloorTableOption[]
  menuCategories: MenuCategoryRecord[]
  canCancelSentItems: boolean
  busy: boolean
  onSelectOrder: (orderId: number) => Promise<void>
  onOpenTableOrder: (tableId: number, guestCount: number) => Promise<void>
  onUpdateOrderMeta: (order: OrderRecord, guestCount: number, note: string | null) => Promise<void>
  onAddItem: (
    order: OrderRecord,
    payload: {
      menu_item_id: number
      item_variant_id?: number | null
      quantity: number
      modifier_option_ids?: number[]
      note?: string
    },
  ) => Promise<boolean>
  onUpdateItem: (order: OrderRecord, item: OrderItemRecord, quantity: number, note: string | null) => Promise<void>
  onRemoveItem: (order: OrderRecord, item: OrderItemRecord) => Promise<void>
  onCancelSentItem: (order: OrderRecord, item: OrderItemRecord, reason: string) => Promise<boolean>
  onSendRound: (order: OrderRecord) => Promise<void>
  onViewOrderKot: (kotId: number) => Promise<void>
  onRequestBill: (order: OrderRecord) => Promise<void>
  onTransferOrder: (order: OrderRecord, toTableId: number, reason: string) => Promise<boolean>
  onMergeOrder: (order: OrderRecord, targetOrderId: number, reason: string) => Promise<boolean>
  onCancelOrder: (order: OrderRecord, reason: string) => Promise<boolean>
  onOpenCashier?: () => void
  onPrintGuestBill?: (order: OrderRecord) => void
}

export default function OrderWorkspace({
  orders,
  selectedOrder,
  tables,
  menuCategories,
  canCancelSentItems,
  busy,
  onSelectOrder,
  onOpenTableOrder,
  onUpdateOrderMeta,
  onAddItem,
  onUpdateItem,
  onRemoveItem,
  onCancelSentItem,
  onSendRound,
  onViewOrderKot,
  onRequestBill,
  onTransferOrder,
  onMergeOrder,
  onCancelOrder,
  onOpenCashier,
  onPrintGuestBill,
}: Props) {
  const [selectedCategory, setSelectedCategory] = useState<number | 'all'>('all')
  const [search, setSearch] = useState('')
  const [customizeDraft, setCustomizeDraft] = useState<CustomizeDraft | null>(null)
  const [showOpenTableModal, setShowOpenTableModal] = useState(false)
  const [newTableId, setNewTableId] = useState('')
  const [newGuestCount, setNewGuestCount] = useState(2)

  const [transferModal, setTransferModal] = useState<{ toTableId: string; reason: string } | null>(null)
  const [mergeModal, setMergeModal] = useState<{ targetOrderId: string; reason: string } | null>(null)
  const [cancelModal, setCancelModal] = useState<{ reason: string } | null>(null)
  const [cancelItemModal, setCancelItemModal] = useState<{ item: OrderItemRecord; reason: string } | null>(null)
  const [editingNote, setEditingNote] = useState<string | null>(null)

  const availableTables = useMemo(
    () => tables.filter((t) => t.active && t.status === 'AVAILABLE'),
    [tables],
  )

  const otherOpenOrders = useMemo(
    () => orders.filter((o) => o.id !== selectedOrder?.id && o.status !== 'PARTIALLY_PAID'),
    [orders, selectedOrder],
  )

  const activeCategories = useMemo(
    () => menuCategories.filter((c) => c.active),
    [menuCategories],
  )

  const visibleMenuItems = useMemo(() => {
    const q = search.trim().toLowerCase()
    return activeCategories
      .filter((c) => selectedCategory === 'all' || c.id === selectedCategory)
      .flatMap((c) =>
        [...c.items, ...c.subcategories.filter((subcategory) => subcategory.active).flatMap((subcategory) => subcategory.items)]
          .filter((item) => item.active)
          .filter((item) => {
            if (!q) return true
            return (
              item.name.toLowerCase().includes(q) ||
              item.sku.toLowerCase().includes(q) ||
              (item.description ?? '').toLowerCase().includes(q)
            )
          })
          .map((item) => ({ ...item, categoryName: c.name })),
      )
  }, [activeCategories, selectedCategory, search])

  const handleTapMenuItem = async (item: MenuItemRecord) => {
    if (!selectedOrder || !item.available) return
    const hasCustomizations = item.variants.length > 0 || item.modifier_groups.length > 0
    if (hasCustomizations) {
      setCustomizeDraft({
        item,
        variantId: item.variants.find((variant) => variant.available)?.id ?? null,
        modifierOptionIds: [],
        quantity: 1,
        note: '',
      })
      return
    }

    await onAddItem(selectedOrder, {
      menu_item_id: item.id,
      quantity: 1,
    })
  }

  const submitCustomize = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !customizeDraft) return
    const ok = await onAddItem(selectedOrder, {
      menu_item_id: customizeDraft.item.id,
      item_variant_id: customizeDraft.variantId,
      quantity: customizeDraft.quantity,
      modifier_option_ids: customizeDraft.modifierOptionIds,
      note: customizeDraft.note.trim() || undefined,
    })
    if (ok) {
      setCustomizeDraft(null)
    }
  }

  const submitNewTableOrder = async (event: FormEvent) => {
    event.preventDefault()
    if (!newTableId) return
    await onOpenTableOrder(Number(newTableId), newGuestCount)
    setShowOpenTableModal(false)
    setNewTableId('')
  }

  const submitTransfer = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !transferModal) return
    const ok = await onTransferOrder(selectedOrder, Number(transferModal.toTableId), transferModal.reason)
    if (ok) {
      setTransferModal(null)
    }
  }

  const submitMerge = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !mergeModal) return
    const ok = await onMergeOrder(selectedOrder, Number(mergeModal.targetOrderId), mergeModal.reason)
    if (ok) {
      setMergeModal(null)
    }
  }

  const submitCancel = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !cancelModal) return
    const ok = await onCancelOrder(selectedOrder, cancelModal.reason)
    if (ok) {
      setCancelModal(null)
    }
  }

  const activeItems = useMemo(
    () => (selectedOrder?.items ?? []).filter((line) => line.status !== 'CANCELLED'),
    [selectedOrder],
  )

  const unsentCount = useMemo(
    () =>
      activeItems.reduce((sum, line) => {
        const unsent = Math.max(
          0,
          Number(line.quantity) - Number(line.cancelled_quantity) - Number(line.sent_quantity),
        )
        return sum + unsent
      }, 0),
    [activeItems],
  )

  const submitCancelSentItem = async (event: FormEvent) => {
    event.preventDefault()
    if (!selectedOrder || !cancelItemModal) return
    const ok = await onCancelSentItem(selectedOrder, cancelItemModal.item, cancelItemModal.reason)
    if (ok) {
      setCancelItemModal(null)
    }
  }

  return (
    <div className="order-workspace">
      <div className="order-Rail">
        <div className="order-rail-scroll">
          {orders.map((ord) => (
            <button
              key={ord.id}
              className={`order-chip ${selectedOrder?.id === ord.id ? 'active' : ''} chip-${ord.status.toLowerCase()}`}
              onClick={() => void onSelectOrder(ord.id)}
            >
              <div className="order-chip-top">
                <strong>{ord.table?.code ?? 'TBL'}</strong>
                <span>{ord.order_number}</span>
              </div>
              <div className="order-chip-bottom">
                <span>{ord.guest_count} guests</span>
                <strong>৳{Number(ord.total).toFixed(0)}</strong>
              </div>
            </button>
          ))}
        </div>
        <button
          className="primary compact"
          onClick={() => {
            setNewTableId(String(availableTables[0]?.id ?? ''))
            setNewGuestCount(2)
            setShowOpenTableModal(true)
          }}
        >
          ＋ New table order
        </button>
      </div>

      {!selectedOrder ? (
        <section className="empty-state">
          <div>▤</div>
          <h2>No active check selected</h2>
          <p>Tap an open table on the Floor or start a new table order to begin composing items.</p>
          {availableTables.length > 0 && (
            <button
              className="primary compact"
              onClick={() => {
                setNewTableId(String(availableTables[0]?.id ?? ''))
                setShowOpenTableModal(true)
              }}
            >
              ＋ Open a table
            </button>
          )}
        </section>
      ) : (
        <div className="pos-split">
          <section className="pos-catalog">
            <div className="pos-catalog-head">
              <input
                type="search"
                className="menu-search"
                placeholder="Search menu items or SKU to add…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
              <div className="zone-tabs pos-tabs">
                <button
                  className={selectedCategory === 'all' ? 'active' : ''}
                  onClick={() => setSelectedCategory('all')}
                >
                  All items
                </button>
                {activeCategories.map((cat) => (
                  <button
                    key={cat.id}
                    className={selectedCategory === cat.id ? 'active' : ''}
                    onClick={() => setSelectedCategory(cat.id)}
                  >
                    {cat.name}
                  </button>
                ))}
              </div>
            </div>

            <div className="pos-item-grid">
              {visibleMenuItems.map((item) => {
                const hasCustom = item.variants.length > 0 || item.modifier_groups.length > 0
                const inCheckQty = activeItems
                  .filter((line) => line.menu_item_id === item.id)
                  .reduce((sum, line) => sum + Math.max(0, Number(line.quantity) - Number(line.cancelled_quantity)), 0)
                return (
                  <button
                    key={item.id}
                    type="button"
                    disabled={busy || !item.available}
                    className={`pos-item-tile ${!item.available ? 'tile-soldout' : ''} ${inCheckQty > 0 ? 'tile-in-check' : ''}`}
                    onClick={() => void handleTapMenuItem(item)}
                  >
                    <div className="pos-tile-media">
                      <img
                        src={resolveMenuItemImage(item)}
                        alt={item.name}
                        loading="lazy"
                        onError={(e) => {
                          const fallback = buildFallbackDishSvg(item)
                          if (e.currentTarget.src !== fallback) e.currentTarget.src = fallback
                        }}
                      />
                      <div className="pos-tile-top">
                        <span className="table-code">{item.sku}</span>
                        <span className={`station-badge station-${item.station.toLowerCase()}`}>{item.station}</span>
                      </div>
                      {inCheckQty > 0 && (
                        <span className="pos-in-check-badge">{inCheckQty} in check</span>
                      )}
                    </div>
                    <div className="pos-tile-body">
                      <h3>{item.name}</h3>
                      {item.description && <p className="pos-tile-desc">{item.description}</p>}
                    </div>
                    <div className="pos-tile-foot">
                      <strong>৳{Number(item.price).toFixed(2)}</strong>
                      <span className="pos-add-pill">
                        {!item.available ? 'Sold out' : hasCustom ? 'Customize ＋' : 'Add ＋'}
                      </span>
                    </div>
                  </button>
                )
              })}
            </div>
          </section>

          <aside className="pos-ticket">
            <div className="ticket-header">
              <div>
                <div className="ticket-badges">
                  <span className="ticket-table-badge">
                    {selectedOrder.table?.code} · {selectedOrder.table?.display_name}
                  </span>
                  <span className={`ticket-status-pill status-pill-${selectedOrder.status.toLowerCase()}`}>
                    {selectedOrder.status.replace('_', ' ')}
                  </span>
                  <span className="version-pill">v{selectedOrder.version}</span>
                </div>
                <h2>{selectedOrder.order_number}</h2>
                <p className="muted">
                  {selectedOrder.table?.zone?.name ?? 'Dining'} · Waiter: {selectedOrder.waiter?.name ?? 'Staff'}
                </p>
              </div>

              <div className="guest-stepper" aria-label="Guest count">
                <button
                  type="button"
                  disabled={busy || selectedOrder.guest_count <= 1}
                  onClick={() => void onUpdateOrderMeta(selectedOrder, selectedOrder.guest_count - 1, selectedOrder.note)}
                >
                  −
                </button>
                <span>
                  <strong>{selectedOrder.guest_count}</strong> guests
                </span>
                <button
                  type="button"
                  disabled={busy || selectedOrder.guest_count >= 100}
                  onClick={() => void onUpdateOrderMeta(selectedOrder, selectedOrder.guest_count + 1, selectedOrder.note)}
                >
                  ＋
                </button>
              </div>
            </div>

            <div className="ticket-note-bar">
              {editingNote !== null ? (
                <form
                  className="ticket-note-form"
                  onSubmit={(e) => {
                    e.preventDefault()
                    void onUpdateOrderMeta(selectedOrder, selectedOrder.guest_count, editingNote.trim() || null)
                    setEditingNote(null)
                  }}
                >
                  <input
                    autoFocus
                    placeholder="Order note (e.g. Birthday table, nut allergy)…"
                    value={editingNote}
                    onChange={(e) => setEditingNote(e.target.value)}
                  />
                  <button type="submit" className="primary compact" disabled={busy}>
                    Save
                  </button>
                  <button type="button" className="secondary compact" onClick={() => setEditingNote(null)}>
                    Cancel
                  </button>
                </form>
              ) : (
                <button
                  type="button"
                  className="ticket-note-trigger"
                  onClick={() => setEditingNote(selectedOrder.note ?? '')}
                >
                  <span>✎ {selectedOrder.note ? `Note: ${selectedOrder.note}` : 'Add table/order note…'}</span>
                </button>
              )}
            </div>

            <div className="ticket-lines">
              {activeItems.length === 0 ? (
                <div className="ticket-empty">
                  <p>No items on this check yet.</p>
                  <span>Tap any item on the left to add it to {selectedOrder.table?.display_name}.</span>
                </div>
              ) : (
                activeItems.map((line) => {
                  const qty = Number(line.quantity)
                  const isDraft = line.status === 'DRAFT' && Number(line.sent_quantity) === 0
                  return (
                    <article key={line.id} className="ticket-line">
                      <div className="ticket-line-main">
                        <div>
                          <div className="ticket-line-title">
                            <strong>{line.item_name}</strong>
                            {line.variant_name && <span className="variant-pill">{line.variant_name}</span>}
                            <span className={`station-badge station-${line.station.toLowerCase()}`}>{line.station}</span>
                          </div>
                          {line.modifiers.length > 0 && (
                            <div className="ticket-line-mods">
                              {line.modifiers.map((m) => (
                                <span key={m.id}>
                                  ＋ {m.name} (৳{Number(m.unit_price).toFixed(0)})
                                </span>
                              ))}
                            </div>
                          )}
                          {line.note && <p className="ticket-line-note">“{line.note}”</p>}
                        </div>
                        <strong className="ticket-line-total">৳{Number(line.line_subtotal).toFixed(2)}</strong>
                      </div>

                      <div className="ticket-line-controls">
                        <span className={`line-state-badge state-${line.status.toLowerCase()}`}>
                          {line.status === 'DRAFT' ? 'UNSENT DRAFT' : line.status}
                        </span>

                        {isDraft ? (
                          <div className="line-stepper">
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() =>
                                qty <= 1
                                  ? void onRemoveItem(selectedOrder, line)
                                  : void onUpdateItem(selectedOrder, line, qty - 1, line.note)
                              }
                            >
                              {qty <= 1 ? '×' : '−'}
                            </button>
                            <span>{qty}</span>
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => void onUpdateItem(selectedOrder, line, qty + 1, line.note)}
                            >
                              ＋
                            </button>
                          </div>
                        ) : (
                          <div className="sent-line-actions">
                            <span className="sent-qty-label">Sent Qty: {qty}</span>
                            {canCancelSentItems && (
                              <button
                                type="button"
                                className="danger-link compact-cancel-btn"
                                disabled={busy}
                                onClick={() => setCancelItemModal({ item: line, reason: '' })}
                              >
                                Void sent item
                              </button>
                            )}
                          </div>
                        )}
                      </div>
                    </article>
                  )
                })
              )}
            </div>

            {selectedOrder.kots && selectedOrder.kots.length > 0 && (
              <div className="ticket-kots-strip">
                <span className="kots-strip-label">KOT Tickets (tap to print 80mm slip):</span>
                <div className="kots-strip-pills">
                  {selectedOrder.kots.map((k) => (
                    <button
                      key={k.id}
                      type="button"
                      className={`kot-mini-pill kot-mini-${k.status.toLowerCase()}`}
                      onClick={() => void onViewOrderKot(k.id)}
                    >
                      🖨 {k.kot_number} · {k.station} · {k.status}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {selectedOrder.transfers && selectedOrder.transfers.length > 0 && (
              <div className="transfer-banner">
                Transferred from {selectedOrder.transfers[0].from_table?.code} →{' '}
                {selectedOrder.transfers[0].to_table?.code} ({selectedOrder.transfers[0].reason})
              </div>
            )}

            <div className="ticket-summary">
              <div className="summary-row">
                <span>Subtotal ({activeItems.reduce((sum, i) => sum + Number(i.quantity), 0)} items)</span>
                <strong>৳{Number(selectedOrder.subtotal).toFixed(2)}</strong>
              </div>
              <div className="summary-row">
                <span>VAT / Tax</span>
                <strong>৳{Number(selectedOrder.tax_amount).toFixed(2)}</strong>
              </div>
              {Number(selectedOrder.discount_amount) > 0 && (
                <div className="summary-row">
                  <span>Discount</span>
                  <strong>−৳{Number(selectedOrder.discount_amount).toFixed(2)}</strong>
                </div>
              )}
              <div className="summary-row total-row">
                <span>Total Due</span>
                <strong>৳{Number(selectedOrder.total).toFixed(2)}</strong>
              </div>
            </div>

            <div className="ticket-actions">
              <div className="ticket-secondary-actions">
                <button
                  type="button"
                  className="secondary compact"
                  disabled={busy || availableTables.length === 0}
                  onClick={() =>
                    setTransferModal({
                      toTableId: String(availableTables[0]?.id ?? ''),
                      reason: '',
                    })
                  }
                >
                  ⇄ Transfer
                </button>
                <button
                  type="button"
                  className="secondary compact"
                  disabled={busy || otherOpenOrders.length === 0}
                  onClick={() =>
                    setMergeModal({
                      targetOrderId: String(otherOpenOrders[0]?.id ?? ''),
                      reason: '',
                    })
                  }
                >
                  ⇶ Merge
                </button>
                <button
                  type="button"
                  className="secondary compact danger-link"
                  disabled={busy}
                  onClick={() => setCancelModal({ reason: '' })}
                >
                  Cancel
                </button>
              </div>

              {unsentCount > 0 && (
                <button
                  type="button"
                  className="primary ticket-send-kot-cta"
                  disabled={busy}
                  onClick={() => void onSendRound(selectedOrder)}
                >
                  ♨ Send Round to Kitchen / Bar ({unsentCount} unsent)
                </button>
              )}

              {activeItems.length > 0 && onPrintGuestBill && (
                <button
                  type="button"
                  className="secondary ticket-primary-cta"
                  disabled={busy}
                  onClick={() => onPrintGuestBill(selectedOrder)}
                >
                  🖨 Print Guest Bill Copy (Table Check)
                </button>
              )}

              {selectedOrder.status === 'BILL_REQUESTED' || selectedOrder.status === 'PARTIALLY_PAID' ? (
                onOpenCashier ? (
                  <button
                    type="button"
                    className="primary ticket-primary-cta"
                    disabled={busy}
                    onClick={onOpenCashier}
                  >
                    ৳ Go to Cashier Settlement · ৳{Number(selectedOrder.balance_due).toFixed(2)} →
                  </button>
                ) : (
                  <button type="button" className="secondary ticket-primary-cta" disabled>
                    ✓ Bill Requested — Ready for Cashier
                  </button>
                )
              ) : (
                <button
                  type="button"
                  className={unsentCount > 0 ? 'secondary ticket-primary-cta' : 'primary ticket-primary-cta'}
                  disabled={busy || activeItems.length === 0}
                  onClick={() => void onRequestBill(selectedOrder)}
                >
                  Request Bill · ৳{Number(selectedOrder.total).toFixed(2)}
                </button>
              )}
            </div>
          </aside>
        </div>
      )}

      {cancelItemModal && selectedOrder && (
        <div className="modal-backdrop" onMouseDown={() => setCancelItemModal(null)}>
          <form className="modal" onSubmit={submitCancelSentItem} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">VOID SENT ITEM</p>
                <h2>Cancel {cancelItemModal.item.item_name}?</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setCancelItemModal(null)}>
                ×
              </button>
            </div>
            <label>
              Mandatory manager reason (notifies station & adjusts bill)
              <input
                required
                minLength={3}
                placeholder="e.g. Guest changed mind / kitchen out of stock"
                value={cancelItemModal.reason}
                onChange={(e) => setCancelItemModal({ ...cancelItemModal, reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setCancelItemModal(null)}>
                Keep item
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Cancelling…' : 'Confirm void'}
              </button>
            </div>
          </form>
        </div>
      )}

      {customizeDraft && (
        <CustomizeModal
          draft={customizeDraft}
          busy={busy}
          onChange={setCustomizeDraft}
          onClose={() => setCustomizeDraft(null)}
          onSubmit={submitCustomize}
        />
      )}

      {showOpenTableModal && (
        <div className="modal-backdrop" onMouseDown={() => setShowOpenTableModal(false)}>
          <form className="modal" onSubmit={submitNewTableOrder} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">NEW TABLE CHECK</p>
                <h2>Open a table</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowOpenTableModal(false)}>
                ×
              </button>
            </div>
            <label>
              Available table
              <select required value={newTableId} onChange={(e) => setNewTableId(e.target.value)}>
                <option value="">Choose a table</option>
                {availableTables.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.code} · {t.display_name} ({t.zoneName}, {t.capacity} seats)
                  </option>
                ))}
              </select>
            </label>
            <label>
              Guest count
              <input
                required
                type="number"
                min="1"
                max="100"
                value={newGuestCount}
                onChange={(e) => setNewGuestCount(Number(e.target.value))}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setShowOpenTableModal(false)}>
                Cancel
              </button>
              <button className="primary" disabled={busy || !newTableId}>
                {busy ? 'Opening…' : 'Open table order'}
              </button>
            </div>
          </form>
        </div>
      )}

      {transferModal && selectedOrder && (
        <div className="modal-backdrop" onMouseDown={() => setTransferModal(null)}>
          <form className="modal" onSubmit={submitTransfer} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">TABLE TRANSFER</p>
                <h2>Move {selectedOrder.order_number}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setTransferModal(null)}>
                ×
              </button>
            </div>
            <label>
              Destination table (Available only)
              <select
                required
                value={transferModal.toTableId}
                onChange={(e) => setTransferModal({ ...transferModal, toTableId: e.target.value })}
              >
                {availableTables.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.code} · {t.display_name} ({t.zoneName})
                  </option>
                ))}
              </select>
            </label>
            <label>
              Reason for transfer (mandatory)
              <input
                required
                minLength={3}
                placeholder="e.g. Guest requested indoor seating"
                value={transferModal.reason}
                onChange={(e) => setTransferModal({ ...transferModal, reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setTransferModal(null)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Transferring…' : 'Confirm transfer'}
              </button>
            </div>
          </form>
        </div>
      )}

      {mergeModal && selectedOrder && (
        <div className="modal-backdrop" onMouseDown={() => setMergeModal(null)}>
          <form className="modal" onSubmit={submitMerge} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">MERGE ORDERS</p>
                <h2>Merge {selectedOrder.order_number} into another table</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setMergeModal(null)}>
                ×
              </button>
            </div>
            <label>
              Target open order
              <select
                required
                value={mergeModal.targetOrderId}
                onChange={(e) => setMergeModal({ ...mergeModal, targetOrderId: e.target.value })}
              >
                {otherOpenOrders.map((ord) => (
                  <option key={ord.id} value={ord.id}>
                    {ord.table?.code} ({ord.order_number}) · ৳{Number(ord.total).toFixed(2)}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Reason for merge (mandatory)
              <input
                required
                minLength={3}
                placeholder="e.g. Two parties combined tables"
                value={mergeModal.reason}
                onChange={(e) => setMergeModal({ ...mergeModal, reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setMergeModal(null)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Merging…' : 'Merge checks'}
              </button>
            </div>
          </form>
        </div>
      )}

      {cancelModal && selectedOrder && (
        <div className="modal-backdrop" onMouseDown={() => setCancelModal(null)}>
          <form className="modal" onSubmit={submitCancel} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">CANCEL ORDER</p>
                <h2>Cancel {selectedOrder.order_number}?</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setCancelModal(null)}>
                ×
              </button>
            </div>
            <label>
              Cancellation reason (mandatory for audit log)
              <input
                required
                minLength={3}
                placeholder="e.g. Guests walked out before ordering"
                value={cancelModal.reason}
                onChange={(e) => setCancelModal({ reason: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setCancelModal(null)}>
                Keep order
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Cancelling…' : 'Confirm cancel'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}

function CustomizeModal({
  draft,
  busy,
  onChange,
  onClose,
  onSubmit,
}: {
  draft: CustomizeDraft
  busy: boolean
  onChange: (draft: CustomizeDraft) => void
  onClose: () => void
  onSubmit: (event: FormEvent) => void
}) {
  const selectedVariant: ItemVariant | undefined = draft.item.variants.find((v) => v.id === draft.variantId)
  const basePrice = Number(selectedVariant ? selectedVariant.price : draft.item.price)

  const allOptions: ModifierOption[] = draft.item.modifier_groups.flatMap((g) => g.options)
  const modsTotal = draft.modifierOptionIds.reduce((sum, id) => {
    const opt = allOptions.find((o) => o.id === id)
    return sum + Number(opt?.price ?? 0)
  }, 0)

  const previewTotal = (basePrice + modsTotal) * draft.quantity
  const invalidGroups = draft.item.modifier_groups.filter((group) => {
    const selected = group.options.filter((option) => draft.modifierOptionIds.includes(option.id)).length
    const minimum = group.required ? Math.max(1, group.min_selections) : group.min_selections
    return selected < minimum || selected > group.max_selections
  })
  const missingVariant = draft.item.variants.some((variant) => variant.available) && !draft.variantId

  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <form className="modal zone-modal" onSubmit={onSubmit} onMouseDown={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <div>
            <p className="eyebrow coral">{draft.item.station} ITEM</p>
            <h2>{draft.item.name}</h2>
          </div>
          <button type="button" className="icon-button" onClick={onClose}>
            ×
          </button>
        </div>

        {draft.item.variants.length > 0 && (
          <div className="modifier-picker">
            <span className="picker-label">Choose size / variant</span>
            <div className="modifier-checkboxes">
              {draft.item.variants.map((v) => (
                <label key={v.id} className="checkbox-pill">
                  <input
                    type="radio"
                    name="variant"
                    disabled={!v.available}
                    checked={draft.variantId === v.id}
                    onChange={() => onChange({ ...draft, variantId: v.id })}
                  />
                  <span>
                    {v.name} (৳{Number(v.price).toFixed(0)}) {!v.available && '· Sold out'}
                  </span>
                </label>
              ))}
            </div>
          </div>
        )}

        {draft.item.modifier_groups.map((group) => (
          <div className="modifier-picker" key={group.id}>
            <span className="picker-label">{group.name} {group.required ? `(choose ${Math.max(1, group.min_selections)}–${group.max_selections})` : `(up to ${group.max_selections})`}</span>
            <div className="modifier-checkboxes">
              {group.options.map((opt) => {
                const checked = draft.modifierOptionIds.includes(opt.id)
                const selectedInGroup = group.options.filter((option) => draft.modifierOptionIds.includes(option.id)).length
                return (
                  <label key={opt.id} className="checkbox-pill">
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={!opt.available || (!checked && selectedInGroup >= group.max_selections)}
                      onChange={(e) => {
                        const next = e.target.checked
                          ? [...draft.modifierOptionIds, opt.id]
                          : draft.modifierOptionIds.filter((id) => id !== opt.id)
                        onChange({ ...draft, modifierOptionIds: next })
                      }}
                    />
                    <span>
                      {opt.name} (+৳{Number(opt.price).toFixed(0)}) {!opt.available && '· Unavailable'}
                    </span>
                  </label>
                )
              })}
            </div>
          {invalidGroups.some((invalidGroup) => invalidGroup.id === group.id) && <span className="field-error">Choose the required number of options.</span>}
          </div>
        ))}

        <div className="field-row">
          <label>
            Quantity
            <input
              type="number"
              min="1"
              max="100"
              required
              value={draft.quantity}
              onChange={(e) => onChange({ ...draft, quantity: Math.max(1, Number(e.target.value)) })}
            />
          </label>
          <label>
            Special instruction / note
            <input
              placeholder="e.g. No mayo, extra spicy"
              value={draft.note}
              onChange={(e) => onChange({ ...draft, note: e.target.value })}
            />
          </label>
        </div>

        <div className="modal-actions">
          <button type="button" className="secondary" onClick={onClose}>
            Cancel
          </button>
          <button className="primary" disabled={busy || missingVariant || invalidGroups.length > 0}>
            {busy ? 'Adding…' : `Add to order · ৳${previewTotal.toFixed(2)}`}
          </button>
        </div>
      </form>
    </div>
  )
}
