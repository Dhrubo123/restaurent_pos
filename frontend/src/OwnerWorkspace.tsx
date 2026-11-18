import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import type { KotRecord } from './KotPrintModal.tsx'
import {
  buildFallbackDishSvg,
  resolveMenuItemImage,
  type MenuCategoryRecord,
  type MenuItemRecord,
} from './MenuWorkspace.tsx'

export type OwnerMealLine = {
  id: number
  sku: string
  item_name: string
  variant_name: string | null
  station: 'KITCHEN' | 'BAR'
  quantity: string
  line_total: string
  note: string | null
  modifiers: { name: string; unit_price: string }[]
}

export type OwnerMealRecord = {
  id: number
  order_number: string
  guest_count: number
  note: string | null
  subtotal: string
  tax_amount: string
  total: string
  opened_at: string
  waiter_name: string
  items: OwnerMealLine[]
  kots: KotRecord[]
}

export type OwnerVoidAlertRecord = {
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
  kots: KotRecord[]
  replacement_items?: {
    id: number
    item_name: string
    variant_name: string | null
    quantity: string
    line_total: string
  }[]
  replacement_kots?: KotRecord[]
}

export type OwnerSummaryData = {
  monthly_budget: string
  this_month_consumed: string
  remaining_budget: string
  over_budget_amount: string
  today_consumed: string
  this_month_orders_count: number
  month_label: string
  recent_meals: OwnerMealRecord[]
  voided_alerts?: OwnerVoidAlertRecord[]
}

type DraftCartLine = {
  key: string
  menuItem: MenuItemRecord
  variantId: number | null
  variantName: string | null
  unitPrice: number
  modifierOptionIds: number[]
  modifierNames: { name: string; price: number }[]
  quantity: number
  note: string
}

type CustomizeDraft = {
  item: MenuItemRecord
  variantId: number | null
  modifierOptionIds: number[]
  quantity: number
  note: string
}

type Props = {
  restaurantName: string
  ownerData: OwnerSummaryData | null
  menuCategories: MenuCategoryRecord[]
  canManageBudget: boolean
  isOwnerAdmin: boolean
  busy: boolean
  onRefresh: () => Promise<void>
  onUpdateBudget: (monthlyBudget: number) => Promise<boolean>
  onSubmitOwnerMeal: (payload: {
    guest_count: number
    note?: string
    items: {
      menu_item_id: number
      item_variant_id?: number | null
      quantity: number
      modifier_option_ids?: number[]
      note?: string
    }[]
  }) => Promise<boolean>
  onPrintKots: (kots: KotRecord[]) => void
}

