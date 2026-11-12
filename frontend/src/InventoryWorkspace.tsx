import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import type { MenuCategoryRecord, MenuItemRecord } from './MenuWorkspace.tsx'

export type InventoryItemRecord = {
  id: number
  sku: string
  name: string
  category: string
  unit: 'pcs' | 'kg' | 'g' | 'l' | 'ml' | 'btl' | 'can' | 'pack'
  current_stock: string
  reorder_level: string
  unit_cost: string
  auto_eighty_six: boolean
  active: boolean
}

export type SupplierRecord = {
  id: number
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  address: string | null
  active: boolean
}

export type PurchaseOrderItemRecord = {
  id: number
  inventory_item_id: number
  quantity: string
  unit_cost: string
  line_total: string
  inventory_item?: {
    id: number
    sku: string
    name: string
    unit: string
  }
}

export type PurchaseOrderRecord = {
  id: number
  po_number: string
  reference_number: string | null
  status: string
  subtotal: string
  tax_amount: string
  total: string
  notes: string | null
  received_at: string
  supplier?: { id: number; name: string } | null
  receiver?: { id: number; name: string } | null
  items: PurchaseOrderItemRecord[]
}

export type RecipeIngredientRecord = {
  id: number
  menu_item_id: number
  inventory_item_id: number
  quantity: string
  menu_item?: {
    id: number
    sku: string
    name: string
    price: string
    available: boolean
  }
  inventory_item?: {
    id: number
    sku: string
    name: string
    unit: string
    unit_cost: string
    current_stock: string
  }
}

export type StockMovementRecord = {
  id: number
  inventory_item_id: number
  type: 'PURCHASE' | 'SALE_DEDUCTION' | 'WASTAGE' | 'ADJUSTMENT' | 'VOID_RETURN'
  quantity_delta: string
  stock_after: string
  unit_cost: string | null
  reason: string | null
  occurred_at: string
  inventory_item?: { id: number; sku: string; name: string; unit: string }
  actor?: { id: number; name: string }
}

export type InventoryData = {
  summary: {
    total_items: number
    low_stock_count: number
    out_of_stock_count: number
    inventory_valuation: string
  }
  items: InventoryItemRecord[]
  suppliers: SupplierRecord[]
  purchase_orders: PurchaseOrderRecord[]
  recipes: RecipeIngredientRecord[]
  movements: StockMovementRecord[]
}

type Props = {
  restaurantName: string
  inventory: InventoryData | null
  menuCategories: MenuCategoryRecord[]
  busy: boolean
  onRefresh: () => Promise<void>
  onSaveItem: (payload: Record<string, unknown>, id?: number) => Promise<boolean>
  onAdjustStock: (
    itemId: number,
    payload: { type: 'WASTAGE' | 'ADJUSTMENT'; quantity_delta: number; reason: string },
  ) => Promise<boolean>
  onSaveSupplier: (payload: Record<string, unknown>) => Promise<boolean>
  onRecordPurchase: (payload: {
    supplier_id?: number | null
    reference_number?: string
    tax_amount?: number
    notes?: string
    items: { inventory_item_id: number; quantity: number; unit_cost: number }[]
  }) => Promise<boolean>
  onSyncRecipe: (
    menuItemId: number,
    ingredients: { inventory_item_id: number; quantity: number }[],
  ) => Promise<boolean>
}

