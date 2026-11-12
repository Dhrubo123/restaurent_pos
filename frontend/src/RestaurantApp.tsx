import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import './restaurant.css'

type User = { name: string; roles: string[]; permissions: string[]; restaurant: { name: string } }
type Table = {
  id: number; code: string; display_name: string; capacity: number; active: boolean
  status: 'AVAILABLE' | 'OPEN' | 'BILL_REQUESTED' | 'PARTIALLY_PAID' | 'INACTIVE'
  order: null | { order_number: string; guest_count: number; opened_at: string }
}
type Zone = { id: number; name: string; tables: Table[] }
type ApiError = { message?: string; errors?: Record<string, string[]> }

async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`/api/v1${path}`, {
    credentials: 'include',
    ...options,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
  })
  if (!response.ok) {
    const body = await response.json().catch(() => ({})) as ApiError
    throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'Something went wrong. Please try again.')
  }
  return response.json()
}

export default function RestaurantApp() {
  const [user, setUser] = useState<User | null>(null)
  const [zones, setZones] = useState<Zone[]>([])
  const [selectedZone, setSelectedZone] = useState<number | 'all'>('all')
  const [email, setEmail] = useState('admin@restaurant.test')
  const [password, setPassword] = useState('')
  const [csrf, setCsrf] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [newTable, setNewTable] = useState({ zone_id: '', code: '', display_name: '', capacity: 4 })

  const loadFloor = async () => {
    const result = await api<{ data: Zone[] }>('/floor')
    setZones(result.data)
  }

  useEffect(() => {
    void (async () => {
      try {
        const token = await api<{ token: string }>('/csrf-token')
        setCsrf(token.token)
        const result = await api<{ data: User }>('/me')
        setUser(result.data)
        await loadFloor()
      } catch {
        setUser(null)
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  const visibleZones = useMemo(() => selectedZone === 'all' ? zones : zones.filter((zone) => zone.id === selectedZone), [zones, selectedZone])
  const tables = useMemo(() => zones.flatMap((zone) => zone.tables), [zones])

  const login = async (event: FormEvent) => {
    event.preventDefault(); setLoading(true); setError('')
    try {
      const result = await api<{ data: User }>('/auth/login', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ email, password }) })
      setUser(result.data); await loadFloor()
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Unable to sign in.') }
    finally { setLoading(false) }
  }

  const addTable = async (event: FormEvent) => {
    event.preventDefault(); setLoading(true); setError('')
    try {
      await api('/tables', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ ...newTable, zone_id: Number(newTable.zone_id) }) })
      setShowAdd(false); setNewTable({ zone_id: '', code: '', display_name: '', capacity: 4 }); await loadFloor()
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Unable to add table.') }
    finally { setLoading(false) }
  }

  const logout = async () => {
    await api('/auth/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
    setUser(null); setZones([])
  }

  if (loading && !user) return <div className="app-loader"><span className="spinner" />Preparing your workspace…</div>

  if (!user) return <main className="login-shell">
    <section className="brand-panel"><div className="brand-mark">R</div><p className="eyebrow">RESTAURANT OPERATIONS</p><h1>Every table.<br />One clear view.</h1><p className="brand-copy">A fast, focused point of sale built for busy service.</p><div className="service-pills"><span>Floor</span><span>Kitchen</span><span>Cashier</span></div></section>
    <section className="login-panel"><form className="login-card" onSubmit={login}><p className="eyebrow coral">WELCOME BACK</p><h2>Sign in to your shift</h2><p className="muted">Use your staff account to continue.</p>{error && <div className="alert" role="alert">{error}</div>}<label>Email<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username" required /></label><label>Password<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required /></label><button className="primary" disabled={loading}>{loading ? 'Signing in…' : 'Sign in'}</button></form></section>
  </main>

  return <div className="dashboard-shell">
    <aside className="sidebar"><div className="logo-row"><div className="brand-mark small">R</div><strong>RestoFlow</strong></div><nav><button className="active"><span>⌂</span> Floor</button><button disabled><span>▤</span> Orders</button><button disabled><span>◫</span> Menu</button><button disabled><span>◒</span> Reports</button></nav><div className="sidebar-user"><div className="avatar">{user.name.charAt(0)}</div><div><strong>{user.name}</strong><span>{user.roles.join(', ')}</span></div><button aria-label="Sign out" onClick={logout}>↪</button></div></aside>
    <main className="content"><header><div><p className="eyebrow coral">LIVE FLOOR</p><h1>Good service starts here.</h1><p className="muted">{user.restaurant.name} · Server-authoritative table status</p></div><div className="header-actions"><span className="live"><i /> Live</span>{user.permissions.includes('manage-floor') && <button className="primary compact" onClick={() => setShowAdd(true)}>＋ Add table</button>}</div></header>
      {error && <div className="alert" role="alert">{error}<button onClick={() => setError('')}>×</button></div>}
      <section className="summary-grid"><Summary icon="✓" tone="green" value={tables.filter((t) => t.status === 'AVAILABLE').length} label="Available" /><Summary icon="●" tone="blue" value={tables.filter((t) => t.status === 'OPEN').length} label="Occupied" /><Summary icon="◷" tone="amber" value={tables.filter((t) => t.status === 'BILL_REQUESTED').length} label="Bill requested" /><Summary icon="#" tone="ink" value={tables.length} label="Total tables" /></section>
      <div className="zone-tabs"><button className={selectedZone === 'all' ? 'active' : ''} onClick={() => setSelectedZone('all')}>All tables</button>{zones.map((zone) => <button key={zone.id} className={selectedZone === zone.id ? 'active' : ''} onClick={() => setSelectedZone(zone.id)}>{zone.name}<span>{zone.tables.length}</span></button>)}</div>
      {visibleZones.map((zone) => <section className="zone-section" key={zone.id}><div className="zone-title"><h2>{zone.name}</h2><span>{zone.tables.length} tables</span></div><div className="table-grid">{zone.tables.map((table) => <button className={`table-card status-${table.status.toLowerCase()}`} key={table.id}><div className="table-card-top"><span className="table-code">{table.code}</span><span className="status-dot" /></div><h3>{table.display_name}</h3><p>{table.capacity} seats</p><div className="table-status"><strong>{table.status.replace('_', ' ')}</strong>{table.order && <span>{table.order.guest_count} guests · {table.order.order_number}</span>}</div></button>)}</div></section>)}
    </main>
    {showAdd && <div className="modal-backdrop" onMouseDown={() => setShowAdd(false)}><form className="modal" onSubmit={addTable} onMouseDown={(e) => e.stopPropagation()}><div className="modal-head"><div><p className="eyebrow coral">FLOOR SETUP</p><h2>Add a table</h2></div><button type="button" className="icon-button" onClick={() => setShowAdd(false)}>×</button></div><label>Zone<select required value={newTable.zone_id} onChange={(e) => setNewTable({ ...newTable, zone_id: e.target.value })}><option value="">Choose a zone</option>{zones.map((zone) => <option value={zone.id} key={zone.id}>{zone.name}</option>)}</select></label><div className="field-row"><label>Table code<input required placeholder="T16" value={newTable.code} onChange={(e) => setNewTable({ ...newTable, code: e.target.value.toUpperCase() })} /></label><label>Seats<input required type="number" min="1" max="100" value={newTable.capacity} onChange={(e) => setNewTable({ ...newTable, capacity: Number(e.target.value) })} /></label></div><label>Display name<input required placeholder="Table 16" value={newTable.display_name} onChange={(e) => setNewTable({ ...newTable, display_name: e.target.value })} /></label><div className="modal-actions"><button type="button" className="secondary" onClick={() => setShowAdd(false)}>Cancel</button><button className="primary" disabled={loading}>{loading ? 'Saving…' : 'Add table'}</button></div></form></div>}
  </div>
}

function Summary({ icon, tone, value, label }: { icon: string; tone: string; value: number; label: string }) {
  return <article><span className={`summary-icon ${tone}`}>{icon}</span><div><strong>{value}</strong><p>{label}</p></div></article>
}
