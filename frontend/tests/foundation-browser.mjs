import { chromium, expect } from '@playwright/test'
import { randomBytes } from 'node:crypto'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

// A fresh account is created with a memory-only secret and disabled after the test.
const backend = fileURLToPath(new URL('../../backend/', import.meta.url))
const suffix = Date.now().toString()
const email = `foundation-qa-${suffix}@local.test`
const password = `Aa1!${randomBytes(24).toString('base64')}`
execFileSync('php', ['artisan', 'pos:create-manager', '--name=Foundation QA', `--email=${email}`, '--password-env=POS_QA_PASSWORD'], {
  cwd: backend, env: { ...process.env, POS_QA_PASSWORD: password }, stdio: 'pipe',
})
let browser
try {
  browser = await chromium.launch({ channel: 'chrome', headless: true })
  const page = await browser.newPage({ viewport: { width: 900, height: 1100 } })
  await page.goto('http://localhost:5173')
  await page.getByLabel('Email', { exact: true }).fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
  await expect(page.locator('header').getByRole('button', { name: 'Manage zones', exact: true })).toBeVisible()
  await expect(page.locator('article.table-card').first()).toBeVisible()
  console.log('PASS login with cookie session')
  await page.getByRole('button', { name: '＋ Add table', exact: true }).click()
  await page.getByLabel('Table code').fill(`QA${suffix}`)
  await page.getByLabel('Display name').fill(`QA ${suffix}`)
  await page.getByLabel('Seats').fill('4')
  await page.getByRole('button', { name: 'Add table', exact: true }).click()
  const card = page.locator('article.table-card').filter({ has: page.getByRole('heading', { name: `QA ${suffix}`, exact: true }) })
  await expect(card).toBeVisible()
  await page.reload()
  await expect(card).toBeVisible()
  console.log('PASS add table and authenticated refresh persistence')
  await card.getByRole('button', { name: 'Edit', exact: true }).click()
  await page.getByLabel('Display name').fill(`QA verified ${suffix}`)
  await page.getByLabel('Seats').fill('6')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  const edited = page.locator('article.table-card').filter({ has: page.getByRole('heading', { name: `QA verified ${suffix}`, exact: true }) })
  await expect(edited).toContainText('6 seats')
  page.on('dialog', dialog => dialog.accept())
  await edited.getByRole('button', { name: 'Deactivate', exact: true }).click()
  await expect(edited).toContainText('INACTIVE')
  await edited.getByRole('button', { name: 'Activate', exact: true }).click()
  await expect(edited).toContainText('AVAILABLE')
  console.log('PASS edit, deactivate, reactivate')
  await page.screenshot({ path: '../.cache/foundation-floor.png', fullPage: true })
  // Leave the clearly labeled QA table inactive, retaining its audit trail.
  await edited.getByRole('button', { name: 'Deactivate', exact: true }).click()
  await expect(edited).toContainText('INACTIVE')
  const missingCsrfStatus = await page.request.post('http://localhost:5173/api/v1/tables', { data: {} })
  expect(missingCsrfStatus.status()).toBe(419)
  console.log('PASS real CSRF rejection without a token')
  await page.getByRole('button', { name: 'Sign out', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible()
  const loggedOut = await page.request.get('http://localhost:5173/api/v1/me')
  expect(loggedOut.status()).toBe(401)
  await page.getByLabel('Email', { exact: true }).fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
  await expect(page.locator('header').getByRole('button', { name: 'Manage zones', exact: true })).toBeVisible()
  await expect(page.locator('article.table-card').first()).toBeVisible()
  await page.getByRole('button', { name: 'Sign out', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible()
  console.log('PASS logout, protected request denied, fresh-CSRF login again, final logout')
} finally {
  await browser?.close()
  execFileSync('php', ['artisan', 'tinker', '--execute', `$user = App\\Models\\User::where("email", "${email}")->firstOrFail(); $user->update(["active" => false, "password" => Illuminate\\Support\\Str::password(48)]); Illuminate\\Support\\Facades\\DB::table("sessions")->where("user_id", $user->id)->delete();`], { cwd: backend, stdio: 'pipe' })
}