export default function OwnerWorkspace({
  restaurantName,
  ownerData,
  menuCategories,
  canManageBudget,
  isOwnerAdmin,
  busy,
  onRefresh,
  onUpdateBudget,
  onSubmitOwnerMeal,
  onPrintKots,
}: Props) {
  const [selectedCategory, setSelectedCategory] = useState<number | 'all'>('all')
  const [search, setSearch] = useState('')
  const [cart, setCart] = useState<DraftCartLine[]>([])
  const [guestCount, setGuestCount] = useState(1)
  const [orderNote, setOrderNote] = useState('')
  const [customizeDraft, setCustomizeDraft] = useState<CustomizeDraft | null>(null)
  const [showBudgetModal, setShowBudgetModal] = useState(false)
  const [budgetInput, setBudgetInput] = useState('20000')
  const [ownerView, setOwnerView] = useState<'all' | 'voids' | 'meals'>('all')

  const activeCategories = useMemo(
    () => menuCategories.filter((c) => c.active),
    [menuCategories],
  )

  const visibleMenuItems = useMemo(() => {
    const q = search.trim().toLowerCase()
    return activeCategories
      .filter((c) => selectedCategory === 'all' || c.id === selectedCategory)
      .flatMap((c) =>
        [
          ...c.items,
          ...c.subcategories
            .filter((sub) => sub.active)
            .flatMap((sub) => sub.items),
        ]
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

  const addLineToCart = (
    item: MenuItemRecord,
    variantId: number | null,
    modifierOptionIds: number[],
    qty: number,
    note: string,
  ) => {
    const variant = variantId ? item.variants.find((v) => v.id === variantId) ?? null : null
    const unitPrice = variant ? Number(variant.price) : Number(item.price)
    const allModOptions = item.modifier_groups.flatMap((g) => g.options)
    const selectedMods = modifierOptionIds
      .map((id) => allModOptions.find((o) => o.id === id))
      .filter((o): o is NonNullable<typeof o> => Boolean(o))
      .map((o) => ({ name: o.name, price: Number(o.price) }))

    const sortedModKey = [...modifierOptionIds].sort((a, b) => a - b).join(',')
    const cleanNote = note.trim()
    const key = `${item.id}:${variantId ?? 0}:${sortedModKey}:${cleanNote}`

    setCart((prev) => {
      const existingIdx = prev.findIndex((line) => line.key === key)
      if (existingIdx >= 0) {
        const updated = [...prev]
        updated[existingIdx] = {
          ...updated[existingIdx],
          quantity: updated[existingIdx].quantity + qty,
        }
        return updated
      }
      return [
        ...prev,
        {
          key,
          menuItem: item,
          variantId,
          variantName: variant?.name ?? null,
          unitPrice,
          modifierOptionIds,
          modifierNames: selectedMods,
          quantity: qty,
          note: cleanNote,
        },
      ]
    })
  }

  const handleTapMenuItem = (item: MenuItemRecord) => {
    if (!item.available) return
    const hasCustomizations = item.variants.length > 0 || item.modifier_groups.length > 0
    if (hasCustomizations) {
      setCustomizeDraft({
        item,
        variantId: item.variants.find((v) => v.available)?.id ?? null,
        modifierOptionIds: [],
        quantity: 1,
        note: '',
      })
      return
    }
    addLineToCart(item, null, [], 1, '')
  }

  const submitCustomize = (event: FormEvent) => {
    event.preventDefault()
    if (!customizeDraft) return
    addLineToCart(
      customizeDraft.item,
      customizeDraft.variantId,
      customizeDraft.modifierOptionIds,
      customizeDraft.quantity,
      customizeDraft.note,
    )
    setCustomizeDraft(null)
  }

  const updateCartQty = (key: string, nextQty: number) => {
    if (nextQty <= 0) {
      setCart((prev) => prev.filter((l) => l.key !== key))
      return
    }
    setCart((prev) =>
      prev.map((l) => (l.key === key ? { ...l, quantity: nextQty } : l)),
    )
  }

  const cartBudgetTotal = useMemo(() => {
    return cart.reduce((sum, line) => {
      const modTotal = line.modifierNames.reduce((mSum, m) => mSum + m.price, 0)
      return sum + (line.unitPrice + modTotal) * line.quantity
    }, 0)
  }, [cart])

  const handlePlaceOwnerOrder = async () => {
    if (cart.length === 0) return
    const ok = await onSubmitOwnerMeal({
      guest_count: guestCount,
      note: orderNote.trim() || undefined,
      items: cart.map((line) => ({
        menu_item_id: line.menuItem.id,
        item_variant_id: line.variantId,
        quantity: line.quantity,
        modifier_option_ids: line.modifierOptionIds,
        note: line.note || undefined,
      })),
    })
    if (ok) {
      setCart([])
      setOrderNote('')
      setGuestCount(1)
    }
  }

  const handleSaveBudget = async (event: FormEvent) => {
    event.preventDefault()
    const ok = await onUpdateBudget(Number(budgetInput))
    if (ok) {
      setShowBudgetModal(false)
    }
  }

  const monthlyBudget = Number(ownerData?.monthly_budget ?? 20000)
  const consumed = Number(ownerData?.this_month_consumed ?? 0)
  const remaining = Number(ownerData?.remaining_budget ?? Math.max(0, monthlyBudget - consumed))
  const todayConsumed = Number(ownerData?.today_consumed ?? 0)
  const budgetPct = monthlyBudget > 0 ? Math.min(100, Math.round((consumed / monthlyBudget) * 100)) : 0

  return (
    <>
      <header>
        <div>
          <p className="eyebrow coral">OWNER HOUSE ACCOUNT & BUDGET</p>
          <h1>Owner meal ordering & budget tracker.</h1>
          <p className="muted">
            {restaurantName} · Releases Kitchen/Bar KOT & deducts Inventory — <strong>৳0 added to Sales Report</strong>
          </p>
        </div>
        <div className="header-actions">
          <span className="live">
            <i /> {ownerData?.month_label ?? 'Current Month'} · {budgetPct}% Budget Used
          </span>
          {canManageBudget && (
            <button
              type="button"
              className="secondary compact"
              onClick={() => {
                setBudgetInput(String(monthlyBudget))
                setShowBudgetModal(true)
              }}
            >
              ⚙ Set Owner Budget
            </button>
          )}
          <button type="button" className="secondary compact" disabled={busy} onClick={() => void onRefresh()}>
            ↻ Refresh
          </button>
        </div>
      </header>

      <section className="summary-grid">
        <article>
          <span className="summary-icon ink">👑</span>
          <div>
            <strong>৳{monthlyBudget.toFixed(0)}</strong>
            <p>Monthly owner budget</p>
          </div>
        </article>
        <article>
          <span className="summary-icon amber">♨</span>
          <div>
            <strong>৳{consumed.toFixed(0)}</strong>
            <p>Consumed this month ({ownerData?.this_month_orders_count ?? 0} orders)</p>
          </div>
        </article>
        <article>
          <span className="summary-icon green">✓</span>
          <div>
            <strong>৳{remaining.toFixed(0)}</strong>
            <p>Remaining owner budget</p>
          </div>
        </article>
        <article>
          <span className="summary-icon blue">📦</span>
          <div>
            <strong>৳{todayConsumed.toFixed(0)}</strong>
            <p>Today's owner meals (৳0 in Sales)</p>
          </div>
        </article>
      </section>

      {isOwnerAdmin && (
        <div className="zone-tabs" style={{ marginBottom: '18px' }}>
          <button
            type="button"
            className={ownerView === 'all' ? 'active' : ''}
            onClick={() => setOwnerView('all')}
          >
            👑 Full Owner Dashboard (Meals + Void Alerts)
          </button>
          <button
            type="button"
            className={ownerView === 'voids' ? 'active' : ''}
            onClick={() => setOwnerView('voids')}
          >
            🚨 Voided Items &amp; KOT Anti-Theft Log ({ownerData?.voided_alerts?.length ?? 0})
          </button>
          <button
            type="button"
            className={ownerView === 'meals' ? 'active' : ''}
            onClick={() => setOwnerView('meals')}
          >
            🍽 Owner Meal Ordering &amp; History ({ownerData?.this_month_orders_count ?? 0})
          </button>
        </div>
      )}

      {isOwnerAdmin && ownerView !== 'meals' && (
        <section
          className="zone-section"
          style={{
            marginBottom: '24px',
            padding: '18px 20px',
            borderRadius: '16px',
            background: '#fffafa',
            border: '1px solid #fecaca',
            borderLeft: '5px solid #dc2626',
          }}
        >
          <div className="zone-title" style={{ marginBottom: '12px' }}>
            <div>
              <p className="eyebrow coral" style={{ color: '#dc2626' }}>
                ADMIN / OWNER ANTI-THEFT MONITOR
              </p>
              <h2 style={{ margin: '2px 0 4px' }}>
                🚨 Voided Sent Items, Mandatory Reasons &amp; Replacement KOT Log
              </h2>
              <span style={{ fontSize: '0.82rem', color: '#57534e' }}>
                Whenever a Supervisor or Manager voids a sent item and changes it to another item, the void reason, original price, replacement item, and both KOTs appear here immediately.
              </span>
            </div>
            <span
              style={{
                padding: '6px 12px',
                borderRadius: '999px',
                background: '#fee2e2',
                color: '#991b1b',
                fontWeight: 800,
                fontSize: '0.78rem',
              }}
            >
              {ownerData?.voided_alerts?.length ?? 0} Voided Items Logged
            </span>
          </div>

          {!ownerData?.voided_alerts || ownerData.voided_alerts.length === 0 ? (
            <div className="inline-empty" style={{ background: '#ffffff' }}>
              No sent items have been voided by supervisors or managers yet.
            </div>
          ) : (
            <div className="reports-table-wrap" style={{ background: '#ffffff', borderRadius: '12px' }}>
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Voided By</th>
                    <th>Table &amp; Order</th>
                    <th>Voided Item &amp; Original Price</th>
                    <th>Mandatory Void Reason</th>
                    <th>Changed To / Active Items on Order</th>
                    <th>Void KOT &amp; New KOT</th>
                  </tr>
                </thead>
                <tbody>
                  {ownerData.voided_alerts.map((alert) => (
                    <tr key={alert.id}>
                      <td>
                        {new Date(alert.voided_at).toLocaleString([], {
                          month: 'short',
                          day: '2-digit',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </td>
                      <td>
                        <strong>{alert.voided_by}</strong>
                      </td>
                      <td>
                        <strong>{alert.table_code}</strong>
                        <div className="invoice-reason-sub">{alert.order_number}</div>
                      </td>
                      <td>
                        <strong style={{ color: '#b91c1c' }}>
                          −{Number(alert.quantity)}x {alert.item_name}
                          {alert.variant_name ? ` (${alert.variant_name})` : ''}
                        </strong>
                        <div className="invoice-reason-sub">
                          Original Value: <strong>৳{Number(alert.original_total).toFixed(2)}</strong> · {alert.station ?? 'KITCHEN'}
                        </div>
                      </td>
                      <td>
                        <span className="void-reason-chip">
                          “{alert.reason ?? 'No reason recorded'}”
                        </span>
                      </td>
                      <td>
                        {alert.replacement_items && alert.replacement_items.length > 0 ? (
                          <div style={{ fontSize: '0.8rem', fontWeight: 700, color: '#1e3a8a', display: 'grid', gap: '2px' }}>
                            {alert.replacement_items.map((rep) => (
                              <div key={rep.id}>
                                ⇄ {Number(rep.quantity)}x {rep.item_name}
                                {rep.variant_name ? ` (${rep.variant_name})` : ''} (৳{Number(rep.line_total).toFixed(2)})
                              </div>
                            ))}
                          </div>
                        ) : (
                          <span className="muted">Full Void (No replacement item)</span>
                        )}
                      </td>
                      <td>
                        <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                          {alert.kots && alert.kots.length > 0 && (
                            <button
                              type="button"
                              className="secondary compact"
                              onClick={() => onPrintKots(alert.kots)}
                              style={{ fontSize: '0.73rem', padding: '4px 8px' }}
                            >
                              🖨 Void KOT ({alert.kots.map((k) => k.kot_number).join(', ')})
                            </button>
                          )}
                          {alert.replacement_kots && alert.replacement_kots.length > 0 && (
                            <button
                              type="button"
                              className="primary compact"
                              onClick={() => onPrintKots(alert.replacement_kots!)}
                              style={{ fontSize: '0.73rem', padding: '4px 8px' }}
                            >
                              🖨 New KOT ({alert.replacement_kots.map((k) => k.kot_number).join(', ')})
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      <div
        style={{
          marginBottom: '20px',
          padding: '14px 18px',
          borderRadius: '14px',
          background: '#ffffff',
          border: '1px solid var(--line)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '16px',
          flexWrap: 'wrap',
        }}
      >
        <div style={{ flex: '1 1 280px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.82rem', fontWeight: 700, marginBottom: '6px' }}>
            <span>Owner Monthly Food Budget Usage ({ownerData?.month_label ?? 'This Month'})</span>
            <span>
              ৳{consumed.toFixed(2)} / ৳{monthlyBudget.toFixed(2)} ({budgetPct}%)
            </span>
          </div>
          <div style={{ height: '10px', borderRadius: '999px', background: '#eee7de', overflow: 'hidden' }}>
            <div
              style={{
                width: `${budgetPct}%`,
                height: '100%',
                background: budgetPct >= 90 ? '#c2410c' : '#ec6b1e',
                transition: 'width 0.25s ease',
              }}
            />
          </div>
        </div>
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', fontSize: '0.76rem', fontWeight: 700 }}>
          <span style={{ padding: '5px 10px', borderRadius: '999px', background: '#ecfdf5', color: '#047857' }}>
            ✓ 80mm KOT Released as Usual
          </span>
          <span style={{ padding: '5px 10px', borderRadius: '999px', background: '#eff6ff', color: '#1d4ed8' }}>
            ✓ Recipe Cut from Inventory
          </span>
          <span style={{ padding: '5px 10px', borderRadius: '999px', background: '#fff7ed', color: '#c2410c' }}>
            ✓ Excluded from Sales Report (৳0 Sale)
          </span>
        </div>
      </div>

      {ownerView !== 'voids' && (
      <div className="pos-split">
        <section className="pos-catalog">
          <div className="pos-catalog-head">
            <input
              type="search"
              className="menu-search"
              placeholder="Search food or drink for Owner order…"
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
              const inCartQty = cart
                .filter((line) => line.menuItem.id === item.id)
                .reduce((sum, line) => sum + line.quantity, 0)
              return (
                <button
                  key={item.id}
                  type="button"
                  disabled={busy || !item.available}
                  className={`pos-item-tile ${!item.available ? 'tile-soldout' : ''} ${inCartQty > 0 ? 'tile-in-check' : ''}`}
                  onClick={() => handleTapMenuItem(item)}
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
                    {inCartQty > 0 && <span className="pos-in-check-badge">{inCartQty} in check</span>}
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

          <div className="zone-title" style={{ marginTop: '28px' }}>
            <h2>Recent Owner Meals (KOT & Budget Log)</h2>
            <span>Tap any KOT ticket to reprint 80mm slip</span>
          </div>

          {!ownerData || ownerData.recent_meals.length === 0 ? (
            <div className="inline-empty">No owner meals recorded yet this month.</div>
          ) : (
            <div className="zone-list">
              {ownerData.recent_meals.map((meal) => (
                <div className="zone-row" key={meal.id}>
                  <div>
                    <strong>
                      {meal.order_number} · Budget Value: ৳{Number(meal.total).toFixed(2)} (Paid: ৳0.00 — Owner Meal)
                    </strong>
                    <span>
                      {meal.items.map((i) => `${Number(i.quantity)}x ${i.item_name}`).join(', ')} ·{' '}
                      {new Date(meal.opened_at).toLocaleString([], {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </span>
                  </div>
                  <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    {meal.kots.length > 0 && (
                      <button type="button" onClick={() => onPrintKots(meal.kots)}>
                        🖨 Reprint 80mm KOT ({meal.kots.map((k) => k.kot_number).join(', ')})
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <aside className="pos-ticket">
          <div className="ticket-header">
            <div>
              <div className="ticket-badges">
                <span className="ticket-table-badge">👑 OWNER MEAL</span>
                <span className="ticket-status-pill status-pill-open">NO SALE · ৳0 DUE</span>
              </div>
              <h2>Owner Order</h2>
              <p className="muted">KOT prints & Inventory deducts · Not added to Sales Report</p>
            </div>

            <div className="guest-stepper" aria-label="Owner guest count">
              <button
                type="button"
                disabled={busy || guestCount <= 1}
                onClick={() => setGuestCount((g) => Math.max(1, g - 1))}
              >
                −
              </button>
              <span>
                <strong>{guestCount}</strong> guests
              </span>
              <button
                type="button"
                disabled={busy || guestCount >= 50}
                onClick={() => setGuestCount((g) => g + 1)}
              >
                ＋
              </button>
            </div>
          </div>

          <div className="ticket-note-bar" style={{ padding: '10px 16px' }}>
            <input
              placeholder="Optional note (e.g. Owner Table, Less sugar, Serve at counter)…"
              value={orderNote}
              onChange={(e) => setOrderNote(e.target.value)}
              style={{
                width: '100%',
                padding: '8px 12px',
                borderRadius: '8px',
                border: '1px solid var(--line)',
                fontSize: '0.82rem',
              }}
            />
          </div>

          <div className="ticket-lines">
            {cart.length === 0 ? (
              <div className="ticket-empty">
                <p>No items in Owner order yet.</p>
                <span>Tap any dish or drink on the left to add it to the Owner's meal.</span>
              </div>
            ) : (
              cart.map((line) => {
                const modUnit = line.modifierNames.reduce((s, m) => s + m.price, 0)
                const lineTotal = (line.unitPrice + modUnit) * line.quantity
                return (
                  <article key={line.key} className="ticket-line">
                    <div className="ticket-line-main">
                      <div>
                        <div className="ticket-line-title">
                          <strong>{line.menuItem.name}</strong>
                          {line.variantName && <span className="variant-pill">{line.variantName}</span>}
                          <span className={`station-badge station-${line.menuItem.station.toLowerCase()}`}>
                            {line.menuItem.station}
                          </span>
                        </div>
                        {line.modifierNames.length > 0 && (
                          <div className="ticket-line-mods">
                            {line.modifierNames.map((m, idx) => (
                              <span key={idx}>
                                ＋ {m.name} (৳{m.price.toFixed(0)})
                              </span>
                            ))}
                          </div>
                        )}
                        {line.note && <p className="ticket-line-note">“{line.note}”</p>}
                      </div>
                      <strong className="ticket-line-total">৳{lineTotal.toFixed(2)}</strong>
                    </div>

                    <div className="ticket-line-controls">
                      <span className="line-state-badge state-draft">BUDGET ITEM</span>
                      <div className="line-stepper">
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => updateCartQty(line.key, line.quantity - 1)}
                        >
                          {line.quantity <= 1 ? '×' : '−'}
                        </button>
                        <span>{line.quantity}</span>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => updateCartQty(line.key, line.quantity + 1)}
                        >
                          ＋
                        </button>
                      </div>
                    </div>
                  </article>
                )
              })
            )}
          </div>

          <div className="ticket-summary">
            <div className="summary-row">
              <span>Food Budget Value ({cart.reduce((s, i) => s + i.quantity, 0)} items)</span>
              <strong>৳{cartBudgetTotal.toFixed(2)}</strong>
            </div>
            <div className="summary-row">
              <span>Remaining Budget After Order</span>
              <strong>৳{Math.max(0, remaining - cartBudgetTotal).toFixed(2)}</strong>
            </div>
            <div className="summary-row total-row" style={{ color: '#21745d' }}>
              <span>Customer Payment / Sales Report</span>
              <strong>৳0.00 (Owner Free)</strong>
            </div>
          </div>

          <div className="ticket-actions">
            {cart.length > 0 && (
              <button
                type="button"
                className="secondary compact"
                disabled={busy}
                onClick={() => setCart([])}
              >
                Clear items
              </button>
            )}
            <button
              type="button"
              className="primary ticket-send-kot-cta"
              disabled={busy || cart.length === 0}
              onClick={() => void handlePlaceOwnerOrder()}
            >
              ♨ Send 80mm KOT & Complete Owner Order (৳0 Sale)
            </button>
          </div>
        </aside>
      </div>
      )}

      {customizeDraft && (
        <div className="modal-backdrop" onMouseDown={() => setCustomizeDraft(null)}>
          <form className="modal" onSubmit={submitCustomize} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">CUSTOMIZE OWNER ITEM</p>
                <h2>{customizeDraft.item.name}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setCustomizeDraft(null)}>
                ×
              </button>
            </div>

            {customizeDraft.item.variants.length > 0 && (
              <div className="customize-section">
                <span className="picker-label">Select Variant / Size</span>
                <div className="variant-pills">
                  {customizeDraft.item.variants
                    .filter((v) => v.available)
                    .map((v) => (
                      <button
                        key={v.id}
                        type="button"
                        className={customizeDraft.variantId === v.id ? 'active' : ''}
                        onClick={() => setCustomizeDraft({ ...customizeDraft, variantId: v.id })}
                      >
                        <span>{v.name}</span>
                        <strong>৳{Number(v.price).toFixed(0)}</strong>
                      </button>
                    ))}
                </div>
              </div>
            )}

            {customizeDraft.item.modifier_groups.map((group) => (
              <div key={group.id} className="customize-section">
                <span className="picker-label">{group.name}</span>
                <div className="modifier-grid">
                  {group.options
                    .filter((o) => o.available)
                    .map((opt) => {
                      const checked = customizeDraft.modifierOptionIds.includes(opt.id)
                      return (
                        <button
                          key={opt.id}
                          type="button"
                          className={`modifier-chip ${checked ? 'active' : ''}`}
                          onClick={() => {
                            const next = checked
                              ? customizeDraft.modifierOptionIds.filter((id) => id !== opt.id)
                              : [...customizeDraft.modifierOptionIds, opt.id]
                            setCustomizeDraft({ ...customizeDraft, modifierOptionIds: next })
                          }}
                        >
                          <span>{opt.name}</span>
                          <strong>+৳{Number(opt.price).toFixed(0)}</strong>
                        </button>
                      )
                    })}
                </div>
              </div>
            ))}

            <div className="field-row">
              <label>
                Quantity
                <input
                  type="number"
                  min={1}
                  max={50}
                  value={customizeDraft.quantity}
                  onChange={(e) =>
                    setCustomizeDraft({ ...customizeDraft, quantity: Math.max(1, Number(e.target.value)) })
                  }
                />
              </label>
              <label>
                Kitchen / Bar Note
                <input
                  placeholder="e.g. Extra hot, less ice"
                  value={customizeDraft.note}
                  onChange={(e) => setCustomizeDraft({ ...customizeDraft, note: e.target.value })}
                />
              </label>
            </div>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setCustomizeDraft(null)}>
                Cancel
              </button>
              <button type="submit" className="primary">
                Add to Owner Order
              </button>
            </div>
          </form>
        </div>
      )}

      {showBudgetModal && (
        <div className="modal-backdrop" onMouseDown={() => setShowBudgetModal(false)}>
          <form className="modal" onSubmit={handleSaveBudget} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">OWNER MONTHLY BUDGET</p>
                <h2>Set Owner food budget</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowBudgetModal(false)}>
                ×
              </button>
            </div>
            <label>
              Monthly Owner food budget limit (BDT ৳)
              <input
                type="number"
                step="0.01"
                min="0"
                required
                value={budgetInput}
                onChange={(e) => setBudgetInput(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setShowBudgetModal(false)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving…' : 'Save budget'}
              </button>
            </div>
          </form>
        </div>
      )}
    </>
  )
}
