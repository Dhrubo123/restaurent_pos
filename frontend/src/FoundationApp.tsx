import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import './restaurant.css'
import './foundation.css'
import MenuWorkspace from './MenuWorkspace.tsx'
import type { MenuCategoryRecord, MenuItemRecord, ModifierGroup, TaxCategory } from './MenuWorkspace.tsx'
import OrderWorkspace from './OrderWorkspace.tsx'
import type { FloorTableOption, OrderItemRecord, OrderRecord } from './OrderWorkspace.tsx'
import KotWorkspace from './KotWorkspace.tsx'
import KotPrintModal from './KotPrintModal.tsx'
import type { KotLineRecord, KotRecord } from './KotPrintModal.tsx'
import CashierWorkspace from './CashierWorkspace.tsx'
import type { CashierShiftRecord } from './CashierWorkspace.tsx'
import InvoicePrintModal, { buildGuestBillFromOrder } from './InvoicePrintModal.tsx'
import type { InvoiceRecord } from './InvoicePrintModal.tsx'
import ReportsWorkspace from './ReportsWorkspace.tsx'
import type { AuditEventRecord, ReportSummaryData } from './ReportsWorkspace.tsx'
import InventoryWorkspace from './InventoryWorkspace.tsx'
import type { InventoryData } from './InventoryWorkspace.tsx'
import StaffWorkspace from './StaffWorkspace.tsx'
import type { StaffRecord, StaffRoleOption } from './StaffWorkspace.tsx'

type User = {
  name: string
  roles: string[]
  permissions: string[]
  restaurant: { name: string }
}

type DiningTable = {
  id: number
  zone_id?: number
  code: string
  display_name: string
  capacity: number
  active: boolean
  status: 'AVAILABLE' | 'OPEN' | 'BILL_REQUESTED' | 'PARTIALLY_PAID' | 'INACTIVE'
  order: null | {
    id: number
    order_number: string
    status: string
    guest_count: number
    total?: string
    items_count?: number
    waiter_name?: string
    opened_at: string
    version: number
  }
}

type Zone = { id: number; name: string; active: boolean; tables: DiningTable[] }
type TableDraft = { id?: number; zone_id: string; code: string; display_name: string; capacity: number }
type ZoneDraft = { id?: number; name: string }

class ApiFailure extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'

async function request<T>(path: string, csrfToken = '', options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    credentials: 'include',
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      ...options.headers,
    },
  })
  const body = await response.json().catch(() => ({})) as {
    message?: string
    errors?: Record<string, string[]>
  }

  if (!response.ok) {
    throw new ApiFailure(body.message ?? 'The request could not be completed.', response.status, body.errors)
  }

  return body as T
}

const emptyTable: TableDraft = { zone_id: '', code: '', display_name: '', capacity: 4 }