export default function InventoryWorkspace({
  restaurantName,
  inventory,
  menuCategories,
  busy,
  onRefresh,
  onSaveItem,
  onAdjustStock,
  onSaveSupplier,
  onRecordPurchase,
  onSyncRecipe,
}: Props) {
  const [subTab, setSubTab] = useState<'items' | 'purchasing' | 'recipes' | 'movements'>('items')
  const [itemSearch, setItemSearch] = useState('')
  const [stockFilter, setStockFilter] = useState<'all' | 'alerts'>('all')

  const [itemModal, setItemModal] = useState<{
    id?: number
    sku: string
    name: string
    category: string
    unit: InventoryItemRecord['unit']
    initial_stock: string
    reorder_level: string
    unit_cost: string
    auto_eighty_six: boolean
  } | null>(null)

  const [adjustModal, setAdjustModal] = useState<{
    item: InventoryItemRecord
    type: 'WASTAGE' | 'ADJUSTMENT'
    quantityDelta: string
    reason: string
  } | null>(null)

  const [supplierModal, setSupplierModal] = useState<{
    name: string
    contact_person: string
    phone: string
    email: string
    address: string
  } | null>(null)

  const [purchaseModal, setPurchaseModal] = useState<{
    supplierId: string
    referenceNumber: string
    taxAmount: string
    notes: string
    lines: { inventory_item_id: string; quantity: string; unit_cost: string }[]
  } | null>(null)

  const [recipeModal, setRecipeModal] = useState<{
    menuItem: MenuItemRecord
    lines: { inventory_item_id: string; quantity: string }[]
  } | null>(null)

  const items = inventory?.items ?? []
  const suppliers = inventory?.suppliers ?? []
  const purchaseOrders = inventory?.purchase_orders ?? []
  const recipes = inventory?.recipes ?? []
  const movements = inventory?.movements ?? []
  const summary = inventory?.summary

  const allMenuItems = useMemo(
    () => menuCategories.flatMap((c) => c.items),
    [menuCategories],
  )

  const filteredItems = useMemo(() => {
    const q = itemSearch.trim().toLowerCase()
    return items.filter((item) => {
      if (stockFilter === 'alerts' && Number(item.current_stock) > Number(item.reorder_level)) {
        return false
      }
      if (!q) return true
      return (
        item.name.toLowerCase().includes(q) ||
        item.sku.toLowerCase().includes(q) ||
        item.category.toLowerCase().includes(q)
      )
    })
  }, [items, itemSearch, stockFilter])

  const submitItem = async (event: FormEvent) => {
    event.preventDefault()
    if (!itemModal) return
    const ok = await onSaveItem(
      {
        sku: itemModal.sku,
        name: itemModal.name,
        category: itemModal.category,
        unit: itemModal.unit,
        initial_stock: Number(itemModal.initial_stock || 0),
        reorder_level: Number(itemModal.reorder_level || 0),
        unit_cost: Number(itemModal.unit_cost || 0),
        auto_eighty_six: itemModal.auto_eighty_six,
      },
      itemModal.id,
    )
    if (ok) setItemModal(null)
  }

  const submitAdjust = async (event: FormEvent) => {
    event.preventDefault()
    if (!adjustModal) return
    const ok = await onAdjustStock(adjustModal.item.id, {
      type: adjustModal.type,
      quantity_delta: Number(adjustModal.quantityDelta),
      reason: adjustModal.reason,
    })
    if (ok) setAdjustModal(null)
  }

  const submitSupplier = async (event: FormEvent) => {
    event.preventDefault()
    if (!supplierModal) return
    const ok = await onSaveSupplier(supplierModal)
    if (ok) setSupplierModal(null)
  }

  const submitPurchase = async (event: FormEvent) => {
    event.preventDefault()
    if (!purchaseModal) return
    const validLines = purchaseModal.lines
      .filter((l) => l.inventory_item_id && Number(l.quantity) > 0)
      .map((l) => ({
        inventory_item_id: Number(l.inventory_item_id),
        quantity: Number(l.quantity),
        unit_cost: Number(l.unit_cost || 0),
      }))
    if (validLines.length === 0) return

    const ok = await onRecordPurchase({
      supplier_id: purchaseModal.supplierId ? Number(purchaseModal.supplierId) : null,
      reference_number: purchaseModal.referenceNumber.trim() || undefined,
      tax_amount: Number(purchaseModal.taxAmount || 0),
      notes: purchaseModal.notes.trim() || undefined,
      items: validLines,
    })
    if (ok) setPurchaseModal(null)
  }

  const submitRecipe = async (event: FormEvent) => {
    event.preventDefault()
    if (!recipeModal) return
    const validLines = recipeModal.lines
      .filter((l) => l.inventory_item_id && Number(l.quantity) > 0)
      .map((l) => ({
        inventory_item_id: Number(l.inventory_item_id),
        quantity: Number(l.quantity),
      }))
    const ok = await onSyncRecipe(recipeModal.menuItem.id, validLines)
    if (ok) setRecipeModal(null)
  }

  return (
    <div className="reports-workspace">
      <header>
        <div>
          <p className="eyebrow coral">INVENTORY, PURCHASING & RECIPE BOM</p>
          <h1>Track every ingredient from purchase to plate.</h1>
          <p className="muted">
            {restaurantName} · Live stock ledger, supplier GRN purchases & automatic KOT recipe deduction
          </p>
        </div>
        <div className="header-actions">
          <button
            type="button"
            className="secondary compact"
            disabled={busy || items.length === 0}
            onClick={() =>
              setPurchaseModal({
                supplierId: String(suppliers[0]?.id ?? ''),
                referenceNumber: '',
                taxAmount: '0',
                notes: '',
                lines: [
                  {
                    inventory_item_id: String(items[0]?.id ?? ''),
                    quantity: '10',
                    unit_cost: String(items[0]?.unit_cost ?? '100'),
                  },
                ],
              })
            }
          >
            🛒 ＋ Record Purchase (Stock-In)
          </button>
          <button
            type="button"
            className="primary compact"
            disabled={busy}
            onClick={() =>
              setItemModal({
                sku: '',
                name: '',
                category: 'Meat & Poultry',
                unit: 'pcs',
                initial_stock: '0',
                reorder_level: '5',
                unit_cost: '0',
                auto_eighty_six: true,
              })
            }
          >
            ＋ Add Stock Item
          </button>
          <button type="button" className="secondary compact" disabled={busy} onClick={() => void onRefresh()}>
            ↻
          </button>
        </div>
      </header>

      {summary && (
        <section className="summary-grid">
          <article>
            <span className="summary-icon green">📦</span>
            <div>
              <strong>{summary.total_items}</strong>
              <p>Active Stock Items</p>
            </div>
          </article>
          <article>
            <span className="summary-icon amber">!</span>
            <div>
              <strong>{summary.low_stock_count}</strong>
              <p>Low Stock Alerts</p>
            </div>
          </article>
          <article>
            <span className="summary-icon ink">0</span>
            <div>
              <strong>{summary.out_of_stock_count}</strong>
              <p>Out of Stock (Auto 86)</p>
            </div>
          </article>
          <article>
            <span className="summary-icon blue">৳</span>
            <div>
              <strong>৳{Number(summary.inventory_valuation).toFixed(0)}</strong>
              <p>On-Hand Stock Valuation</p>
            </div>
          </article>
        </section>
      )}

      <div className="reports-toolbar">
        <div className="zone-tabs" style={{ marginBottom: 0 }}>
          <button
            type="button"
            className={subTab === 'items' ? 'active' : ''}
            onClick={() => setSubTab('items')}
          >
            📦 Stock Items & Wastage
            <span>{items.length}</span>
          </button>
          <button
            type="button"
            className={subTab === 'purchasing' ? 'active' : ''}
            onClick={() => setSubTab('purchasing')}
          >
            🛒 Purchases & Suppliers
            <span>{purchaseOrders.length}</span>
          </button>
          <button
            type="button"
            className={subTab === 'recipes' ? 'active' : ''}
            onClick={() => setSubTab('recipes')}
          >
            🍲 Menu Recipe Mapping (BOM)
            <span>{allMenuItems.length}</span>
          </button>
          <button
            type="button"
            className={subTab === 'movements' ? 'active' : ''}
            onClick={() => setSubTab('movements')}
          >
            📜 Stock Movement Ledger
            <span>{movements.length}</span>
          </button>
        </div>
      </div>

      {subTab === 'items' && (
        <section className="zone-section">
          <div className="zone-title">
            <h2>Stock Items & Live Quantities</h2>
            <div className="audit-filter-bar">
              <div className="quick-cash-pills">
                <button
                  type="button"
                  className={stockFilter === 'all' ? 'active-pill' : ''}
                  onClick={() => setStockFilter('all')}
                >
                  All Items ({items.length})
                </button>
                <button
                  type="button"
                  className={stockFilter === 'alerts' ? 'active-pill' : ''}
                  onClick={() => setStockFilter('alerts')}
                >
                  Low / Out of Stock (
                  {items.filter((i) => Number(i.current_stock) <= Number(i.reorder_level)).length})
                </button>
              </div>
              <input
                className="reports-search-input"
                placeholder="Search SKU, ingredient or category…"
                value={itemSearch}
                onChange={(e) => setItemSearch(e.target.value)}
              />
            </div>
          </div>

          {filteredItems.length === 0 ? (
            <div className="inline-empty">
              No stock items yet. Click “＋ Add Stock Item” to add raw ingredients or drinks.
            </div>
          ) : (
            <div className="reports-table-wrap">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Ingredient / Item</th>
                    <th>Category</th>
                    <th>On-Hand Stock</th>
                    <th>Reorder Level</th>
                    <th>Unit Cost</th>
                    <th>Auto 86</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredItems.map((item) => {
                    const stock = Number(item.current_stock)
                    const reorder = Number(item.reorder_level)
                    const statusClass =
                      stock <= 0 ? 'status-voided' : stock <= reorder ? 'status-partially_refunded' : 'status-issued'
                    const statusLabel =
                      stock <= 0 ? 'OUT OF STOCK' : stock <= reorder ? 'LOW STOCK' : 'IN STOCK'

                    return (
                      <tr key={item.id}>
                        <td>
                          <span className="sku-tag">{item.sku}</span>
                        </td>
                        <td>
                          <strong>{item.name}</strong>
                        </td>
                        <td>{item.category}</td>
                        <td>
                          <strong>
                            {stock.toFixed(2)} {item.unit}
                          </strong>{' '}
                          <span className={`invoice-status-pill ${statusClass}`}>{statusLabel}</span>
                        </td>
                        <td>
                          {reorder.toFixed(2)} {item.unit}
                        </td>
                        <td>৳{Number(item.unit_cost).toFixed(2)} / {item.unit}</td>
                        <td>{item.auto_eighty_six ? 'Yes (Auto Sold-Out)' : 'Manual'}</td>
                        <td>
                          <div className="invoice-row-actions">
                            <button
                              type="button"
                              className="secondary compact"
                              onClick={() =>
                                setAdjustModal({
                                  item,
                                  type: 'WASTAGE',
                                  quantityDelta: '1',
                                  reason: '',
                                })
                              }
                            >
                              ± Wastage / Adjust
                            </button>
                            <button
                              type="button"
                              className="secondary compact"
                              onClick={() =>
                                setItemModal({
                                  id: item.id,
                                  sku: item.sku,
                                  name: item.name,
                                  category: item.category,
                                  unit: item.unit,
                                  initial_stock: item.current_stock,
                                  reorder_level: item.reorder_level,
                                  unit_cost: item.unit_cost,
                                  auto_eighty_six: item.auto_eighty_six,
                                })
                              }
                            >
                              Edit
                            </button>
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
      )}

      {subTab === 'purchasing' && (
        <div className="reports-two-col">
          <section className="zone-section">
            <div className="zone-title">
              <h2>Purchase Orders / Goods Received (GRN)</h2>
              <button
                type="button"
                className="primary compact"
                disabled={busy || items.length === 0}
                onClick={() =>
                  setPurchaseModal({
                    supplierId: String(suppliers[0]?.id ?? ''),
                    referenceNumber: '',
                    taxAmount: '0',
                    notes: '',
                    lines: [
                      {
                        inventory_item_id: String(items[0]?.id ?? ''),
                        quantity: '10',
                        unit_cost: String(items[0]?.unit_cost ?? '100'),
                      },
                    ],
                  })
                }
              >
                ＋ New Purchase Bill
              </button>
            </div>
            {purchaseOrders.length === 0 ? (
              <div className="inline-empty">No purchase bills recorded yet.</div>
            ) : (
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>PO #</th>
                      <th>Supplier / Ref</th>
                      <th>Items Received</th>
                      <th>Total Cost</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {purchaseOrders.map((po) => (
                      <tr key={po.id}>
                        <td>
                          <strong>{po.po_number}</strong>
                        </td>
                        <td>
                          <strong>{po.supplier?.name ?? 'Local Market Cash'}</strong>
                          {po.reference_number && <div className="muted">Ref: {po.reference_number}</div>}
                        </td>
                        <td>
                          {po.items.map((l) => (
                            <div key={l.id}>
                              +{Number(l.quantity)} {l.inventory_item?.unit} {l.inventory_item?.name} @ ৳
                              {Number(l.unit_cost).toFixed(0)}
                            </div>
                          ))}
                        </td>
                        <td>
                          <strong>৳{Number(po.total).toFixed(2)}</strong>
                        </td>
                        <td>
                          {new Date(po.received_at).toLocaleString([], {
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
            )}
          </section>

          <section className="zone-section">
            <div className="zone-title">
              <h2>Approved Suppliers & Vendors</h2>
              <button
                type="button"
                className="secondary compact"
                disabled={busy}
                onClick={() =>
                  setSupplierModal({
                    name: '',
                    contact_person: '',
                    phone: '',
                    email: '',
                    address: '',
                  })
                }
              >
                ＋ Add Supplier
              </button>
            </div>
            {suppliers.length === 0 ? (
              <div className="inline-empty">
                No suppliers added yet. Add vendors or record direct Local Market purchases.
              </div>
            ) : (
              <div className="reports-table-wrap">
                <table className="reports-table">
                  <thead>
                    <tr>
                      <th>Supplier Name</th>
                      <th>Contact Person</th>
                      <th>Phone</th>
                    </tr>
                  </thead>
                  <tbody>
                    {suppliers.map((sup) => (
                      <tr key={sup.id}>
                        <td>
                          <strong>{sup.name}</strong>
                        </td>
                        <td>{sup.contact_person ?? '—'}</td>
                        <td>{sup.phone ?? '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      )}

      {subTab === 'recipes' && (
        <section className="zone-section">
          <div className="zone-title">
            <h2>Menu Item Recipe Mapping & Food Cost %</h2>
            <span>Automatically deducted whenever a KOT round is sent</span>
          </div>
          <div className="reports-table-wrap">
            <table className="reports-table">
              <thead>
                <tr>
                  <th>Menu Item</th>
                  <th>Selling Price</th>
                  <th>Recipe Ingredients (Per Serving)</th>
                  <th>Est. Recipe Cost</th>
                  <th>Food Cost %</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {allMenuItems.map((menuItem) => {
                  const itemRecipes = recipes.filter((r) => r.menu_item_id === menuItem.id)
                  const recipeCost = itemRecipes.reduce(
                    (sum, r) => sum + Number(r.quantity) * Number(r.inventory_item?.unit_cost ?? 0),
                    0,
                  )
                  const sellPrice = Number(menuItem.price)
                  const foodCostPct = sellPrice > 0 ? (recipeCost / sellPrice) * 100 : 0

                  return (
                    <tr key={menuItem.id}>
                      <td>
                        <strong>{menuItem.name}</strong>
                        <div className="muted">
                          {menuItem.sku} · {menuItem.available ? 'Available' : 'Sold Out (86)'}
                        </div>
                      </td>
                      <td>৳{sellPrice.toFixed(2)}</td>
                      <td>
                        {itemRecipes.length === 0 ? (
                          <span className="muted">No ingredients linked yet</span>
                        ) : (
                          itemRecipes.map((r) => (
                            <span key={r.id} className="sku-tag" style={{ marginRight: '6px' }}>
                              {Number(r.quantity)} {r.inventory_item?.unit} {r.inventory_item?.name}
                            </span>
                          ))
                        )}
                      </td>
                      <td>
                        <strong>৳{recipeCost.toFixed(2)}</strong>
                      </td>
                      <td>
                        {itemRecipes.length > 0 ? (
                          <span
                            className={`invoice-status-pill ${
                              foodCostPct > 40 ? 'status-partially_refunded' : 'status-issued'
                            }`}
                          >
                            {foodCostPct.toFixed(1)}%
                          </span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td>
                        <button
                          type="button"
                          className="secondary compact"
                          disabled={items.length === 0}
                          onClick={() =>
                            setRecipeModal({
                              menuItem,
                              lines:
                                itemRecipes.length > 0
                                  ? itemRecipes.map((r) => ({
                                      inventory_item_id: String(r.inventory_item_id),
                                      quantity: String(Number(r.quantity)),
                                    }))
                                  : [
                                      {
                                        inventory_item_id: String(items[0]?.id ?? ''),
                                        quantity: '1',
                                      },
                                    ],
                            })
                          }
                        >
                          ⚙ Configure Recipe
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {subTab === 'movements' && (
        <section className="zone-section">
          <div className="zone-title">
            <h2>Immutable Stock Movement Ledger</h2>
            <span>Purchases, KOT sale deductions, wastage & physical count adjustments</span>
          </div>
          {movements.length === 0 ? (
            <div className="inline-empty">No stock movements recorded yet.</div>
          ) : (
            <div className="reports-table-wrap">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Qty Change</th>
                    <th>Stock After</th>
                    <th>Reason / Reference</th>
                    <th>Actor</th>
                  </tr>
                </thead>
                <tbody>
                  {movements.map((mov) => {
                    const delta = Number(mov.quantity_delta)
                    return (
                      <tr key={mov.id}>
                        <td>
                          {new Date(mov.occurred_at).toLocaleString([], {
                            month: 'short',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </td>
                        <td>
                          <strong>{mov.inventory_item?.name ?? `#${mov.inventory_item_id}`}</strong>
                        </td>
                        <td>
                          <span className="sku-tag">{mov.type}</span>
                        </td>
                        <td className={delta < 0 ? 'danger-link' : 'activate-link'}>
                          <strong>
                            {delta > 0 ? `+${delta}` : delta} {mov.inventory_item?.unit}
                          </strong>
                        </td>
                        <td>
                          {Number(mov.stock_after).toFixed(2)} {mov.inventory_item?.unit}
                        </td>
                        <td>{mov.reason ?? '—'}</td>
                        <td>{mov.actor?.name ?? 'Staff'}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {itemModal && (
        <div className="modal-backdrop" onMouseDown={() => setItemModal(null)}>
          <form className="modal" onSubmit={submitItem} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">INVENTORY STOCK ITEM</p>
                <h2>{itemModal.id ? 'Edit stock item' : 'Add stock item'}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setItemModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                SKU Code
                <input
                  required
                  placeholder="ING-BEEF-01"
                  value={itemModal.sku}
                  onChange={(e) => setItemModal({ ...itemModal, sku: e.target.value.toUpperCase() })}
                />
              </label>
              <label>
                Storage Unit
                <select
                  value={itemModal.unit}
                  onChange={(e) =>
                    setItemModal({
                      ...itemModal,
                      unit: e.target.value as InventoryItemRecord['unit'],
                    })
                  }
                >
                  <option value="pcs">pcs (Pieces / Buns / Patties)</option>
                  <option value="kg">kg (Kilograms)</option>
                  <option value="g">g (Grams)</option>
                  <option value="l">l (Liters)</option>
                  <option value="ml">ml (Milliliters)</option>
                  <option value="can">can (Cans)</option>
                  <option value="btl">btl (Bottles)</option>
                  <option value="pack">pack (Packs)</option>
                </select>
              </label>
            </div>

            <div className="field-row">
              <label>
                Item Name
                <input
                  required
                  placeholder="Beef Burger Patty 150g"
                  value={itemModal.name}
                  onChange={(e) => setItemModal({ ...itemModal, name: e.target.value })}
                />
              </label>
              <label>
                Category
                <input
                  required
                  placeholder="Meat & Poultry"
                  value={itemModal.category}
                  onChange={(e) => setItemModal({ ...itemModal, category: e.target.value })}
                />
              </label>
            </div>

            <div className="field-row">
              {!itemModal.id && (
                <label>
                  Opening Stock
                  <input
                    required
                    type="number"
                    min="0"
                    step="0.001"
                    value={itemModal.initial_stock}
                    onChange={(e) => setItemModal({ ...itemModal, initial_stock: e.target.value })}
                  />
                </label>
              )}
              <label>
                Low-Stock Alert Threshold
                <input
                  required
                  type="number"
                  min="0"
                  step="0.001"
                  value={itemModal.reorder_level}
                  onChange={(e) => setItemModal({ ...itemModal, reorder_level: e.target.value })}
                />
              </label>
              <label>
                Unit Cost (BDT ৳)
                <input
                  required
                  type="number"
                  min="0"
                  step="0.01"
                  value={itemModal.unit_cost}
                  onChange={(e) => setItemModal({ ...itemModal, unit_cost: e.target.value })}
                />
              </label>
            </div>

            <label className="checkbox-pill">
              <input
                type="checkbox"
                checked={itemModal.auto_eighty_six}
                onChange={(e) => setItemModal({ ...itemModal, auto_eighty_six: e.target.checked })}
              />
              <span>
                Automatically mark linked Menu Items as Sold Out (86) when this ingredient runs out
              </span>
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setItemModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving…' : itemModal.id ? 'Save changes' : 'Create stock item'}
              </button>
            </div>
          </form>
        </div>
      )}

      {adjustModal && (
        <div className="modal-backdrop" onMouseDown={() => setAdjustModal(null)}>
          <form className="modal" onSubmit={submitAdjust} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">WASTAGE & PHYSICAL COUNT ADJUSTMENT</p>
                <h2>
                  {adjustModal.item.name} ({Number(adjustModal.item.current_stock).toFixed(2)}{' '}
                  {adjustModal.item.unit} on hand)
                </h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setAdjustModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Adjustment Type
                <select
                  value={adjustModal.type}
                  onChange={(e) =>
                    setAdjustModal({
                      ...adjustModal,
                      type: e.target.value as 'WASTAGE' | 'ADJUSTMENT',
                    })
                  }
                >
                  <option value="WASTAGE">Wastage / Spoilage (Deducts from stock)</option>
                  <option value="ADJUSTMENT">Physical Count Adjustment (+ or −)</option>
                </select>
              </label>
              <label>
                Quantity ({adjustModal.item.unit})
                <input
                  required
                  type="number"
                  step="0.001"
                  value={adjustModal.quantityDelta}
                  onChange={(e) =>
                    setAdjustModal({ ...adjustModal, quantityDelta: e.target.value })
                  }
                />
              </label>
            </div>

            <label>
              Mandatory reason (logged in stock ledger & audit trail)
              <input
                required
                minLength={3}
                placeholder="e.g. Spoiled in chiller / end-of-night physical stock count"
                value={adjustModal.reason}
                onChange={(e) => setAdjustModal({ ...adjustModal, reason: e.target.value })}
              />
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setAdjustModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Recording…' : 'Record Stock Adjustment'}
              </button>
            </div>
          </form>
        </div>
      )}

      {supplierModal && (
        <div className="modal-backdrop" onMouseDown={() => setSupplierModal(null)}>
          <form className="modal" onSubmit={submitSupplier} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">SUPPLIER DIRECTORY</p>
                <h2>Add supplier / vendor</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setSupplierModal(null)}>
                ×
              </button>
            </div>

            <label>
              Supplier / Company Name
              <input
                required
                placeholder="Dhaka Fresh Meats & Poultry"
                value={supplierModal.name}
                onChange={(e) => setSupplierModal({ ...supplierModal, name: e.target.value })}
              />
            </label>

            <div className="field-row">
              <label>
                Contact Person
                <input
                  placeholder="Karim Uddin"
                  value={supplierModal.contact_person}
                  onChange={(e) =>
                    setSupplierModal({ ...supplierModal, contact_person: e.target.value })
                  }
                />
              </label>
              <label>
                Phone
                <input
                  placeholder="01700-000000"
                  value={supplierModal.phone}
                  onChange={(e) => setSupplierModal({ ...supplierModal, phone: e.target.value })}
                />
              </label>
            </div>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setSupplierModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving…' : 'Save Supplier'}
              </button>
            </div>
          </form>
        </div>
      )}

      {purchaseModal && (
        <div className="modal-backdrop" onMouseDown={() => setPurchaseModal(null)}>
          <form
            className="modal zone-modal"
            onSubmit={submitPurchase}
            onMouseDown={(e) => e.stopPropagation()}
          >
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">PURCHASING / GOODS RECEIVED (GRN)</p>
                <h2>Record supplier / market purchase</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setPurchaseModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Supplier (or Local Cash Bazaar)
                <select
                  value={purchaseModal.supplierId}
                  onChange={(e) =>
                    setPurchaseModal({ ...purchaseModal, supplierId: e.target.value })
                  }
                >
                  <option value="">Local Market Cash Purchase</option>
                  {suppliers.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                Vendor Invoice / Bill Ref #
                <input
                  placeholder="e.g. BILL-2026-88"
                  value={purchaseModal.referenceNumber}
                  onChange={(e) =>
                    setPurchaseModal({ ...purchaseModal, referenceNumber: e.target.value })
                  }
                />
              </label>
            </div>

            <div className="modifier-picker">
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span className="picker-label">Purchased Items (Stock-In)</span>
                <button
                  type="button"
                  className="inline-link-btn"
                  onClick={() =>
                    setPurchaseModal({
                      ...purchaseModal,
                      lines: [
                        ...purchaseModal.lines,
                        {
                          inventory_item_id: String(items[0]?.id ?? ''),
                          quantity: '1',
                          unit_cost: String(items[0]?.unit_cost ?? '0'),
                        },
                      ],
                    })
                  }
                >
                  ＋ Add another line
                </button>
              </div>

              {purchaseModal.lines.map((line, idx) => (
                <div className="field-row" key={idx} style={{ alignItems: 'end' }}>
                  <label>
                    Stock Item
                    <select
                      value={line.inventory_item_id}
                      onChange={(e) => {
                        const matched = items.find((i) => String(i.id) === e.target.value)
                        const next = [...purchaseModal.lines]
                        next[idx] = {
                          ...line,
                          inventory_item_id: e.target.value,
                          unit_cost: matched ? String(matched.unit_cost) : line.unit_cost,
                        }
                        setPurchaseModal({ ...purchaseModal, lines: next })
                      }}
                    >
                      {items.map((i) => (
                        <option key={i.id} value={i.id}>
                          {i.name} ({i.unit})
                        </option>
                      ))}
                    </select>
                  </label>
                  <label>
                    Qty Received
                    <input
                      required
                      type="number"
                      min="0.001"
                      step="0.001"
                      value={line.quantity}
                      onChange={(e) => {
                        const next = [...purchaseModal.lines]
                        next[idx] = { ...line, quantity: e.target.value }
                        setPurchaseModal({ ...purchaseModal, lines: next })
                      }}
                    />
                  </label>
                  <label>
                    Purchase Unit Cost (৳)
                    <input
                      required
                      type="number"
                      min="0"
                      step="0.01"
                      value={line.unit_cost}
                      onChange={(e) => {
                        const next = [...purchaseModal.lines]
                        next[idx] = { ...line, unit_cost: e.target.value }
                        setPurchaseModal({ ...purchaseModal, lines: next })
                      }}
                    />
                  </label>
                </div>
              ))}
            </div>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setPurchaseModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy
                  ? 'Receiving stock…'
                  : `Receive Stock · ৳${purchaseModal.lines
                      .reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_cost || 0), 0)
                      .toFixed(2)}`}
              </button>
            </div>
          </form>
        </div>
      )}

      {recipeModal && (
        <div className="modal-backdrop" onMouseDown={() => setRecipeModal(null)}>
          <form
            className="modal zone-modal"
            onSubmit={submitRecipe}
            onMouseDown={(e) => e.stopPropagation()}
          >
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">RECIPE BILL OF MATERIALS (BOM)</p>
                <h2>{recipeModal.menuItem.name}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setRecipeModal(null)}>
                ×
              </button>
            </div>

            <div className="modifier-picker">
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span className="picker-label">Ingredients deducted per 1 {recipeModal.menuItem.name}</span>
                <button
                  type="button"
                  className="inline-link-btn"
                  onClick={() =>
                    setRecipeModal({
                      ...recipeModal,
                      lines: [
                        ...recipeModal.lines,
                        { inventory_item_id: String(items[0]?.id ?? ''), quantity: '1' },
                      ],
                    })
                  }
                >
                  ＋ Add ingredient
                </button>
              </div>

              {recipeModal.lines.map((line, idx) => (
                <div className="field-row" key={idx} style={{ alignItems: 'end' }}>
                  <label>
                    Stock Ingredient
                    <select
                      value={line.inventory_item_id}
                      onChange={(e) => {
                        const next = [...recipeModal.lines]
                        next[idx] = { ...line, inventory_item_id: e.target.value }
                        setRecipeModal({ ...recipeModal, lines: next })
                      }}
                    >
                      {items.map((i) => (
                        <option key={i.id} value={i.id}>
                          {i.name} ({i.unit} · ৳{Number(i.unit_cost).toFixed(2)}/{i.unit})
                        </option>
                      ))}
                    </select>
                  </label>
                  <label>
                    Quantity Used per Serving
                    <div style={{ display: 'flex', gap: '6px' }}>
                      <input
                        required
                        type="number"
                        min="0.001"
                        step="0.001"
                        value={line.quantity}
                        onChange={(e) => {
                          const next = [...recipeModal.lines]
                          next[idx] = { ...line, quantity: e.target.value }
                          setRecipeModal({ ...recipeModal, lines: next })
                        }}
                      />
                      <button
                        type="button"
                        className="secondary compact danger-link"
                        onClick={() =>
                          setRecipeModal({
                            ...recipeModal,
                            lines: recipeModal.lines.filter((_, i) => i !== idx),
                          })
                        }
                      >
                        ×
                      </button>
                    </div>
                  </label>
                </div>
              ))}
            </div>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setRecipeModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving recipe…' : 'Save Recipe BOM'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
