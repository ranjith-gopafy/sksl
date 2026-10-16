const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

/**
 * Admin actions driven through the real screens: cancelling a booking from the bookings list (with the
 * confirmation prompt), closing and re-opening a date, and creating a combo package with the form.
 * Throwaway admin, customer and booking come from tests/support/*.php; everything is removed afterwards.
 */

const adminHelper = path.join(__dirname, '..', 'support', 'admin_session.php');
const custHelper = path.join(__dirname, '..', 'support', 'customer_session.php');
const run = (file, ...args) => execFileSync('php', [file, ...args], { encoding: 'utf8' }).trim();

let admin, cust, ref;
const pkgName = 'E2E Package ' + Math.random().toString(36).slice(2, 8);
const closeDate = (() => { const d = new Date(Date.now() + 400 * 86400000); return d.toISOString().slice(0, 10); })();

test.beforeAll(() => {
  admin = JSON.parse(run(adminHelper, 'create'));
  cust = JSON.parse(run(custHelper, 'create'));
  ref = JSON.parse(run(custHelper, 'booking', String(cust.id), '60', 'paid')).ref;
});
test.afterAll(() => {
  if (admin) { run(adminHelper, 'cleanup', String(admin.id)); }
  if (cust) { run(custHelper, 'cleanup', String(cust.id)); }
  run(adminHelper, 'remove-package', pkgName);
  run(adminHelper, 'remove-closed-date', closeDate);
});

async function signIn(page) {
  run(adminHelper, 'reset-limits');
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', admin.email);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/verify-otp/);
  run(adminHelper, 'otp', String(admin.id));
  await page.fill('input[name="otp"]', '246810');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL(/admin\/dashboard/, { timeout: 15000 });
}

test.describe.serial('Admin actions through the screens', () => {
  test('cancelling a paid booking asks first, then shows the refund due', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/bookings?status=all&search=' + ref, { waitUntil: 'load' });
    const row = page.locator('tr', { hasText: ref });
    await expect(row.first()).toBeVisible();
    await row.first().locator('[data-action="toggleActionMenu"]').click();

    // "Keep it" answer: nothing changes
    page.once('dialog', (d) => { expect(d.message()).toContain(ref); expect(d.message().toLowerCase()).toContain('refund'); d.dismiss(); });
    await row.first().getByRole('button', { name: /cancel booking/i }).click();
    await page.waitForTimeout(500);
    expect(JSON.parse(run(custHelper, 'status', ref)).booking_status).toBe('confirmed');

    // "Yes" answer
    await row.first().locator('[data-action="toggleActionMenu"]').click().catch(() => {});
    page.once('dialog', (d) => d.accept());
    await Promise.all([page.waitForNavigation(), row.first().getByRole('button', { name: /cancel booking/i }).click()]);
    const st = JSON.parse(run(custHelper, 'status', ref));
    expect(st.booking_status).toBe('cancelled');
    expect(st.cancelled_by).toBe('admin');
    expect(Number(st.refund_amount)).toBe(599);

    await page.goto('/admin/bookings?status=cancelled&search=' + ref, { waitUntil: 'load' });
    await expect(page.getByTestId('admin-refund-due').first()).toContainText('599.00');
  });

  test('a date can be closed and re-opened from the Closed Dates page', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/closed-dates', { waitUntil: 'load' });
    await page.evaluate((d) => { const el = document.getElementById('closed_date'); el.value = d; el.dispatchEvent(new Event('change', { bubbles: true })); }, closeDate);
    await page.fill('#reason', 'E2E closure');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: /block date/i }).click()]);
    const row = page.locator('tr', { hasText: 'E2E closure' });
    await expect(row.first()).toBeVisible();
    page.once('dialog', (d) => d.accept());
    await Promise.all([page.waitForNavigation(), row.first().getByRole('button', { name: /re-open/i }).click()]);
    await expect(page.locator('tr', { hasText: 'E2E closure' })).toHaveCount(0);
  });

  test('the combo package form builds a package from its item rows', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/packages', { waitUntil: 'load' });
    await page.fill('#f_name_1', pkgName);
    await page.fill('#f_price_3', '799');
    // first row exists already; add a second one
    await page.locator('#add-item-row-btn').click();
    const selects = page.locator('#items-container select[name="service_id[]"]');
    expect(await selects.count()).toBe(2);
    await selects.nth(0).selectOption({ index: 0 });
    await selects.nth(1).selectOption({ index: 1 });
    await page.locator('#items-container input[name="quantity[]"]').nth(1).fill('3');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: /publish combo package/i }).last().click()]);
    await expect(page.getByText(pkgName).first()).toBeVisible();
    const items = JSON.parse(run(adminHelper, 'package-items', pkgName));
    expect(items).toHaveLength(2);
    expect(items.map((i) => Number(i.quantity))).toEqual([2, 3]);
  });
});
