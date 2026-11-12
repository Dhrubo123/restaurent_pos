import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'

export type TaxCategory = {
  id: number
  name: string
  rate: string
  inclusive: boolean
}

export type ModifierOption = {
  id: number
  name: string
  price: string
  active: boolean
  available: boolean
  sort_order: number
}

export type ModifierGroup = {
  id: number
  name: string
  min_selections: number
  max_selections: number
  required: boolean
  active: boolean
  sort_order: number
  options: ModifierOption[]
}

export type ItemVariant = {
  id: number
  sku: string
  name: string
  price: string
  active: boolean
  available: boolean
  sort_order: number
}

export type PriceHistoryEntry = {
  id: number
  amount: string
  effective_from: string
  effective_to: string | null
  reason: string | null
  actor?: { id: number; name: string } | null
}

export type MenuItemRecord = {
  id: number
  menu_category_id: number
  menu_subcategory_id: number | null
  tax_category_id: number | null
  sku: string
  name: string
  description: string | null
  image_path?: string | null
  station: 'KITCHEN' | 'BAR'
  price: string
  sort_order: number
  active: boolean
  available: boolean
  variants: ItemVariant[]
  modifier_groups: ModifierGroup[]
  price_histories?: PriceHistoryEntry[]
}

export function buildFallbackDishSvg(item: { sku?: string; name?: string; station?: string }): string {
  const title = (item.name || 'Clique Café').replace(/[<>&"']/g, '')
  const sku = (item.sku || 'ITEM').replace(/[<>&"']/g, '')
  const isBar = item.station === 'BAR'
  const bgStart = isBar ? '#231c18' : '#1f1b18'
  const bgEnd = isBar ? '#3b281c' : '#332419'
  const icon = isBar ? '☕' : '🍔'
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="480" height="300" viewBox="0 0 480 300">
    <defs>
      <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="${bgStart}"/>
        <stop offset="100%" stop-color="${bgEnd}"/>
      </linearGradient>
    </defs>
    <rect width="480" height="300" fill="url(#g)"/>
    <circle cx="390" cy="60" r="110" fill="#ec6b1e" fill-opacity="0.14"/>
    <circle cx="90" cy="250" r="95" fill="#ec6b1e" fill-opacity="0.1"/>
    <circle cx="240" cy="128" r="46" fill="#ec6b1e" fill-opacity="0.2" stroke="#ec6b1e" stroke-opacity="0.5" stroke-width="2"/>
    <text x="240" y="143" text-anchor="middle" font-size="40" font-family="sans-serif">${icon}</text>
    <text x="240" y="212" text-anchor="middle" fill="#ffffff" font-size="20" font-weight="700" font-family="Manrope, sans-serif">${title.slice(0, 28)}</text>
    <text x="240" y="238" text-anchor="middle" fill="#f38b4a" font-size="12" font-weight="700" letter-spacing="2" font-family="DM Sans, sans-serif">CLIQUE CAFÉ · ${sku}</text>
  </svg>`
  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
}

export function resolveMenuItemImage(item: { sku?: string; name?: string; station?: string; image_path?: string | null }): string {
  const trimmed = item.image_path?.trim()
  if (trimmed) return trimmed
  return buildFallbackDishSvg(item)
}

function compressImageFileToDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onerror = () => reject(new Error('Could not read image file.'))
    reader.onload = () => {
      const img = new Image()
      img.onerror = () => reject(new Error('Invalid image file.'))
      img.onload = () => {
        const maxW = 560
        const maxH = 380
        const ratio = Math.min(maxW / img.width, maxH / img.height, 1)
        const width = Math.max(1, Math.round(img.width * ratio))
        const height = Math.max(1, Math.round(img.height * ratio))
        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d')
        if (!ctx) {
          resolve(String(reader.result))
          return
        }
        ctx.drawImage(img, 0, 0, width, height)
        resolve(canvas.toDataURL('image/jpeg', 0.84))
      }
      img.src = String(reader.result)
    }
    reader.readAsDataURL(file)
  })
}

export type MenuSubcategoryRecord = {
  id: number
  menu_category_id: number
  name: string
  sort_order: number
  active: boolean
  items: MenuItemRecord[]
}

export type MenuCategoryRecord = {
  id: number
  name: string
  default_station: 'KITCHEN' | 'BAR'
  active: boolean
  sort_order: number
  items: MenuItemRecord[]
  subcategories: MenuSubcategoryRecord[]
}

type ItemDraft = {
  id?: number
  menu_category_id: string
  menu_subcategory_id: string
  tax_category_id: string
  sku: string
  name: string
  description: string
  image_path: string
  station: 'KITCHEN' | 'BAR'
  price: string
  originalPrice?: string
  reason: string
  modifier_group_ids: number[]
  available: boolean
  sort_order: number
  variants: VariantDraft[]
  priceHistories?: PriceHistoryEntry[]
}

type CategoryDraft = {
  id?: number
  name: string
  default_station: 'KITCHEN' | 'BAR'
  sort_order: number
}

type VariantDraft = {
  id?: number
  sku: string
  name: string
  price: string
  active: boolean
  available: boolean
  sort_order: number
}

type SubcategoryDraft = {
  id?: number
  menu_category_id: string
  name: string
  sort_order: number
}

type ModifierOptionDraft = {
  id?: number
  name: string
  price: string
  active: boolean
  available: boolean
  sort_order: number
}

type ModifierGroupDraft = {
  id?: number
  name: string
  min_selections: number
  max_selections: number
  required: boolean
  sort_order: number
  options: ModifierOptionDraft[]
}

type Props = {
  restaurantName: string
  categories: MenuCategoryRecord[]
  taxCategories: TaxCategory[]
  modifierGroups: ModifierGroup[]
  permissions: string[]
  busy: boolean
  fieldErrors: Record<string, string[]>
  onClearErrors: () => void
  onSaveItem: (payload: Record<string, unknown>, id?: number) => Promise<boolean>
  onToggleAvailability: (item: MenuItemRecord, available: boolean) => Promise<void>
  onToggleItemActive: (item: MenuItemRecord, active: boolean) => Promise<void>
  onSaveCategory: (payload: CategoryDraft) => Promise<boolean>
  onToggleCategoryActive: (category: MenuCategoryRecord, active: boolean) => Promise<void>
  onSaveSubcategory: (payload: Record<string, unknown>, id?: number) => Promise<boolean>
  onToggleSubcategoryActive: (id: number, name: string, active: boolean) => Promise<void>
  onSaveModifierGroup: (payload: Record<string, unknown>, id?: number) => Promise<boolean>
  onToggleModifierGroupActive: (id: number, name: string, active: boolean) => Promise<void>
  onSaveTaxCategory?: (payload: { name: string; rate: number; inclusive: boolean }) => Promise<boolean>
  onApplyBulkTaxMode?: (taxCategoryId: number | null, label: string) => Promise<boolean>
}

export default function MenuWorkspace({
  restaurantName,
  categories,
  taxCategories,
  modifierGroups,
  permissions,
  busy,
  fieldErrors,
  onClearErrors,
  onSaveItem,
  onToggleAvailability,
  onToggleItemActive,
  onSaveCategory,
  onToggleCategoryActive,
  onSaveSubcategory,
  onToggleSubcategoryActive,
  onSaveModifierGroup,
  onToggleModifierGroupActive,
  onSaveTaxCategory,
  onApplyBulkTaxMode,
}: Props) {
  const [selectedCategory, setSelectedCategory] = useState<number | 'all'>('all')
  const [stationFilter, setStationFilter] = useState<'ALL' | 'KITCHEN' | 'BAR'>('ALL')
  const [search, setSearch] = useState('')
  const [itemDraft, setItemDraft] = useState<ItemDraft | null>(null)
  const [showCategories, setShowCategories] = useState(false)
  const [categoryDraft, setCategoryDraft] = useState<CategoryDraft | null>(null)
  const [subcategoryDraft, setSubcategoryDraft] = useState<SubcategoryDraft | null>(null)
  const [showModifiers, setShowModifiers] = useState(false)
  const [modifierGroupDraft, setModifierGroupDraft] = useState<ModifierGroupDraft | null>(null)
  const [showTaxManager, setShowTaxManager] = useState(false)
  const [newTaxName, setNewTaxName] = useState('')
  const [newTaxRate, setNewTaxRate] = useState('5')
  const [newTaxInclusive, setNewTaxInclusive] = useState(true)

  const canManageMenu = permissions.includes('manage-menu')
  const canToggleStation = (station: 'KITCHEN' | 'BAR') =>
    canManageMenu ||
    (station === 'KITCHEN' && permissions.includes('manage-kitchen')) ||
    (station === 'BAR' && permissions.includes('manage-bar'))

  const itemsForCategory = (category: MenuCategoryRecord) => [
    ...category.items,
    ...category.subcategories.flatMap((subcategory) => subcategory.items),
  ]
  const allItems = useMemo(() => categories.flatMap(itemsForCategory), [categories])

  const filteredCategories = useMemo(() => {
    const query = search.trim().toLowerCase()
    return categories
      .filter((cat) => selectedCategory === 'all' || cat.id === selectedCategory)
      .map((cat) => ({
        ...cat,
        items: itemsForCategory(cat).filter((item) => {
          if (stationFilter !== 'ALL' && item.station !== stationFilter) return false
          if (!query) return true
          return (
            item.name.toLowerCase().includes(query) ||
            item.sku.toLowerCase().includes(query) ||
            (item.description ?? '').toLowerCase().includes(query)
          )
        }),
      }))
  }, [categories, selectedCategory, stationFilter, search])

  const openNewItemModal = () => {
    onClearErrors()
    const firstActiveCategory = categories.find((c) => c.active)
    setItemDraft({
      menu_category_id: String(firstActiveCategory?.id ?? ''),
      menu_subcategory_id: '',
      tax_category_id: String(taxCategories[0]?.id ?? ''),
      sku: '',
      name: '',
      description: '',
      image_path: '',
      station: firstActiveCategory?.default_station ?? 'KITCHEN',
      price: '',
      reason: '',
      modifier_group_ids: [],
      available: true,
      sort_order: 0,
      variants: [],
    })
  }

  const openEditItemModal = (item: MenuItemRecord) => {
    onClearErrors()
    setItemDraft({
      id: item.id,
      menu_category_id: String(item.menu_category_id),
      menu_subcategory_id: item.menu_subcategory_id ? String(item.menu_subcategory_id) : '',
      tax_category_id: item.tax_category_id ? String(item.tax_category_id) : '',
      sku: item.sku,
      name: item.name,
      description: item.description ?? '',
      image_path: item.image_path ?? '',
      station: item.station,
      price: item.price,
      originalPrice: item.price,
      reason: '',
      modifier_group_ids: item.modifier_groups.map((g) => g.id),
      available: item.available,
      sort_order: item.sort_order ?? 0,
      variants: item.variants.map((variant) => ({
        id: variant.id,
        sku: variant.sku,
        name: variant.name,
        price: variant.price,
        active: variant.active,
        available: variant.available,
        sort_order: variant.sort_order,
      })),
      priceHistories: item.price_histories ?? [],
    })
  }

  const submitItem = async (event: FormEvent) => {
    event.preventDefault()
    if (!itemDraft) return

    const payload: Record<string, unknown> = {
      menu_category_id: Number(itemDraft.menu_category_id),
      menu_subcategory_id: itemDraft.menu_subcategory_id ? Number(itemDraft.menu_subcategory_id) : null,
      tax_category_id: itemDraft.tax_category_id ? Number(itemDraft.tax_category_id) : null,
      sku: itemDraft.sku,
      name: itemDraft.name,
      description: itemDraft.description || null,
      image_path: itemDraft.image_path.trim() || null,
      station: itemDraft.station,
      price: Number(itemDraft.price),
      modifier_group_ids: itemDraft.modifier_group_ids,
      available: itemDraft.available,
      sort_order: itemDraft.sort_order,
      variants: itemDraft.variants.map((variant, index) => ({ ...variant, sort_order: variant.sort_order || index + 1 })),
    }

    if (itemDraft.id && itemDraft.reason.trim() !== '') {
      payload.reason = itemDraft.reason.trim()
    }

    const ok = await onSaveItem(payload, itemDraft.id)
    if (ok) {
      setItemDraft(null)
    }
  }

  const submitCategory = async (event: FormEvent) => {
    event.preventDefault()
    if (!categoryDraft) return
    const ok = await onSaveCategory(categoryDraft)
    if (ok) {
      setCategoryDraft(null)
    }
  }

  const submitSubcategory = async (event: FormEvent) => {
    event.preventDefault()
    if (!subcategoryDraft) return
    const ok = await onSaveSubcategory({
      menu_category_id: Number(subcategoryDraft.menu_category_id),
      name: subcategoryDraft.name,
      sort_order: subcategoryDraft.sort_order,
    }, subcategoryDraft.id)
    if (ok) setSubcategoryDraft(null)
  }

  const openNewModifierGroup = () => {
    onClearErrors()
    setModifierGroupDraft({
      name: '',
      min_selections: 0,
      max_selections: 1,
      required: false,
      sort_order: 0,
      options: [{ name: '', price: '0', active: true, available: true, sort_order: 1 }],
    })
  }

  const openEditModifierGroup = (group: ModifierGroup) => {
    onClearErrors()
    setModifierGroupDraft({
      id: group.id,
      name: group.name,
      min_selections: group.min_selections,
      max_selections: group.max_selections,
      required: group.required,
      sort_order: group.sort_order,
      options: group.options.map((option) => ({
        id: option.id,
        name: option.name,
        price: option.price,
        active: option.active,
        available: option.available,
        sort_order: option.sort_order,
      })),
    })
  }

  const submitModifierGroup = async (event: FormEvent) => {
    event.preventDefault()
    if (!modifierGroupDraft) return
    const ok = await onSaveModifierGroup({
      ...modifierGroupDraft,
      options: modifierGroupDraft.options.map((option, index) => ({ ...option, sort_order: option.sort_order || index + 1 })),
    }, modifierGroupDraft.id)
    if (ok) setModifierGroupDraft(null)
  }

  const priceChanged =
    Boolean(itemDraft?.id) &&
    Number(itemDraft?.price ?? 0).toFixed(2) !== Number(itemDraft?.originalPrice ?? 0).toFixed(2)

  return (
    <>
      <header>
        <div>
          <p className="eyebrow coral">MENU & PRICING</p>
          <h1>Built for fast service.</h1>
          <p className="muted">{restaurantName} · Effective-dated prices & station routing</p>
        </div>
        <div className="header-actions">
          <span className="live">
            <i /> BDT (৳)
          </span>
          {canManageMenu && (
            <>
              <button className="secondary compact" onClick={() => { onClearErrors(); setShowTaxManager(true) }}>
                Manage VAT / Tax
              </button>
              <button className="secondary compact" onClick={() => { onClearErrors(); setShowCategories(true) }}>
                Manage categories
              </button>
              <button className="secondary compact" onClick={() => { onClearErrors(); setShowModifiers(true) }}>
                Manage add-ons
              </button>
              <button className="primary compact" onClick={openNewItemModal}>
                ＋ Add item
              </button>
            </>
          )}
        </div>
      </header>

      <section className="summary-grid">
        <article>
          <span className="summary-icon green">✓</span>
          <div>
            <strong>{allItems.filter((i) => i.active && i.available).length}</strong>
            <p>Available</p>
          </div>
        </article>
        <article>
          <span className="summary-icon amber">!</span>
          <div>
            <strong>{allItems.filter((i) => i.active && !i.available).length}</strong>
            <p>Sold out</p>
          </div>
        </article>
        <article>
          <span className="summary-icon blue">♨</span>
          <div>
            <strong>{allItems.filter((i) => i.station === 'KITCHEN').length}</strong>
            <p>Kitchen items</p>
          </div>
        </article>
        <article>
          <span className="summary-icon ink">🍸</span>
          <div>
            <strong>{allItems.filter((i) => i.station === 'BAR').length}</strong>
            <p>Bar items</p>
          </div>
        </article>
      </section>

      <div className="menu-toolbar">
        <input
          type="search"
          className="menu-search"
          placeholder="Search by item name, SKU, or description…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <div className="station-pills">
          {(['ALL', 'KITCHEN', 'BAR'] as const).map((station) => (
            <button
              key={station}
              className={stationFilter === station ? 'active' : ''}
              onClick={() => setStationFilter(station)}
            >
              {station === 'ALL' ? 'All stations' : station}
            </button>
          ))}
        </div>
      </div>

      {categories.length === 0 ? (
        <section className="empty-state">
          <div>◫</div>
          <h2>No menu categories yet</h2>
          <p>Create categories like Mains, Starters, or Beverages to start building your menu.</p>
          {canManageMenu && (
            <button className="primary compact" onClick={() => setShowCategories(true)}>
              Manage categories
            </button>
          )}
        </section>
      ) : (
        <>
          <div className="zone-tabs">
            <button className={selectedCategory === 'all' ? 'active' : ''} onClick={() => setSelectedCategory('all')}>
              All categories
            </button>
            {categories.map((category) => (
              <button
                key={category.id}
                className={selectedCategory === category.id ? 'active' : ''}
                onClick={() => setSelectedCategory(category.id)}
              >
                {category.name}
                {!category.active && ' · Inactive'}
                <span>{category.items.length}</span>
              </button>
            ))}
          </div>

          {filteredCategories.map((category) => (
            <section className={`zone-section ${category.active ? '' : 'inactive-zone'}`} key={category.id}>
              <div className="zone-title">
                <h2>{category.name}</h2>
                <span>
                  {category.default_station} default · {category.items.length} items
                </span>
              </div>
              {category.items.length === 0 ? (
                <div className="inline-empty">No matching items in this category.</div>
              ) : (
                <div className="menu-grid">
                  {category.items.map((item) => (
                    <article
                      key={item.id}
                      className={`menu-card ${!item.active ? 'status-inactive' : !item.available ? 'menu-card-soldout' : ''}`}
                    >
                      <div className="menu-card-media">
                        <img
                          src={resolveMenuItemImage(item)}
                          alt={item.name}
                          loading="lazy"
                          onError={(e) => {
                            const fallback = buildFallbackDishSvg(item)
                            if (e.currentTarget.src !== fallback) e.currentTarget.src = fallback
                          }}
                        />
                        <div className="table-card-top menu-card-overlay">
                          <div className="menu-badges">
                            <span className="table-code">{item.sku}</span>
                            <span className={`station-badge station-${item.station.toLowerCase()}`}>{item.station}</span>
                          </div>
                          <span className={`availability-pill ${!item.active ? 'inactive' : item.available ? 'available' : 'soldout'}`}>
                            {!item.active ? 'INACTIVE' : item.available ? 'AVAILABLE' : 'SOLD OUT'}
                          </span>
                        </div>
                      </div>

                      <div className="menu-card-head">
                        <h3>{item.name}</h3>
                        <strong className="menu-price">৳{Number(item.price).toFixed(2)}</strong>
                      </div>

                      {item.description && <p className="menu-desc">{item.description}</p>}

                      {(item.variants.length > 0 || item.modifier_groups.length > 0) && (
                        <div className="menu-meta-tags">
                          {item.variants.map((v) => (
                            <span key={v.id}>
                              {v.name}: ৳{Number(v.price).toFixed(0)}
                            </span>
                          ))}
                          {item.modifier_groups.map((mg) => (
                            <span key={mg.id}>＋ {mg.name}</span>
                          ))}
                        </div>
                      )}

                      {(canManageMenu || canToggleStation(item.station)) && (
                        <div className="card-actions">
                          {item.active && canToggleStation(item.station) && (
                            <button
                              className={item.available ? 'danger-link' : 'activate-link'}
                              disabled={busy}
                              onClick={() => void onToggleAvailability(item, !item.available)}
                            >
                              {item.available ? 'Mark sold out' : 'Mark available'}
                            </button>
                          )}
                          {canManageMenu && (
                            <>
                              <button onClick={() => openEditItemModal(item)}>Edit</button>
                              <button
                                className={item.active ? 'danger-link' : 'activate-link'}
                                disabled={busy}
                                onClick={() => void onToggleItemActive(item, !item.active)}
                              >
                                {item.active ? 'Deactivate' : 'Activate'}
                              </button>
                            </>
                          )}
                        </div>
                      )}
                    </article>
                  ))}
                </div>
              )}
            </section>
          ))}
        </>
      )}

      {itemDraft && (
        <div className="modal-backdrop" onMouseDown={() => setItemDraft(null)}>
          <form className="modal zone-modal" onSubmit={submitItem} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">MENU ITEM</p>
                <h2>{itemDraft.id ? 'Edit menu item' : 'Add menu item'}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setItemDraft(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Category
                <select
                  required
                  value={itemDraft.menu_category_id}
                  onChange={(e) => setItemDraft({ ...itemDraft, menu_category_id: e.target.value, menu_subcategory_id: '' })}
                >
                  <option value="">Select category</option>
                  {categories
                    .filter((c) => c.active)
                    .map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                </select>
                <FieldError errors={fieldErrors.menu_category_id} />
              </label>

              <label>
                Subcategory
                <select
                  value={itemDraft.menu_subcategory_id}
                  onChange={(e) => setItemDraft({ ...itemDraft, menu_subcategory_id: e.target.value })}
                >
                  <option value="">No subcategory</option>
                  {categories
                    .find((category) => category.id === Number(itemDraft.menu_category_id))
                    ?.subcategories.filter((subcategory) => subcategory.active)
                    .map((subcategory) => (
                      <option key={subcategory.id} value={subcategory.id}>{subcategory.name}</option>
                    ))}
                </select>
                <FieldError errors={fieldErrors.menu_subcategory_id} />
              </label>

              <label>
                Station
                <select
                  value={itemDraft.station}
                  onChange={(e) => setItemDraft({ ...itemDraft, station: e.target.value as 'KITCHEN' | 'BAR' })}
                >
                  <option value="KITCHEN">Kitchen</option>
                  <option value="BAR">Bar</option>
                </select>
                <FieldError errors={fieldErrors.station} />
              </label>
            </div>

            <div className="field-row">
              <label>
                SKU
                <input
                  required
                  placeholder="BRG-01"
                  value={itemDraft.sku}
                  onChange={(e) => setItemDraft({ ...itemDraft, sku: e.target.value.toUpperCase() })}
                />
                <FieldError errors={fieldErrors.sku} />
              </label>

              <label>
                Price (BDT ৳)
                <input
                  required
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="450.00"
                  value={itemDraft.price}
                  onChange={(e) => setItemDraft({ ...itemDraft, price: e.target.value })}
                />
                <FieldError errors={fieldErrors.price} />
              </label>
            </div>

            <div className="field-row">
              <label>
                Display order
                <input type="number" min="0" value={itemDraft.sort_order} onChange={(e) => setItemDraft({ ...itemDraft, sort_order: Number(e.target.value) })} />
              </label>
              <label className="checkbox-pill">
                <input type="checkbox" checked={itemDraft.available} onChange={(e) => setItemDraft({ ...itemDraft, available: e.target.checked })} />
                <span>Available to order</span>
              </label>
            </div>

            <label>
              Item name
              <input
                required
                placeholder="Smokehouse Beef Burger"
                value={itemDraft.name}
                onChange={(e) => setItemDraft({ ...itemDraft, name: e.target.value })}
              />
              <FieldError errors={fieldErrors.name} />
            </label>

            <label>
              Description
              <input
                placeholder="Optional kitchen/waiter description"
                value={itemDraft.description}
                onChange={(e) => setItemDraft({ ...itemDraft, description: e.target.value })}
              />
              <FieldError errors={fieldErrors.description} />
            </label>

            <div className="item-photo-picker">
              <span className="picker-label">Item photo (shown on POS Order Composer & Menu)</span>
              <div className="item-photo-picker-row">
                <div className="item-photo-preview">
                  <img
                    src={resolveMenuItemImage(itemDraft)}
                    alt={itemDraft.name || 'Preview'}
                    onError={(e) => {
                      const fallback = buildFallbackDishSvg(itemDraft)
                      if (e.currentTarget.src !== fallback) e.currentTarget.src = fallback
                    }}
                  />
                </div>
                <div className="item-photo-controls">
                  <div className="item-photo-actions">
                    <label className="secondary compact photo-upload-btn">
                      <span>📷 Upload photo</span>
                      <input
                        type="file"
                        accept="image/*"
                        style={{ display: 'none' }}
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (!file) return
                          void compressImageFileToDataUrl(file).then((dataUrl) => {
                            setItemDraft((prev) => (prev ? { ...prev, image_path: dataUrl } : prev))
                          })
                          e.currentTarget.value = ''
                        }}
                      />
                    </label>
                    {itemDraft.image_path && (
                      <button
                        type="button"
                        className="danger-link compact"
                        onClick={() => setItemDraft({ ...itemDraft, image_path: '' })}
                      >
                        Remove photo
                      </button>
                    )}
                  </div>
                  <input
                    placeholder="Or paste an image URL (https://...)"
                    value={itemDraft.image_path.startsWith('data:') ? '' : itemDraft.image_path}
                    onChange={(e) => setItemDraft({ ...itemDraft, image_path: e.target.value })}
                  />
                </div>
              </div>
              <FieldError errors={fieldErrors.image_path} />
            </div>

            <label>
              VAT / Tax Mode for this Item
              <select
                value={itemDraft.tax_category_id}
                onChange={(e) => setItemDraft({ ...itemDraft, tax_category_id: e.target.value })}
              >
                <option value="">No VAT (0% — Food price only, no tax)</option>
                {taxCategories.map((tax) => (
                  <option key={tax.id} value={tax.id}>
                    {tax.name} ({Number(tax.rate).toFixed(1)}% ·{' '}
                    {tax.inclusive ? 'Included inside food price — NO external VAT added' : 'External VAT — added on top of bill'})
                  </option>
                ))}
              </select>
            </label>

            {modifierGroups.length > 0 && (
              <div className="modifier-picker">
                <span className="picker-label">Modifier groups</span>
                <div className="modifier-checkboxes">
                  {modifierGroups.map((group) => {
                    const checked = itemDraft.modifier_group_ids.includes(group.id)
                    return (
                      <label key={group.id} className="checkbox-pill">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(e) => {
                            const next = e.target.checked
                              ? [...itemDraft.modifier_group_ids, group.id]
                              : itemDraft.modifier_group_ids.filter((id) => id !== group.id)
                            setItemDraft({ ...itemDraft, modifier_group_ids: next })
                          }}
                        />
                        <span>{group.name}</span>
                      </label>
                    )
                  })}
                </div>
              </div>
            )}

            <div className="price-history-box">
              <strong>Variants / sizes</strong>
              <p className="muted">A variant price replaces the base item price for that order line.</p>
              <div className="variant-editor-list">
                {itemDraft.variants.map((variant, index) => (
                  <div className="variant-editor-row" key={variant.id ?? `new-${index}`}>
                    <input required placeholder="Regular" value={variant.name} onChange={(e) => {
                      const variants = [...itemDraft.variants]
                      variants[index] = { ...variant, name: e.target.value }
                      setItemDraft({ ...itemDraft, variants })
                    }} />
                    <input required placeholder="SKU" value={variant.sku} onChange={(e) => {
                      const variants = [...itemDraft.variants]
                      variants[index] = { ...variant, sku: e.target.value.toUpperCase() }
                      setItemDraft({ ...itemDraft, variants })
                    }} />
                    <input required type="number" min="0" step="0.01" placeholder="Price" value={variant.price} onChange={(e) => {
                      const variants = [...itemDraft.variants]
                      variants[index] = { ...variant, price: e.target.value }
                      setItemDraft({ ...itemDraft, variants })
                    }} />
                    <label className="checkbox-pill"><input type="checkbox" checked={variant.available} onChange={(e) => {
                      const variants = [...itemDraft.variants]
                      variants[index] = { ...variant, available: e.target.checked }
                      setItemDraft({ ...itemDraft, variants })
                    }} /><span>Available</span></label>
                    <button type="button" className="danger-link" onClick={() => setItemDraft({ ...itemDraft, variants: itemDraft.variants.filter((_, rowIndex) => rowIndex !== index) })}>Remove</button>
                  </div>
                ))}
              </div>
              <button type="button" className="secondary compact" onClick={() => setItemDraft({
                ...itemDraft,
                variants: [...itemDraft.variants, { name: '', sku: '', price: itemDraft.price || '0', active: true, available: true, sort_order: itemDraft.variants.length + 1 }],
              })}>＋ Add size / variant</button>
            </div>

            {priceChanged && (
              <label>
                Reason for price change (required)
                <input
                  required
                  placeholder="e.g. Seasonal supplier cost update"
                  value={itemDraft.reason}
                  onChange={(e) => setItemDraft({ ...itemDraft, reason: e.target.value })}
                />
                <FieldError errors={fieldErrors.reason} />
              </label>
            )}

            {itemDraft.priceHistories && itemDraft.priceHistories.length > 0 && (
              <div className="price-history-box">
                <strong>Recent price history</strong>
                <ul>
                  {itemDraft.priceHistories.map((ph) => (
                    <li key={ph.id}>
                      <span>৳{Number(ph.amount).toFixed(2)}</span>
                      <span>{ph.reason ?? 'Initial price'}</span>
                      <span>{new Date(ph.effective_from).toLocaleDateString()}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setItemDraft(null)}>
                Cancel
              </button>
              <button className="primary" disabled={busy}>
                {busy ? 'Saving…' : itemDraft.id ? 'Save changes' : 'Add item'}
              </button>
            </div>
          </form>
        </div>
      )}

      {showCategories && (
        <div className="modal-backdrop" onMouseDown={() => { setShowCategories(false); setCategoryDraft(null) }}>
          <section className="modal zone-modal" onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">MENU SETUP</p>
                <h2>Manage categories</h2>
              </div>
              <button
                type="button"
                className="icon-button"
                onClick={() => {
                  setShowCategories(false)
                  setCategoryDraft(null)
                }}
              >
                ×
              </button>
            </div>

            {categoryDraft ? (
              <form onSubmit={submitCategory}>
                <label>
                  Category name
                  <input
                    autoFocus
                    required
                    value={categoryDraft.name}
                    onChange={(e) => setCategoryDraft({ ...categoryDraft, name: e.target.value })}
                  />
                  <FieldError errors={fieldErrors.name} />
                </label>
                <label>
                  Default preparation station
                  <select
                    value={categoryDraft.default_station}
                    onChange={(e) =>
                      setCategoryDraft({
                        ...categoryDraft,
                        default_station: e.target.value as 'KITCHEN' | 'BAR',
                      })
                    }
                  >
                    <option value="KITCHEN">Kitchen</option>
                    <option value="BAR">Bar</option>
                  </select>
                </label>
                <label>
                  Display order
                  <input type="number" min="0" value={categoryDraft.sort_order} onChange={(e) => setCategoryDraft({ ...categoryDraft, sort_order: Number(e.target.value) })} />
                </label>
                <div className="modal-actions">
                  <button type="button" className="secondary" onClick={() => setCategoryDraft(null)}>
                    Cancel
                  </button>
                  <button className="primary" disabled={busy}>
                    {busy ? 'Saving…' : categoryDraft.id ? 'Save category' : 'Add category'}
                  </button>
                </div>
              </form>
            ) : (
              <>
                <button
                  className="primary compact zone-add"
                  onClick={() => setCategoryDraft({ name: '', default_station: 'KITCHEN', sort_order: 0 })}
                >
                  ＋ Add category
                </button>
                <div className="zone-list">
                  {categories.map((cat) => (
                    <div className="zone-row" key={cat.id}>
                      <div>
                        <strong>{cat.name}</strong>
                        <span>
                          {cat.default_station} · {cat.active ? `${cat.items.length} items` : 'Inactive'}
                        </span>
                      </div>
                      <div>
                        <button
                          onClick={() =>
                            setCategoryDraft({
                              id: cat.id,
                              name: cat.name,
                              default_station: cat.default_station,
                              sort_order: cat.sort_order,
                            })
                          }
                        >
                          Edit
                        </button>
                        <button
                          className={cat.active ? 'danger-link' : 'activate-link'}
                          disabled={busy}
                          onClick={() => void onToggleCategoryActive(cat, !cat.active)}
                        >
                          {cat.active ? 'Deactivate' : 'Activate'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="price-history-box">
                  <strong>Subcategories</strong>
                  <p className="muted">Use subcategories such as Hot Drinks and Cold Drinks to keep waiter menus fast.</p>
                  <button type="button" className="secondary compact" onClick={() => setSubcategoryDraft({
                    menu_category_id: String(categories.find((category) => category.active)?.id ?? ''),
                    name: '',
                    sort_order: 0,
                  })}>＋ Add subcategory</button>
                  <div className="zone-list">
                    {categories.flatMap((category) => category.subcategories.map((subcategory) => ({ category, subcategory }))).map(({ category, subcategory }) => (
                      <div className="zone-row" key={subcategory.id}>
                        <div><strong>{subcategory.name}</strong><span>{category.name} · {subcategory.active ? `${subcategory.items.length} items` : 'Inactive'}</span></div>
                        <div>
                          <button type="button" onClick={() => setSubcategoryDraft({ id: subcategory.id, menu_category_id: String(category.id), name: subcategory.name, sort_order: subcategory.sort_order })}>Edit</button>
                          <button type="button" className={subcategory.active ? 'danger-link' : 'activate-link'} disabled={busy} onClick={() => void onToggleSubcategoryActive(subcategory.id, subcategory.name, !subcategory.active)}>{subcategory.active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </>
            )}
          </section>
        </div>
      )}

      {subcategoryDraft && (
        <div className="modal-backdrop" onMouseDown={() => setSubcategoryDraft(null)}>
          <form className="modal" onSubmit={submitSubcategory} onMouseDown={(event) => event.stopPropagation()}>
            <div className="modal-head"><div><p className="eyebrow coral">MENU STRUCTURE</p><h2>{subcategoryDraft.id ? 'Edit subcategory' : 'Add subcategory'}</h2></div><button type="button" className="icon-button" onClick={() => setSubcategoryDraft(null)}>×</button></div>
            <label>Category<select required value={subcategoryDraft.menu_category_id} onChange={(event) => setSubcategoryDraft({ ...subcategoryDraft, menu_category_id: event.target.value })}><option value="">Select category</option>{categories.filter((category) => category.active).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
            <label>Subcategory name<input autoFocus required value={subcategoryDraft.name} onChange={(event) => setSubcategoryDraft({ ...subcategoryDraft, name: event.target.value })} /><FieldError errors={fieldErrors.name} /></label>
            <label>Display order<input type="number" min="0" value={subcategoryDraft.sort_order} onChange={(event) => setSubcategoryDraft({ ...subcategoryDraft, sort_order: Number(event.target.value) })} /></label>
            <div className="modal-actions"><button type="button" className="secondary" onClick={() => setSubcategoryDraft(null)}>Cancel</button><button className="primary" disabled={busy}>{busy ? 'Saving…' : 'Save subcategory'}</button></div>
          </form>
        </div>
      )}

      {showModifiers && (
        <div className="modal-backdrop" onMouseDown={() => { setShowModifiers(false); setModifierGroupDraft(null) }}>
          <section className="modal zone-modal modifier-manager" onMouseDown={(event) => event.stopPropagation()}>
            <div className="modal-head"><div><p className="eyebrow coral">ADD-ONS & CHOICES</p><h2>Manage add-on groups</h2></div><button type="button" className="icon-button" onClick={() => { setShowModifiers(false); setModifierGroupDraft(null) }}>×</button></div>
            {modifierGroupDraft ? (
              <form onSubmit={submitModifierGroup}>
                <label>Group name<input autoFocus required placeholder="e.g. Choose a side" value={modifierGroupDraft.name} onChange={(event) => setModifierGroupDraft({ ...modifierGroupDraft, name: event.target.value })} /><FieldError errors={fieldErrors.name} /></label>
                <div className="field-row">
                  <label>Minimum choices<input type="number" min="0" max="20" value={modifierGroupDraft.min_selections} onChange={(event) => setModifierGroupDraft({ ...modifierGroupDraft, min_selections: Number(event.target.value) })} /><FieldError errors={fieldErrors.min_selections} /></label>
                  <label>Maximum choices<input type="number" min="1" max="20" value={modifierGroupDraft.max_selections} onChange={(event) => setModifierGroupDraft({ ...modifierGroupDraft, max_selections: Number(event.target.value) })} /><FieldError errors={fieldErrors.max_selections} /></label>
                </div>
                <label className="checkbox-pill"><input type="checkbox" checked={modifierGroupDraft.required} onChange={(event) => setModifierGroupDraft({ ...modifierGroupDraft, required: event.target.checked, min_selections: event.target.checked ? Math.max(1, modifierGroupDraft.min_selections) : modifierGroupDraft.min_selections })} /><span>Required before adding to an order</span></label>
                <div className="price-history-box">
                  <strong>Choices and price adjustments</strong>
                  <div className="variant-editor-list">
                    {modifierGroupDraft.options.map((option, index) => (
                      <div className="variant-editor-row" key={option.id ?? `choice-${index}`}>
                        <input required placeholder="Choice name" value={option.name} onChange={(event) => {
                          const options = [...modifierGroupDraft.options]; options[index] = { ...option, name: event.target.value }; setModifierGroupDraft({ ...modifierGroupDraft, options })
                        }} />
                        <input required type="number" min="0" step="0.01" placeholder="+ Price" value={option.price} onChange={(event) => {
                          const options = [...modifierGroupDraft.options]; options[index] = { ...option, price: event.target.value }; setModifierGroupDraft({ ...modifierGroupDraft, options })
                        }} />
                        <label className="checkbox-pill"><input type="checkbox" checked={option.available} onChange={(event) => {
                          const options = [...modifierGroupDraft.options]; options[index] = { ...option, available: event.target.checked }; setModifierGroupDraft({ ...modifierGroupDraft, options })
                        }} /><span>Available</span></label>
                        <button type="button" className="danger-link" onClick={() => setModifierGroupDraft({ ...modifierGroupDraft, options: modifierGroupDraft.options.filter((_, optionIndex) => optionIndex !== index) })}>Remove</button>
                      </div>
                    ))}
                  </div>
                  <button type="button" className="secondary compact" onClick={() => setModifierGroupDraft({ ...modifierGroupDraft, options: [...modifierGroupDraft.options, { name: '', price: '0', active: true, available: true, sort_order: modifierGroupDraft.options.length + 1 }] })}>＋ Add choice</button>
                  <FieldError errors={fieldErrors.options} />
                </div>
                <div className="modal-actions"><button type="button" className="secondary" onClick={() => setModifierGroupDraft(null)}>Cancel</button><button className="primary" disabled={busy}>{busy ? 'Saving…' : 'Save add-on group'}</button></div>
              </form>
            ) : (
              <>
                <button type="button" className="primary compact zone-add" onClick={openNewModifierGroup}>＋ Add add-on group</button>
                <div className="zone-list">
                  {modifierGroups.map((group) => <div className="zone-row" key={group.id}><div><strong>{group.name}</strong><span>{group.required ? `Required · ${group.min_selections}–${group.max_selections}` : `Optional · up to ${group.max_selections}`} · {group.options.length} choices</span></div><div><button type="button" onClick={() => openEditModifierGroup(group)}>Edit</button><button type="button" className={group.active ? 'danger-link' : 'activate-link'} disabled={busy} onClick={() => void onToggleModifierGroupActive(group.id, group.name, !group.active)}>{group.active ? 'Deactivate' : 'Activate'}</button></div></div>)}
                </div>
              </>
            )}
          </section>
        </div>
      )}

      {showTaxManager && (
        <div className="modal-backdrop" onMouseDown={() => setShowTaxManager(false)}>
          <section className="modal zone-modal" onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">VAT & TAX CONFIGURATION</p>
                <h2>Manage VAT Inside Food vs External VAT</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setShowTaxManager(false)}>
                ×
              </button>
            </div>

            <p className="muted" style={{ marginBottom: '14px' }}>
              Choose how VAT works across your menu. You can switch the entire menu with <strong>1 click</strong> below, or assign different VAT rules to individual items in <strong>Edit Item</strong>.
            </p>

            <div className="zone-list" style={{ marginBottom: '20px' }}>
              <div className="zone-row">
                <div>
                  <strong>0% · No VAT (Food Price Only)</strong>
                  <span>Customer pays exact menu price (e.g. ৳450 item = ৳450.00 bill).</span>
                </div>
                {onApplyBulkTaxMode && (
                  <button
                    type="button"
                    className="activate-link"
                    disabled={busy}
                    onClick={() => void onApplyBulkTaxMode(null, 'No VAT (Food Price Only)')}
                  >
                    Apply to All Items
                  </button>
                )}
              </div>

              {taxCategories.map((tax) => (
                <div className="zone-row" key={tax.id}>
                  <div>
                    <strong>
                      {tax.name} ({Number(tax.rate).toFixed(1)}%)
                    </strong>
                    <span>
                      {tax.inclusive
                        ? '✓ VAT INSIDE FOOD PRICE (Inclusive) — No extra VAT added on customer bill (৳450 item = ৳450.00 total)'
                        : '＋ EXTERNAL VAT ADDED ON BILL (Exclusive) — Added on top at checkout (৳450 item + 5% = ৳472.50 total)'}
                    </span>
                  </div>
                  {onApplyBulkTaxMode && (
                    <button
                      type="button"
                      className="activate-link"
                      disabled={busy}
                      onClick={() => void onApplyBulkTaxMode(tax.id, tax.name)}
                    >
                      Apply to All Items
                    </button>
                  )}
                </div>
              ))}
            </div>

            {onSaveTaxCategory && (
              <form
                className="price-history-box"
                onSubmit={async (e) => {
                  e.preventDefault()
                  if (!newTaxName.trim()) return
                  const ok = await onSaveTaxCategory({
                    name: newTaxName.trim(),
                    rate: Number(newTaxRate),
                    inclusive: newTaxInclusive,
                  })
                  if (ok) {
                    setNewTaxName('')
                  }
                }}
              >
                <strong>＋ Create Custom VAT / Tax Rule</strong>
                <div className="field-row" style={{ marginTop: '10px' }}>
                  <label style={{ margin: 0 }}>
                    Rule Name
                    <input
                      required
                      placeholder="e.g. VAT Included 5% or External VAT 10%"
                      value={newTaxName}
                      onChange={(e) => setNewTaxName(e.target.value)}
                    />
                  </label>
                  <label style={{ margin: 0 }}>
                    VAT Rate (%)
                    <input
                      required
                      type="number"
                      min="0"
                      max="100"
                      step="0.1"
                      value={newTaxRate}
                      onChange={(e) => setNewTaxRate(e.target.value)}
                    />
                  </label>
                </div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', marginTop: '12px' }}>
                  <label className="checkbox-pill">
                    <input
                      type="radio"
                      name="vat_mode"
                      checked={newTaxInclusive}
                      onChange={() => setNewTaxInclusive(true)}
                    />
                    <span>
                      <strong>VAT Inside Food Price</strong> (No extra VAT added to bill)
                    </span>
                  </label>
                  <label className="checkbox-pill">
                    <input
                      type="radio"
                      name="vat_mode"
                      checked={!newTaxInclusive}
                      onChange={() => setNewTaxInclusive(false)}
                    />
                    <span>
                      <strong>External VAT</strong> (Added on top of bill)
                    </span>
                  </label>
                </div>
                <div className="modal-actions" style={{ marginTop: '14px' }}>
                  <button type="submit" className="primary compact" disabled={busy}>
                    Save VAT Rule
                  </button>
                </div>
              </form>
            )}
          </section>
        </div>
      )}
    </>
  )
}

function FieldError({ errors }: { errors?: string[] }) {
  return errors?.length ? <span className="field-error">{errors[0]}</span> : null
}
