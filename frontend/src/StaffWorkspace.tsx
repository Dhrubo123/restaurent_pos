import { useState } from 'react'
import type { FormEvent } from 'react'

export type StaffRoleOption = {
  id: number
  key: string
  name: string
}

export type StaffRecord = {
  id: number
  name: string
  email: string
  active: boolean
  roles: { key: string; name: string }[]
  created_at: string | null
}

type Props = {
  restaurantName: string
  staffList: StaffRecord[]
  roleOptions: StaffRoleOption[]
  busy: boolean
  onRefresh: () => Promise<void>
  onCreateStaff: (payload: {
    name: string
    email: string
    password: string
    role_key: string
  }) => Promise<boolean>
  onUpdateStaff: (
    staffId: number,
    payload: { name: string; email: string; role_key: string },
  ) => Promise<boolean>
  onResetPassword: (staffId: number, password: string, reason: string) => Promise<boolean>
  onToggleActive: (staff: StaffRecord, active: boolean) => Promise<void>
}

export default function StaffWorkspace({
  restaurantName,
  staffList,
  roleOptions,
  busy,
  onRefresh,
  onCreateStaff,
  onUpdateStaff,
  onResetPassword,
  onToggleActive,
}: Props) {
  const [createModal, setCreateModal] = useState<{
    name: string
    email: string
    password: string
    role_key: string
  } | null>(null)

  const [editModal, setEditModal] = useState<{
    staff: StaffRecord
    name: string
    email: string
    role_key: string
  } | null>(null)

  const [passwordModal, setPasswordModal] = useState<{
    staff: StaffRecord
    password: string
    reason: string
  } | null>(null)

  const submitCreate = async (event: FormEvent) => {
    event.preventDefault()
    if (!createModal) return
    const ok = await onCreateStaff(createModal)
    if (ok) setCreateModal(null)
  }

  const submitEdit = async (event: FormEvent) => {
    event.preventDefault()
    if (!editModal) return
    const ok = await onUpdateStaff(editModal.staff.id, {
      name: editModal.name,
      email: editModal.email,
      role_key: editModal.role_key,
    })
    if (ok) setEditModal(null)
  }

  const submitPassword = async (event: FormEvent) => {
    event.preventDefault()
    if (!passwordModal) return
    const ok = await onResetPassword(
      passwordModal.staff.id,
      passwordModal.password,
      passwordModal.reason,
    )
    if (ok) setPasswordModal(null)
  }

  const activeCount = staffList.filter((s) => s.active).length
  const frontOfHouseCount = staffList.filter((s) =>
    s.roles.some((r) => r.key === 'waiter' || r.key === 'cashier'),
  ).length
  const stationCount = staffList.filter((s) =>
    s.roles.some((r) => r.key === 'kitchen' || r.key === 'bar'),
  ).length

  return (
    <div className="reports-workspace">
      <header>
        <div>
          <p className="eyebrow coral">STAFF & ROLE ACCESS CONTROL</p>
          <h1>Manage shift logins & role permissions.</h1>
          <p className="muted">
            {restaurantName} · Role-scoped accounts for Waiters, Cashiers, Kitchen, Bar & Managers
          </p>
        </div>
        <div className="header-actions">
          <button
            type="button"
            className="primary compact"
            disabled={busy}
            onClick={() =>
              setCreateModal({
                name: '',
                email: '',
                password: '',
                role_key: roleOptions.find((r) => r.key === 'waiter')?.key ?? roleOptions[0]?.key ?? 'waiter',
              })
            }
          >
            ＋ Add Staff Account
          </button>
          <button type="button" className="secondary compact" disabled={busy} onClick={() => void onRefresh()}>
            ↻
          </button>
        </div>
      </header>

      <section className="summary-grid">
        <article>
          <span className="summary-icon green">✓</span>
          <div>
            <strong>{activeCount}</strong>
            <p>Active Staff Accounts</p>
          </div>
        </article>
        <article>
          <span className="summary-icon blue">▤</span>
          <div>
            <strong>{frontOfHouseCount}</strong>
            <p>Waiters & Cashiers</p>
          </div>
        </article>
        <article>
          <span className="summary-icon amber">♨</span>
          <div>
            <strong>{stationCount}</strong>
            <p>Kitchen & Bar Staff</p>
          </div>
        </article>
        <article>
          <span className="summary-icon ink">#</span>
          <div>
            <strong>{staffList.length}</strong>
            <p>Total Accounts</p>
          </div>
        </article>
      </section>

      <section className="zone-section">
        <div className="zone-title">
          <h2>Restaurant Staff Directory</h2>
          <span>Deactivating an account immediately revokes active sessions</span>
        </div>

        <div className="reports-table-wrap">
          <table className="reports-table">
            <thead>
              <tr>
                <th>Staff Name</th>
                <th>Login Email</th>
                <th>Assigned Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {staffList.map((staff) => {
                const roleKey = staff.roles[0]?.key ?? 'waiter'
                const roleName = staff.roles.map((r) => r.name).join(', ') || 'Staff'
                return (
                  <tr key={staff.id} className={!staff.active ? 'voided-invoice-row' : ''}>
                    <td>
                      <strong>{staff.name}</strong>
                    </td>
                    <td>{staff.email}</td>
                    <td>
                      <span className="sku-tag">{roleName.toUpperCase()}</span>
                    </td>
                    <td>
                      <span
                        className={`invoice-status-pill ${
                          staff.active ? 'status-issued' : 'status-voided'
                        }`}
                      >
                        {staff.active ? 'ACTIVE' : 'INACTIVE'}
                      </span>
                    </td>
                    <td>
                      <div className="invoice-row-actions">
                        <button
                          type="button"
                          className="secondary compact"
                          disabled={busy}
                          onClick={() =>
                            setEditModal({
                              staff,
                              name: staff.name,
                              email: staff.email,
                              role_key: roleKey,
                            })
                          }
                        >
                          Edit Role / Info
                        </button>
                        <button
                          type="button"
                          className="secondary compact"
                          disabled={busy}
                          onClick={() =>
                            setPasswordModal({
                              staff,
                              password: '',
                              reason: '',
                            })
                          }
                        >
                          🔑 Reset Password
                        </button>
                        <button
                          type="button"
                          className={`secondary compact ${
                            staff.active ? 'danger-link' : 'activate-link'
                          }`}
                          disabled={busy}
                          onClick={() => void onToggleActive(staff, !staff.active)}
                        >
                          {staff.active ? 'Deactivate' : 'Activate'}
                        </button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </section>

      {createModal && (
        <div className="modal-backdrop" onMouseDown={() => setCreateModal(null)}>
          <form className="modal" onSubmit={submitCreate} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">NEW STAFF LOGIN</p>
                <h2>Create staff account</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setCreateModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Full Name
                <input
                  required
                  placeholder="e.g. Tanvir Hasan"
                  value={createModal.name}
                  onChange={(e) => setCreateModal({ ...createModal, name: e.target.value })}
                />
              </label>
              <label>
                Role
                <select
                  value={createModal.role_key}
                  onChange={(e) => setCreateModal({ ...createModal, role_key: e.target.value })}
                >
                  {roleOptions.map((r) => (
                    <option key={r.key} value={r.key}>
                      {r.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <label>
              Login Email
              <input
                required
                type="email"
                placeholder="waiter1@restaurant.local"
                value={createModal.email}
                onChange={(e) => setCreateModal({ ...createModal, email: e.target.value })}
              />
            </label>

            <label>
              Initial Password (minimum 8 characters)
              <input
                required
                type="password"
                minLength={8}
                placeholder="At least 8 characters"
                value={createModal.password}
                onChange={(e) => setCreateModal({ ...createModal, password: e.target.value })}
              />
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setCreateModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Creating…' : 'Create Staff Account'}
              </button>
            </div>
          </form>
        </div>
      )}

      {editModal && (
        <div className="modal-backdrop" onMouseDown={() => setEditModal(null)}>
          <form className="modal" onSubmit={submitEdit} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">EDIT STAFF PROFILE</p>
                <h2>{editModal.staff.name}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setEditModal(null)}>
                ×
              </button>
            </div>

            <div className="field-row">
              <label>
                Full Name
                <input
                  required
                  value={editModal.name}
                  onChange={(e) => setEditModal({ ...editModal, name: e.target.value })}
                />
              </label>
              <label>
                Assigned Role
                <select
                  value={editModal.role_key}
                  onChange={(e) => setEditModal({ ...editModal, role_key: e.target.value })}
                >
                  {roleOptions.map((r) => (
                    <option key={r.key} value={r.key}>
                      {r.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <label>
              Login Email
              <input
                required
                type="email"
                value={editModal.email}
                onChange={(e) => setEditModal({ ...editModal, email: e.target.value })}
              />
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setEditModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving…' : 'Save Changes'}
              </button>
            </div>
          </form>
        </div>
      )}

      {passwordModal && (
        <div className="modal-backdrop" onMouseDown={() => setPasswordModal(null)}>
          <form className="modal" onSubmit={submitPassword} onMouseDown={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <p className="eyebrow coral">ROTATE STAFF PASSWORD</p>
                <h2>Reset password for {passwordModal.staff.name}</h2>
              </div>
              <button type="button" className="icon-button" onClick={() => setPasswordModal(null)}>
                ×
              </button>
            </div>

            <label>
              New Password (minimum 8 characters)
              <input
                autoFocus
                required
                type="password"
                minLength={8}
                placeholder="Enter new password"
                value={passwordModal.password}
                onChange={(e) =>
                  setPasswordModal({ ...passwordModal, password: e.target.value })
                }
              />
            </label>

            <label>
              Mandatory reason for password reset (logged in audit trail)
              <input
                required
                minLength={3}
                placeholder="e.g. Staff member forgot shift password"
                value={passwordModal.reason}
                onChange={(e) => setPasswordModal({ ...passwordModal, reason: e.target.value })}
              />
            </label>

            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setPasswordModal(null)}>
                Cancel
              </button>
              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Resetting…' : 'Reset Password & Revoke Old Sessions'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