export default function FoundationApp() {
  const [user, setUser] = useState<User | null>(null)
  const [activeTab, setActiveTab] = useState<
    'floor' | 'orders' | 'menu' | 'kitchen' | 'cashier' | 'reports' | 'inventory' | 'staff'
  >('floor')
  const [kots, setKots] = useState<KotRecord[]>([])
  const [kotStations, setKotStations] = useState<string[]>([])
  const [includeCompletedKots, setIncludeCompletedKots] = useState(false)
  const [zones, setZones] = useState<Zone[]>([])
  const [selectedZone, setSelectedZone] = useState<number | 'all'>('all')
  const [orders, setOrders] = useState<OrderRecord[]>([])
  const [selectedOrder, setSelectedOrder] = useState<OrderRecord | null>(null)
  const [printKots, setPrintKots] = useState<KotRecord[]>([])
  const [currentShift, setCurrentShift] = useState<CashierShiftRecord | null>(null)
  const [recentShifts, setRecentShifts] = useState<CashierShiftRecord[]>([])
  const [recentInvoices, setRecentInvoices] = useState<InvoiceRecord[]>([])
  const [printInvoice, setPrintInvoice] = useState<InvoiceRecord | null>(null)
  const [reportData, setReportData] = useState<ReportSummaryData | null>(null)
  const [auditEvents, setAuditEvents] = useState<AuditEventRecord[]>([])
  const [inventoryData, setInventoryData] = useState<InventoryData | null>(null)
  const [staffList, setStaffList] = useState<StaffRecord[]>([])
  const [staffRoleOptions, setStaffRoleOptions] = useState<StaffRoleOption[]>([])
  const [menuCategories, setMenuCategories] = useState<MenuCategoryRecord[]>([])
  const [taxCategories, setTaxCategories] = useState<TaxCategory[]>([])
  const [modifierGroups, setModifierGroups] = useState<ModifierGroup[]>([])
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [csrfToken, setCsrfToken] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [tableDraft, setTableDraft] = useState<TableDraft | null>(null)
  const [zoneDraft, setZoneDraft] = useState<ZoneDraft | null>(null)
  const [showZones, setShowZones] = useState(false)

  const canViewFloor = user?.permissions.includes('view-floor') ?? false
  const canManageFloor = user?.permissions.includes('manage-floor') ?? false
  const canManageOrders = user?.permissions.includes('manage-orders') ?? false
  const canTakePayment = user?.permissions.includes('take-payment') ?? false
  const canManageMenu = user?.permissions.includes('manage-menu') ?? false
  const canManageInventory = canManageMenu || canManageFloor
  const canManageStaff = (user?.permissions.includes('manage-users') ?? false) || canManageFloor || canManageMenu
  const canViewReports = (user?.permissions.includes('view-reports') ?? false) || canManageFloor || canManageMenu
  const canRefundOrVoid = (user?.permissions.includes('refund-payment') ?? false) || canManageFloor || canManageMenu
  const canViewKots = user
    ? ['manage-orders', 'manage-kitchen', 'manage-bar', 'manage-floor', 'manage-menu', 'view-floor'].some((p) =>
        user.permissions.includes(p),
      )
    : false
  const canCancelSentItems = canManageFloor || canManageMenu

  const refreshCsrf = async () => {
    const result = await request<{ token: string }>('/csrf-token')
    setCsrfToken(result.token)
    return result.token
  }

  const loadFloor = async (manageFloor = canManageFloor) => {
    const result = await request<{ data: Zone[] }>(`/floor${manageFloor ? '?include_inactive=1' : ''}`)
    setZones(result.data)
  }

  const loadOrders = async (preferOrderId?: number) => {
    const result = await request<{ data: OrderRecord[] }>('/orders')
    setOrders(result.data)
    setSelectedOrder((prev) => {
      const targetId = preferOrderId ?? prev?.id
      if (targetId) {
        const match = result.data.find((o) => o.id === targetId)
        if (match) return match
      }
      return result.data[0] ?? null
    })
  }

  const loadKots = async (includeCompleted = includeCompletedKots) => {
    const result = await request<{ data: KotRecord[]; meta: { allowed_stations: string[] } }>(
      `/kots${includeCompleted ? '?include_completed=1' : ''}`,
    )
    setKots(result.data)
    setKotStations(result.meta.allowed_stations)
  }

  const loadMenu = async (manageMenu = canManageMenu) => {
    const result = await request<{
      data: MenuCategoryRecord[]
      meta: { tax_categories: TaxCategory[]; modifier_groups: ModifierGroup[] }
    }>(`/menu${manageMenu ? '?include_inactive=1' : ''}`)
    setMenuCategories(result.data)
    setTaxCategories(result.meta.tax_categories)
    setModifierGroups(result.meta.modifier_groups)
  }

  const loadBillingData = async () => {
    const [shiftRes, invoicesRes] = await Promise.all([
      request<{ data: { current_shift: CashierShiftRecord | null; recent_shifts: CashierShiftRecord[] } }>('/shifts/current'),
      request<{ data: InvoiceRecord[] }>('/invoices'),
    ])
    setCurrentShift(shiftRes.data.current_shift)
    setRecentShifts(shiftRes.data.recent_shifts)
    setRecentInvoices(invoicesRes.data)
  }

  const loadReports = async (from?: string, to?: string) => {
    const params = new URLSearchParams()
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    const qs = params.toString() ? `?${params.toString()}` : ''
    const result = await request<{ data: ReportSummaryData }>(`/reports/summary${qs}`)
    setReportData(result.data)
  }

  const loadAuditEvents = async (action = '', search = '') => {
    const params = new URLSearchParams()
    if (action) params.set('action', action)
    if (search) params.set('search', search)
    const qs = params.toString() ? `?${params.toString()}` : ''
    const result = await request<{ data: AuditEventRecord[] }>(`/audit-events${qs}`)
    setAuditEvents(result.data)
  }

  const loadInventory = async () => {
    const result = await request<{ data: InventoryData }>('/inventory')
    setInventoryData(result.data)
  }

  const loadStaff = async () => {
    const result = await request<{ data: StaffRecord[]; meta: { roles: StaffRoleOption[] } }>('/staff')
    setStaffList(result.data)
    setStaffRoleOptions(result.meta.roles)
  }

  const bootstrapUser = async (currentUser: User) => {
    const hasFloor = currentUser.permissions.includes('view-floor')
    const manageFloor = currentUser.permissions.includes('manage-floor')
    const manageOrders = currentUser.permissions.includes('manage-orders')
    const takePayment = currentUser.permissions.includes('take-payment')
    const manageMenu = currentUser.permissions.includes('manage-menu')
    const manageUsers = currentUser.permissions.includes('manage-users')
    const viewReports = currentUser.permissions.includes('view-reports') || manageFloor || manageMenu

    const tasks: Promise<unknown>[] = [loadMenu(manageMenu)]
    if (hasFloor) tasks.push(loadFloor(manageFloor))
    if (manageOrders || takePayment) tasks.push(loadOrders())
    if (takePayment) tasks.push(loadBillingData())
    if (viewReports) tasks.push(loadReports(), loadAuditEvents())
    if (manageMenu || manageFloor) tasks.push(loadInventory())
    if (manageUsers || manageFloor || manageMenu) tasks.push(loadStaff())

    const hasKots = ['manage-orders', 'manage-kitchen', 'manage-bar', 'manage-floor', 'manage-menu', 'view-floor'].some(
      (p) => currentUser.permissions.includes(p),
    )
    if (hasKots) tasks.push(loadKots(false))

    if (!hasFloor) {
      setActiveTab(takePayment ? 'cashier' : hasKots ? 'kitchen' : 'menu')
    }
    await Promise.all(tasks)
  }

  useEffect(() => {
    void (async () => {
      try {
        await refreshCsrf()
        const result = await request<{ data: User }>('/me')
        setUser(result.data)
        await bootstrapUser(result.data)
      } catch (caught) {
        if (caught instanceof ApiFailure && caught.status !== 401) {
          setError(caught.message)
        }
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  const visibleZones = useMemo(
    () => (selectedZone === 'all' ? zones : zones.filter((zone) => zone.id === selectedZone)),
    [zones, selectedZone],
  )
  const tables = useMemo(() => zones.flatMap((zone) => zone.tables), [zones])

  const floorTableOptions: FloorTableOption[] = useMemo(
    () =>
      zones.flatMap((zone) =>
        zone.tables.map((t) => ({
          id: t.id,
          code: t.code,
          display_name: t.display_name,
          capacity: t.capacity,
          zoneName: zone.name,
          active: t.active,
          status: t.status,
          orderId: t.order?.id,
          orderNumber: t.order?.order_number,
        })),
      ),
    [zones],
  )

  const resetMessages = () => {
    setError('')
    setSuccess('')
    setFieldErrors({})
  }

  const handleFailure = (caught: unknown) => {
    if (caught instanceof ApiFailure) {
      setError(caught.message)
      setFieldErrors(caught.errors)
      return
    }
    setError('The request could not be completed.')
  }

  const login = async (event: FormEvent) => {
    event.preventDefault()
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: User; token: string }>('/auth/login', csrfToken, {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
      setPassword('')
      setCsrfToken(result.token)
      setUser(result.data)
      await bootstrapUser(result.data)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const logout = async () => {
    resetMessages()
    setBusy(true)
    try {
      await request('/auth/logout', csrfToken, { method: 'POST' })
      setUser(null)
      setZones([])
      setOrders([])
      setSelectedOrder(null)
      setMenuCategories([])
      setEmail('')
      setPassword('')
      setActiveTab('floor')
      await refreshCsrf()
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveTable = async (event: FormEvent) => {
    event.preventDefault()
    if (!tableDraft) return
    resetMessages()
    setBusy(true)
    const editing = Boolean(tableDraft.id)
    try {
      await request(editing ? `/tables/${tableDraft.id}` : '/tables', csrfToken, {
        method: editing ? 'PATCH' : 'POST',
        body: JSON.stringify({ ...tableDraft, zone_id: Number(tableDraft.zone_id) }),
      })
      setTableDraft(null)
      setSuccess(editing ? 'Table updated successfully.' : 'Table added successfully.')
      await loadFloor(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveZone = async (event: FormEvent) => {
    event.preventDefault()
    if (!zoneDraft) return
    resetMessages()
    setBusy(true)
    const editing = Boolean(zoneDraft.id)
    try {
      await request(editing ? `/zones/${zoneDraft.id}` : '/zones', csrfToken, {
        method: editing ? 'PATCH' : 'POST',
        body: JSON.stringify({ name: zoneDraft.name }),
      })
      setZoneDraft(null)
      setSuccess(editing ? 'Zone updated successfully.' : 'Zone added successfully.')
      await loadFloor(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const setActivation = async (resource: 'tables' | 'zones', id: number, active: boolean) => {
    if (!active && !window.confirm(`Deactivate this ${resource === 'tables' ? 'table' : 'zone'}?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/${resource}/${id}/activation`, csrfToken, { method: active ? 'POST' : 'DELETE' })
      setSuccess(`${resource === 'tables' ? 'Table' : 'Zone'} ${active ? 'activated' : 'deactivated'} successfully.`)
      await loadFloor(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const openOrFocusTableOrder = async (tableId: number, guestCount = 2) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/tables/${tableId}/orders`, csrfToken, {
        method: 'POST',
        headers: { 'Idempotency-Key': `open-${tableId}-${Date.now()}` },
        body: JSON.stringify({ guest_count: guestCount }),
      })
      setSelectedOrder(result.data)
      setActiveTab('orders')
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const selectOrderById = async (orderId: number) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${orderId}`)
      setSelectedOrder(result.data)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const updateOrderMeta = async (order: OrderRecord, guestCount: number, note: string | null) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}`, csrfToken, {
        method: 'PATCH',
        body: JSON.stringify({ version: order.version, guest_count: guestCount, note }),
      })
      setSelectedOrder(result.data)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const addOrderItem = async (
    order: OrderRecord,
    payload: {
      menu_item_id: number
      item_variant_id?: number | null
      quantity: number
      modifier_option_ids?: number[]
      note?: string
    },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/items`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ ...payload, version: order.version }),
      })
      setSelectedOrder(result.data)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const updateOrderItem = async (
    order: OrderRecord,
    item: OrderItemRecord,
    quantity: number,
    note: string | null,
  ) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/items/${item.id}`, csrfToken, {
        method: 'PATCH',
        body: JSON.stringify({ version: order.version, quantity, note }),
      })
      setSelectedOrder(result.data)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const removeOrderItem = async (order: OrderRecord, item: OrderItemRecord) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/items/${item.id}`, csrfToken, {
        method: 'DELETE',
        body: JSON.stringify({ version: order.version }),
      })
      setSelectedOrder(result.data)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const cancelSentOrderItem = async (
    order: OrderRecord,
    item: OrderItemRecord,
    reason: string,
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(
        `/orders/${order.id}/items/${item.id}/cancel`,
        csrfToken,
        {
          method: 'POST',
          body: JSON.stringify({ version: order.version, reason }),
        },
      )
      setSelectedOrder(result.data)
      setSuccess(`${item.item_name} was cancelled.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const sendOrderRound = async (order: OrderRecord) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord; kots: KotRecord[] }>(
        `/orders/${order.id}/send`,
        csrfToken,
        {
          method: 'POST',
          headers: { 'Idempotency-Key': `send-${order.id}-${Date.now()}` },
          body: JSON.stringify({ version: order.version }),
        },
      )
      setSelectedOrder(result.data)
      setPrintKots(result.kots)
      setSuccess(`Round sent for ${result.data.order_number}.`)
      await Promise.all([
        loadFloor(),
        loadOrders(result.data.id),
        loadKots(),
        loadMenu(canManageMenu),
        canManageInventory ? loadInventory() : Promise.resolve(),
      ])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const toggleCompletedKots = async (include: boolean) => {
    setIncludeCompletedKots(include)
    resetMessages()
    setBusy(true)
    try {
      await loadKots(include)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const startKot = async (kot: KotRecord) => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: KotRecord }>(`/kots/${kot.id}/start`, csrfToken, { method: 'POST' })
      setSuccess(`${kot.kot_number} marked as preparing.`)
      await Promise.all([loadKots(), canManageOrders ? loadOrders() : Promise.resolve()])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const readyKot = async (kot: KotRecord) => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: KotRecord }>(`/kots/${kot.id}/ready`, csrfToken, { method: 'POST' })
      setSuccess(`${kot.kot_number} marked as ready to serve.`)
      await Promise.all([loadKots(), canManageOrders ? loadOrders() : Promise.resolve()])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const serveKotItem = async (item: KotLineRecord) => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: KotRecord }>(`/kot-items/${item.id}/served`, csrfToken, { method: 'POST' })
      setSuccess(`${item.item_name} marked as served.`)
      await Promise.all([loadKots(), canManageOrders ? loadOrders() : Promise.resolve()])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const reprintKot = async (kot: KotRecord, reason: string): Promise<KotRecord | null> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: KotRecord }>(`/kots/${kot.id}/reprint`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      })
      setPrintKots((prev) => prev.map((k) => (k.id === kot.id ? result.data : k)))
      setSuccess(`Reprint logged for ${kot.kot_number}.`)
      await loadKots()
      return result.data
    } catch (caught) {
      handleFailure(caught)
      return null
    } finally {
      setBusy(false)
    }
  }

  const viewOrderKot = async (kotId: number) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: KotRecord[] }>('/kots?include_completed=1')
      const kot = result.data.find((candidate) => candidate.id === kotId)
      if (!kot) {
        throw new ApiFailure('The selected KOT is no longer available.', 404)
      }
      setPrintKots([kot])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const requestOrderBill = async (order: OrderRecord) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/request-bill`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ version: order.version }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Bill requested for ${result.data.order_number}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const transferOrder = async (order: OrderRecord, toTableId: number, reason: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/transfer`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ version: order.version, to_dining_table_id: toTableId, reason }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Transferred ${result.data.order_number} to ${result.data.table?.display_name}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const mergeOrder = async (order: OrderRecord, targetOrderId: number, reason: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/merge`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ version: order.version, target_order_id: targetOrderId, reason }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Merged into ${result.data.order_number}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const cancelOrder = async (order: OrderRecord, reason: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: OrderRecord }>(`/orders/${order.id}/cancel`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ version: order.version, reason }),
      })
      setSuccess(`Cancelled ${order.order_number}.`)
      await Promise.all([loadFloor(), loadOrders()])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const openShift = async (openingFloat: number, notes: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: CashierShiftRecord }>('/shifts/open', csrfToken, {
        method: 'POST',
        body: JSON.stringify({ opening_float: openingFloat, notes: notes || null }),
      })
      setCurrentShift(result.data)
      setSuccess('Cashier shift opened successfully.')
      await loadBillingData()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const closeShift = async (shiftId: number, countedCash: number, notes: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: CashierShiftRecord }>(`/shifts/${shiftId}/close`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ counted_cash: countedCash, notes: notes || null }),
      })
      setCurrentShift(null)
      setSuccess('Cashier shift closed and variance calculated.')
      await loadBillingData()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const approveShift = async (shiftId: number) => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: CashierShiftRecord }>(`/shifts/${shiftId}/approve`, csrfToken, {
        method: 'POST',
      })
      setSuccess('Shift variance approved.')
      await loadBillingData()
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const applyOrderDiscount = async (
    order: OrderRecord,
    type: 'PERCENTAGE' | 'FIXED',
    value: number,
    reason: string,
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/discount`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ version: order.version, type, value, reason }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Discount applied to ${result.data.order_number}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const removeOrderDiscount = async (order: OrderRecord) => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/discount`, csrfToken, {
        method: 'DELETE',
        body: JSON.stringify({ version: order.version }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Discount removed from ${result.data.order_number}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id)])
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const recordOrderPayment = async (
    order: OrderRecord,
    payload: {
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      amount: number
      tendered_amount?: number
      provider_reference?: string
    },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord }>(`/orders/${order.id}/payments`, csrfToken, {
        method: 'POST',
        headers: { 'Idempotency-Key': `pay-${order.id}-${Date.now()}` },
        body: JSON.stringify({ ...payload, version: order.version }),
      })
      setSelectedOrder(result.data)
      setSuccess(`Payment recorded for ${result.data.order_number}.`)
      await Promise.all([loadFloor(), loadOrders(result.data.id), loadBillingData()])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const checkoutOrder = async (
    order: OrderRecord,
    payload?: {
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      amount: number
      tendered_amount?: number
      provider_reference?: string
    },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: OrderRecord; invoice: InvoiceRecord }>(
        `/orders/${order.id}/checkout`,
        csrfToken,
        {
          method: 'POST',
          headers: { 'Idempotency-Key': `checkout-${order.id}-${Date.now()}` },
          body: JSON.stringify({ ...(payload ?? {}), version: order.version }),
        },
      )
      setPrintInvoice(result.invoice)
      setSuccess(`Checkout complete · ${result.invoice.invoice_number} issued.`)
      await Promise.all([loadFloor(), loadOrders(), loadBillingData()])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const reprintInvoice = async (invoice: InvoiceRecord, reason: string): Promise<InvoiceRecord | null> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: InvoiceRecord }>(`/invoices/${invoice.id}/reprint`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      })
      setPrintInvoice(result.data)
      setSuccess(`Reprint logged for ${invoice.invoice_number}.`)
      await Promise.all([
        canTakePayment ? loadBillingData() : Promise.resolve(),
        canViewReports ? loadReports(reportData?.range.from, reportData?.range.to) : Promise.resolve(),
      ])
      return result.data
    } catch (caught) {
      handleFailure(caught)
      return null
    } finally {
      setBusy(false)
    }
  }

  const voidInvoice = async (invoice: InvoiceRecord, reason: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request<{ data: InvoiceRecord }>(`/invoices/${invoice.id}/void`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      })
      setSuccess(`Invoice ${invoice.invoice_number} voided and logged.`)
      await Promise.all([
        canTakePayment ? loadBillingData() : Promise.resolve(),
        canViewReports ? loadReports(reportData?.range.from, reportData?.range.to) : Promise.resolve(),
        canViewReports ? loadAuditEvents() : Promise.resolve(),
      ])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const refundInvoice = async (
    invoice: InvoiceRecord,
    payload: {
      amount: number
      method: 'CASH' | 'CARD' | 'BKASH' | 'NAGAD'
      reason: string
      provider_reference?: string
    },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: InvoiceRecord; refund: { refund_number: string } }>(
        `/invoices/${invoice.id}/refunds`,
        csrfToken,
        {
          method: 'POST',
          headers: { 'Idempotency-Key': `refund-${invoice.id}-${Date.now()}` },
          body: JSON.stringify(payload),
        },
      )
      setSuccess(`Refund ${result.refund.refund_number} processed for ${invoice.invoice_number}.`)
      await Promise.all([
        canTakePayment ? loadBillingData() : Promise.resolve(),
        canViewReports ? loadReports(reportData?.range.from, reportData?.range.to) : Promise.resolve(),
        canViewReports ? loadAuditEvents() : Promise.resolve(),
      ])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const exportReportCsv = (from: string, to: string) => {
    const params = new URLSearchParams({ from, to })
    window.open(`${apiBase}/reports/export?${params.toString()}`, '_blank', 'noopener,noreferrer')
  }

  const saveInventoryItem = async (payload: Record<string, unknown>, id?: number): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(id ? `/inventory/items/${id}` : '/inventory/items', csrfToken, {
        method: id ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(id ? 'Stock item updated.' : 'Stock item added.')
      await loadInventory()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const adjustInventoryStock = async (
    itemId: number,
    payload: { type: 'WASTAGE' | 'ADJUSTMENT'; quantity_delta: number; reason: string },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(`/inventory/items/${itemId}/adjust`, csrfToken, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess('Stock adjustment recorded.')
      await Promise.all([loadInventory(), loadMenu(canManageMenu)])
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const saveSupplier = async (payload: Record<string, unknown>): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request('/inventory/suppliers', csrfToken, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess('Supplier added to directory.')
      await loadInventory()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const recordPurchaseOrder = async (payload: {
    supplier_id?: number | null
    reference_number?: string
    tax_amount?: number
    notes?: string
    items: { inventory_item_id: number; quantity: number; unit_cost: number }[]
  }): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      const result = await request<{ data: { po_number: string } }>('/inventory/purchases', csrfToken, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(`Purchase ${result.data.po_number} received into stock.`)
      await loadInventory()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const syncMenuItemRecipe = async (
    menuItemId: number,
    ingredients: { inventory_item_id: number; quantity: number }[],
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(`/inventory/recipes/${menuItemId}`, csrfToken, {
        method: 'PUT',
        body: JSON.stringify({ ingredients }),
      })
      setSuccess('Recipe Bill of Materials (BOM) updated.')
      await loadInventory()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const createStaffAccount = async (payload: {
    name: string
    email: string
    password: string
    role_key: string
  }): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request('/staff', csrfToken, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(`Staff account created for ${payload.name}.`)
      await loadStaff()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const updateStaffAccount = async (
    staffId: number,
    payload: { name: string; email: string; role_key: string },
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(`/staff/${staffId}`, csrfToken, {
        method: 'PATCH',
        body: JSON.stringify(payload),
      })
      setSuccess('Staff account updated.')
      await loadStaff()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const resetStaffPassword = async (
    staffId: number,
    newPassword: string,
    reason: string,
  ): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(`/staff/${staffId}/reset-password`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ password: newPassword, reason }),
      })
      setSuccess('Staff password reset and old sessions revoked.')
      await loadStaff()
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const toggleStaffActive = async (staff: StaffRecord, active: boolean) => {
    if (!active && !window.confirm(`Deactivate "${staff.name}" and revoke their active sessions?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/staff/${staff.id}/activation`, csrfToken, {
        method: active ? 'POST' : 'DELETE',
      })
      setSuccess(`${staff.name} ${active ? 'activated' : 'deactivated'}.`)
      await loadStaff()
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveMenuItem = async (payload: Record<string, unknown>, id?: number): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(id ? `/menu/items/${id}` : '/menu/items', csrfToken, {
        method: id ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(id ? 'Menu item updated successfully.' : 'Menu item added successfully.')
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const toggleMenuItemAvailability = async (item: MenuItemRecord, available: boolean) => {
    resetMessages()
    setBusy(true)
    try {
      await request(`/menu/items/${item.id}/availability`, csrfToken, {
        method: 'POST',
        body: JSON.stringify({ available }),
      })
      setSuccess(`${item.name} marked as ${available ? 'available' : 'sold out'}.`)
      await loadMenu(canManageMenu)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const toggleMenuItemActive = async (item: MenuItemRecord, active: boolean) => {
    if (!active && !window.confirm(`Deactivate "${item.name}"?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/menu/items/${item.id}/activation`, csrfToken, {
        method: active ? 'POST' : 'DELETE',
      })
      setSuccess(`${item.name} ${active ? 'activated' : 'deactivated'} successfully.`)
      await loadMenu(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveMenuCategory = async (draft: { id?: number; name: string; default_station: 'KITCHEN' | 'BAR'; sort_order: number }): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(draft.id ? `/menu/categories/${draft.id}` : '/menu/categories', csrfToken, {
        method: draft.id ? 'PATCH' : 'POST',
        body: JSON.stringify({ name: draft.name, default_station: draft.default_station, sort_order: draft.sort_order }),
      })
      setSuccess(draft.id ? 'Category updated successfully.' : 'Category added successfully.')
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const toggleMenuCategoryActive = async (category: MenuCategoryRecord, active: boolean) => {
    if (!active && !window.confirm(`Deactivate category "${category.name}"?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/menu/categories/${category.id}/activation`, csrfToken, {
        method: active ? 'POST' : 'DELETE',
      })
      setSuccess(`Category ${active ? 'activated' : 'deactivated'} successfully.`)
      await loadMenu(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveMenuSubcategory = async (payload: Record<string, unknown>, id?: number): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(id ? `/menu/subcategories/${id}` : '/menu/subcategories', csrfToken, {
        method: id ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(id ? 'Subcategory updated successfully.' : 'Subcategory added successfully.')
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const toggleMenuSubcategoryActive = async (id: number, name: string, active: boolean) => {
    if (!active && !window.confirm(`Deactivate subcategory "${name}"?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/menu/subcategories/${id}/activation`, csrfToken, { method: active ? 'POST' : 'DELETE' })
      setSuccess(`Subcategory ${active ? 'activated' : 'deactivated'} successfully.`)
      await loadMenu(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveModifierGroup = async (payload: Record<string, unknown>, id?: number): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request(id ? `/menu/modifier-groups/${id}` : '/menu/modifier-groups', csrfToken, {
        method: id ? 'PATCH' : 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(id ? 'Add-on group updated successfully.' : 'Add-on group added successfully.')
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const toggleModifierGroupActive = async (id: number, name: string, active: boolean) => {
    if (!active && !window.confirm(`Deactivate add-on group "${name}"?`)) return
    resetMessages()
    setBusy(true)
    try {
      await request(`/menu/modifier-groups/${id}/activation`, csrfToken, { method: active ? 'POST' : 'DELETE' })
      setSuccess(`Add-on group ${active ? 'activated' : 'deactivated'} successfully.`)
      await loadMenu(true)
    } catch (caught) {
      handleFailure(caught)
    } finally {
      setBusy(false)
    }
  }

  const saveTaxCategory = async (payload: { name: string; rate: number; inclusive: boolean }): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request('/menu/tax-categories', csrfToken, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      setSuccess(`VAT rule "${payload.name}" saved.`)
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  const applyBulkTaxMode = async (taxCategoryId: number | null, label: string): Promise<boolean> => {
    resetMessages()
    setBusy(true)
    try {
      await request('/menu/tax-mode', csrfToken, {
        method: 'POST',
        body: JSON.stringify({ tax_category_id: taxCategoryId }),
      })
      setSuccess(`Applied "${label}" to all menu items.`)
      await loadMenu(true)
      return true
    } catch (caught) {
      handleFailure(caught)
      return false
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <div className="app-loader"><span className="spinner" />Preparing your workspace…</div>

  if (!user) return <main className="login-shell">
    <section className="brand-panel">
      <div className="brand-logo-wrap">
        <img src="/clique-cafe-logo.jpg" alt="Clique Café Logo" className="brand-logo-hero" />
      </div>
      <p className="eyebrow">COFFEE • BISTRO • DESSERTS</p>
      <h1>Every table.<br />One clear view.</h1>
      <p className="brand-copy">Crafted for Clique Café — fast table service, espresso & kitchen routing, and seamless cashier checkout.</p>
      <div className="service-pills"><span>Coffee</span><span>Bistro</span><span>Desserts</span></div>
    </section>
    <section className="login-panel"><form className="login-card" onSubmit={login}><p className="eyebrow coral">CLIQUE CAFÉ · POS</p><h2>Sign in to your shift</h2><p className="muted">Use the local account created during setup.</p>{error && <Alert message={error} />}{success && <Success message={success} />}<label>Email<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" required /><FieldError errors={fieldErrors.email} /></label><label>Password<input type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required /><FieldError errors={fieldErrors.password} /></label><button className="primary" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button><p className="demo-note">Clique Café POS · Developed by Tech Base</p></form></section>
  </main>

  return <div className="dashboard-shell">
    <aside className="sidebar">
      <div className="logo-row">
        <img src="/clique-cafe-logo.jpg" alt="Clique Café" className="brand-logo-sidebar" />
        <div className="logo-text-stack">
          <strong>{user.restaurant.name || 'Clique Cafe'}</strong>
          <small className="logo-sub">COFFEE • BISTRO • DESSERTS</small>
        </div>
      </div>
      <nav>
        <button className={activeTab === 'floor' ? 'active' : ''} disabled={!canViewFloor} onClick={() => { resetMessages(); setActiveTab('floor') }}>
          <span>⌂</span> Floor
        </button>
        <button className={activeTab === 'orders' ? 'active' : ''} disabled={!canManageOrders} onClick={() => { resetMessages(); setActiveTab('orders') }}>
          <span>▤</span> Orders {orders.length > 0 && `(${orders.length})`}
        </button>
        <button className={activeTab === 'kitchen' ? 'active' : ''} disabled={!canViewKots} onClick={() => { resetMessages(); setActiveTab('kitchen'); void loadKots() }}>
          <span>♨</span> Kitchen / Bar {kots.filter((k) => k.status === 'PENDING' || k.status === 'PREPARING').length > 0 && `(${kots.filter((k) => k.status === 'PENDING' || k.status === 'PREPARING').length})`}
        </button>
        <button className={activeTab === 'cashier' ? 'active' : ''} disabled={!canTakePayment} onClick={() => { resetMessages(); setActiveTab('cashier'); void Promise.all([loadOrders(), loadBillingData()]) }}>
          <span>৳</span> Cashier {orders.filter((o) => o.status === 'BILL_REQUESTED' || o.status === 'PARTIALLY_PAID').length > 0 && `(${orders.filter((o) => o.status === 'BILL_REQUESTED' || o.status === 'PARTIALLY_PAID').length})`}
        </button>
        <button className={activeTab === 'menu' ? 'active' : ''} onClick={() => { resetMessages(); setActiveTab('menu') }}>
          <span>◫</span> Menu
        </button>
        <button className={activeTab === 'inventory' ? 'active' : ''} disabled={!canManageInventory} onClick={() => { resetMessages(); setActiveTab('inventory'); void loadInventory() }}>
          <span>📦</span> Inventory {inventoryData && (inventoryData.summary.low_stock_count + inventoryData.summary.out_of_stock_count) > 0 && `(${inventoryData.summary.low_stock_count + inventoryData.summary.out_of_stock_count})`}
        </button>
        <button className={activeTab === 'reports' ? 'active' : ''} disabled={!canViewReports} onClick={() => { resetMessages(); setActiveTab('reports'); void Promise.all([loadReports(reportData?.range.from, reportData?.range.to), loadAuditEvents()]) }}>
          <span>◒</span> Reports
        </button>
        <button className={activeTab === 'staff' ? 'active' : ''} disabled={!canManageStaff} onClick={() => { resetMessages(); setActiveTab('staff'); void loadStaff() }}>
          <span>👥</span> Staff
        </button>
        <button className="tablet-signout" aria-label="Sign out" disabled={busy} onClick={logout}><span>↪</span> Sign out</button>
      </nav>
      <div className="sidebar-user"><div className="avatar">{user.name.charAt(0)}</div><div><strong>{user.name}</strong><span>{user.roles.join(', ')}</span></div><button aria-label="Sign out" disabled={busy} onClick={logout}>↪</button></div>
    </aside>

    <main className="content">
      {error && <Alert message={error} dismiss={() => setError('')} />}
      {success && <Success message={success} dismiss={() => setSuccess('')} />}

      {activeTab === 'staff' ? (
        <StaffWorkspace
          restaurantName={user.restaurant.name}
          staffList={staffList}
          roleOptions={staffRoleOptions}
          busy={busy}
          onRefresh={loadStaff}
          onCreateStaff={createStaffAccount}
          onUpdateStaff={updateStaffAccount}
          onResetPassword={resetStaffPassword}
          onToggleActive={toggleStaffActive}
        />
      ) : activeTab === 'inventory' ? (
        <InventoryWorkspace
          restaurantName={user.restaurant.name}
          inventory={inventoryData}
          menuCategories={menuCategories}
          busy={busy}
          onRefresh={loadInventory}
          onSaveItem={saveInventoryItem}
          onAdjustStock={adjustInventoryStock}
          onSaveSupplier={saveSupplier}
          onRecordPurchase={recordPurchaseOrder}
          onSyncRecipe={syncMenuItemRecipe}
        />
      ) : activeTab === 'reports' ? (
        <ReportsWorkspace
          restaurantName={user.restaurant.name}
          report={reportData}
          auditEvents={auditEvents}
          canRefundOrVoid={canRefundOrVoid}
          busy={busy}
          onLoadReport={loadReports}
          onLoadAudit={loadAuditEvents}
          onExportCsv={exportReportCsv}
          onVoidInvoice={voidInvoice}
          onRefundInvoice={refundInvoice}
          onViewInvoice={(inv) => setPrintInvoice(inv)}
        />
      ) : activeTab === 'cashier' ? (
        <CashierWorkspace
          restaurantName={user.restaurant.name}
          orders={orders}
          selectedOrder={selectedOrder}
          currentShift={currentShift}
          recentShifts={recentShifts}
          recentInvoices={recentInvoices}
          canApproveShift={canManageFloor || canManageMenu}
          busy={busy}
          onSelectOrder={selectOrderById}
          onOpenShift={openShift}
          onCloseShift={closeShift}
          onApproveShift={approveShift}
          onApplyDiscount={applyOrderDiscount}
          onRemoveDiscount={removeOrderDiscount}
          onRecordPayment={recordOrderPayment}
          onCheckoutOrder={checkoutOrder}
          onViewInvoice={(inv) => setPrintInvoice(inv)}
          onPrintGuestBill={(ord) => setPrintInvoice(buildGuestBillFromOrder(ord))}
        />
      ) : activeTab === 'kitchen' ? (
        <KotWorkspace
          restaurantName={user.restaurant.name}
          kots={kots}
          allowedStations={kotStations}
          permissions={user.permissions}
          includeCompleted={includeCompletedKots}
          busy={busy}
          onToggleCompleted={toggleCompletedKots}
          onRefresh={() => loadKots()}
          onStartKot={startKot}
          onReadyKot={readyKot}
          onServeItem={serveKotItem}
          onOpenPrintModal={(kot) => setPrintKots([kot])}
        />
      ) : activeTab === 'menu' ? (
        <MenuWorkspace
          restaurantName={user.restaurant.name}
          categories={menuCategories}
          taxCategories={taxCategories}
          modifierGroups={modifierGroups}
          permissions={user.permissions}
          busy={busy}
          fieldErrors={fieldErrors}
          onClearErrors={resetMessages}
          onSaveItem={saveMenuItem}
          onToggleAvailability={toggleMenuItemAvailability}
          onToggleItemActive={toggleMenuItemActive}
          onSaveCategory={saveMenuCategory}
          onToggleCategoryActive={toggleMenuCategoryActive}
          onSaveSubcategory={saveMenuSubcategory}
          onToggleSubcategoryActive={toggleMenuSubcategoryActive}
          onSaveModifierGroup={saveModifierGroup}
          onToggleModifierGroupActive={toggleModifierGroupActive}
          onSaveTaxCategory={saveTaxCategory}
          onApplyBulkTaxMode={applyBulkTaxMode}
        />
      ) : activeTab === 'orders' ? (
        <>
          <header>
            <div>
              <p className="eyebrow coral">ORDER COMPOSER</p>
              <h1>Fast, accurate table service.</h1>
              <p className="muted">{user.restaurant.name} · Optimistic versioning & snapshot pricing</p>
            </div>
            <div className="header-actions">
              <span className="live"><i /> {orders.length} Open Checks</span>
              <button className="secondary compact" onClick={() => setActiveTab('floor')}>
                ← Back to floor
              </button>
            </div>
          </header>

          <OrderWorkspace
            orders={orders}
            selectedOrder={selectedOrder}
            tables={floorTableOptions}
            menuCategories={menuCategories}
            canCancelSentItems={canCancelSentItems}
            busy={busy}
            onSelectOrder={selectOrderById}
            onOpenTableOrder={openOrFocusTableOrder}
            onUpdateOrderMeta={updateOrderMeta}
            onAddItem={addOrderItem}
            onUpdateItem={updateOrderItem}
            onRemoveItem={removeOrderItem}
            onCancelSentItem={cancelSentOrderItem}
            onSendRound={sendOrderRound}
            onViewOrderKot={viewOrderKot}
            onRequestBill={requestOrderBill}
            onTransferOrder={transferOrder}
            onMergeOrder={mergeOrder}
            onCancelOrder={cancelOrder}
            onOpenCashier={canTakePayment ? () => { resetMessages(); setActiveTab('cashier'); void loadBillingData() } : undefined}
            onPrintGuestBill={(ord) => setPrintInvoice(buildGuestBillFromOrder(ord))}
          />
        </>
      ) : (
        <>
          <header><div><p className="eyebrow coral">FLOOR & TABLES</p><h1>Good service starts here.</h1><p className="muted">{user.restaurant.name} · Interactive dining room floor plan</p></div><div className="header-actions"><span className="live"><i /> Live Floor</span>{canManageFloor && <><button className="secondary compact" onClick={() => setShowZones(true)}>Manage zones</button><button className="primary compact" onClick={() => setTableDraft({ ...emptyTable, zone_id: String(zones.find((zone) => zone.active)?.id ?? '') })}>＋ Add table</button></>}</div></header>
          <section className="summary-grid"><Summary icon="✓" tone="green" value={tables.filter((table) => table.active && table.status === 'AVAILABLE').length} label="Available" /><Summary icon="●" tone="blue" value={tables.filter((table) => table.status === 'OPEN').length} label="Occupied" /><Summary icon="◷" tone="amber" value={tables.filter((table) => table.status === 'BILL_REQUESTED' || table.status === 'PARTIALLY_PAID').length} label="Bill / Payment" /><Summary icon="#" tone="ink" value={tables.length} label="Total tables" /></section>
          {zones.length === 0 ? <EmptyState title="No zones yet" copy="Add Indoor, Outdoor, or another service zone to begin." action={canManageFloor ? () => setShowZones(true) : undefined} /> : <>
            <div className="floor-plan-bar">
              <div className="zone-tabs" style={{ marginBottom: 0 }}><button className={selectedZone === 'all' ? 'active' : ''} onClick={() => setSelectedZone('all')}>All tables</button>{zones.map((zone) => <button key={zone.id} className={selectedZone === zone.id ? 'active' : ''} onClick={() => setSelectedZone(zone.id)}>{zone.name}{!zone.active && ' · Inactive'}<span>{zone.tables.length}</span></button>)}</div>
              <div className="floor-legend">
                <span><i className="legend-swatch swatch-available" /> Available</span>
                <span><i className="legend-swatch swatch-open" /> Occupied</span>
                <span><i className="legend-swatch swatch-bill" /> Bill Requested</span>
                <span><i className="legend-chair-icon" /> Filled Chair = Seated Guest</span>
              </div>
            </div>
            {visibleZones.map((zone) => <section className={`zone-section floor-canvas ${zone.active ? '' : 'inactive-zone'}`} key={zone.id}><div className="zone-title"><h2>{zone.name}</h2><span>{zone.active ? `${zone.tables.length} dining tables` : 'Inactive zone'}</span></div>{zone.tables.length === 0 ? <div className="inline-empty">No tables in this zone.</div> : <div className="table-grid floor-plan-grid">{zone.tables.map((table) => <FloorTableCard key={table.id} table={table} zoneId={zone.id} canManageOrders={canManageOrders} canManageFloor={canManageFloor} busy={busy} onOpenOrder={() => void openOrFocusTableOrder(table.id, Math.min(4, table.capacity))} onEdit={() => setTableDraft({ id: table.id, zone_id: String(zone.id), code: table.code, display_name: table.display_name, capacity: table.capacity })} onToggleActive={() => void setActivation('tables', table.id, !table.active)} />)}</div>}</section>)}
          </>}
        </>
      )}

      <footer className="app-workspace-footer no-print">
        <span>{user.restaurant.name || 'Clique Cafe'} · Point of Sale & Restaurant Management</span>
        <strong>Developed by Tech Base</strong>
      </footer>
    </main>
    {tableDraft && <TableModal draft={tableDraft} zones={zones.filter((zone) => zone.active)} errors={fieldErrors} busy={busy} onChange={setTableDraft} onClose={() => { setTableDraft(null); setFieldErrors({}) }} onSubmit={saveTable} />}
    {showZones && <ZoneManager zones={zones} draft={zoneDraft} errors={fieldErrors} busy={busy} onDraft={setZoneDraft} onClose={() => { setShowZones(false); setZoneDraft(null); setFieldErrors({}) }} onSubmit={saveZone} onActivation={(zone, active) => setActivation('zones', zone.id, active)} />}
    {printKots.length > 0 && <KotPrintModal kots={printKots} busy={busy} onClose={() => setPrintKots([])} onReprint={reprintKot} />}
    {printInvoice && <InvoicePrintModal restaurantName={user.restaurant.name} invoice={printInvoice} busy={busy} onClose={() => setPrintInvoice(null)} onReprint={reprintInvoice} />}
  </div>
}

function Summary({ icon, tone, value, label }: { icon: string; tone: string; value: number; label: string }) {
  return <article><span className={`summary-icon ${tone}`}>{icon}</span><div><strong>{value}</strong><p>{label}</p></div></article>
}

function FloorTableCard({
  table,
  canManageOrders,
  canManageFloor,
  busy,
  onOpenOrder,
  onEdit,
  onToggleActive,
}: {
  table: DiningTable
  zoneId: number
  canManageOrders: boolean
  canManageFloor: boolean
  busy: boolean
  onOpenOrder: () => void
  onEdit: () => void
  onToggleActive: () => void
}) {
  const visualSeats = Math.max(2, Math.min(8, table.capacity))
  const occupiedGuests = table.order ? Math.min(visualSeats, table.order.guest_count) : 0

  const hasSideChairs = visualSeats >= 6
  const sideCount = hasSideChairs ? 2 : 0
  const remaining = visualSeats - sideCount
  const topCount = Math.ceil(remaining / 2)
  const bottomCount = Math.floor(remaining / 2)

  let seatCursor = 0
  const nextSeatOccupied = () => {
    const isOccupied = seatCursor < occupiedGuests
    seatCursor += 1
    return isOccupied
  }

  const topChairs = Array.from({ length: topCount }, (_, i) => ({
    key: `top-${i}`,
    occupied: nextSeatOccupied(),
  }))
  const bottomChairs = Array.from({ length: bottomCount }, (_, i) => ({
    key: `bot-${i}`,
    occupied: nextSeatOccupied(),
  }))
  const leftOccupied = hasSideChairs ? nextSeatOccupied() : false
  const rightOccupied = hasSideChairs ? nextSeatOccupied() : false

  const shapeClass =
    table.capacity <= 2 ? 'shape-round' : table.capacity >= 6 ? 'shape-banquet' : 'shape-square'

  return (
    <article className={`table-card floor-table-unit status-${table.status.toLowerCase()} ${shapeClass}`}>
      <div className="dining-rig">
        <div className="chair-row chair-row-top" aria-hidden="true">
          {topChairs.map((c) => (
            <span key={c.key} className={`chair-seat chair-top ${c.occupied ? 'chair-occupied' : ''}`} />
          ))}
        </div>

        <div className="dining-middle-row">
          {hasSideChairs && (
            <span
              aria-hidden="true"
              className={`chair-seat chair-left ${leftOccupied ? 'chair-occupied' : ''}`}
            />
          )}

          <div
            className="dining-tabletop"
            onClick={() => {
              if (canManageOrders && table.active && !busy) {
                onOpenOrder()
              }
            }}
          >
            <div className="table-card-top">
              <span className="table-code">{table.code}</span>
              <span className="status-dot" />
            </div>

            <div className="tabletop-center">
              <h3>{table.display_name}</h3>
              <p>{table.capacity} seats</p>
            </div>

            <div className="table-status">
              <strong>{table.status.replace('_', ' ')}</strong>
              {table.order ? (
                <span>
                  {table.order.guest_count} guests · {table.order.order_number}
                  {table.order.total ? ` · ৳${Number(table.order.total).toFixed(0)}` : ''}
                </span>
              ) : (
                <span>Ready to seat</span>
              )}
            </div>
          </div>

          {hasSideChairs && (
            <span
              aria-hidden="true"
              className={`chair-seat chair-right ${rightOccupied ? 'chair-occupied' : ''}`}
            />
          )}
        </div>

        <div className="chair-row chair-row-bottom" aria-hidden="true">
          {bottomChairs.map((c) => (
            <span key={c.key} className={`chair-seat chair-bottom ${c.occupied ? 'chair-occupied' : ''}`} />
          ))}
        </div>
      </div>

      <div className="floor-table-footer">
        {canManageOrders && table.active && (
          <button className="table-order-cta" disabled={busy} onClick={onOpenOrder}>
            {table.order ? `Open check · ${table.order.order_number}` : '＋ Start order'}
          </button>
        )}
        {canManageFloor && (
          <div className="card-actions">
            <button onClick={onEdit}>Edit</button>
            <button
              className={table.active ? 'danger-link' : 'activate-link'}
              disabled={busy}
              onClick={onToggleActive}
            >
              {table.active ? 'Deactivate' : 'Activate'}
            </button>
          </div>
        )}
      </div>
    </article>
  )
}

function Alert({ message, dismiss }: { message: string; dismiss?: () => void }) {
  return <div className="alert" role="alert"><span>{message}</span>{dismiss && <button onClick={dismiss} aria-label="Dismiss error">×</button>}</div>
}

function Success({ message, dismiss }: { message: string; dismiss?: () => void }) {
  return <div className="success-message" role="status"><span>✓ {message}</span>{dismiss && <button onClick={dismiss} aria-label="Dismiss message">×</button>}</div>
}

function FieldError({ errors }: { errors?: string[] }) {
  return errors?.length ? <span className="field-error">{errors[0]}</span> : null
}

function EmptyState({ title, copy, action }: { title: string; copy: string; action?: () => void }) {
  return <section className="empty-state"><div>□</div><h2>{title}</h2><p>{copy}</p>{action && <button className="primary compact" onClick={action}>Manage zones</button>}</section>
}

function TableModal({ draft, zones, errors, busy, onChange, onClose, onSubmit }: { draft: TableDraft; zones: Zone[]; errors: Record<string, string[]>; busy: boolean; onChange: (draft: TableDraft) => void; onClose: () => void; onSubmit: (event: FormEvent) => void }) {
  return <div className="modal-backdrop" onMouseDown={onClose}><form className="modal" onSubmit={onSubmit} onMouseDown={(event) => event.stopPropagation()}><div className="modal-head"><div><p className="eyebrow coral">FLOOR SETUP</p><h2>{draft.id ? 'Edit table' : 'Add a table'}</h2></div><button type="button" className="icon-button" onClick={onClose}>×</button></div><label>Zone<select required value={draft.zone_id} onChange={(event) => onChange({ ...draft, zone_id: event.target.value })}><option value="">Choose a zone</option>{zones.map((zone) => <option value={zone.id} key={zone.id}>{zone.name}</option>)}</select><FieldError errors={errors.zone_id} /></label><div className="field-row"><label>Table code<input required placeholder="T16" value={draft.code} onChange={(event) => onChange({ ...draft, code: event.target.value.toUpperCase() })} /><FieldError errors={errors.code} /></label><label>Seats<input required type="number" min="1" max="100" value={draft.capacity} onChange={(event) => onChange({ ...draft, capacity: Number(event.target.value) })} /><FieldError errors={errors.capacity} /></label></div><label>Display name<input required placeholder="Table 16" value={draft.display_name} onChange={(event) => onChange({ ...draft, display_name: event.target.value })} /><FieldError errors={errors.display_name} /></label><div className="modal-actions"><button type="button" className="secondary" onClick={onClose}>Cancel</button><button className="primary" disabled={busy}>{busy ? 'Saving…' : draft.id ? 'Save changes' : 'Add table'}</button></div></form></div>
}

function ZoneManager({ zones, draft, errors, busy, onDraft, onClose, onSubmit, onActivation }: { zones: Zone[]; draft: ZoneDraft | null; errors: Record<string, string[]>; busy: boolean; onDraft: (draft: ZoneDraft | null) => void; onClose: () => void; onSubmit: (event: FormEvent) => void; onActivation: (zone: Zone, active: boolean) => void }) {
  return <div className="modal-backdrop" onMouseDown={onClose}><section className="modal zone-modal" onMouseDown={(event) => event.stopPropagation()}><div className="modal-head"><div><p className="eyebrow coral">FLOOR SETUP</p><h2>Manage zones</h2></div><button type="button" className="icon-button" onClick={onClose}>×</button></div>{draft ? <form onSubmit={onSubmit}><label>Zone name<input autoFocus required value={draft.name} onChange={(event) => onDraft({ ...draft, name: event.target.value })} /><FieldError errors={errors.name} /></label><div className="modal-actions"><button type="button" className="secondary" onClick={() => onDraft(null)}>Cancel</button><button className="primary" disabled={busy}>{busy ? 'Saving…' : draft.id ? 'Save zone' : 'Add zone'}</button></div></form> : <><button className="primary compact zone-add" onClick={() => onDraft({ name: '' })}>＋ Add zone</button><div className="zone-list">{zones.length === 0 ? <div className="inline-empty">No zones have been added.</div> : zones.map((zone) => <div className="zone-row" key={zone.id}><div><strong>{zone.name}</strong><span>{zone.active ? `${zone.tables.length} tables` : 'Inactive'}</span></div><div><button onClick={() => onDraft({ id: zone.id, name: zone.name })}>Edit</button><button className={zone.active ? 'danger-link' : 'activate-link'} disabled={busy} onClick={() => onActivation(zone, !zone.active)}>{zone.active ? 'Deactivate' : 'Activate'}</button></div></div>)}</div></>}</section></div>
}
