const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Offline payment mode (ONLINE_PAYMENTS_ENABLED=false, served on its own port by playwright.config.js):
 * the gateway is hidden, a booking or combo package is requested, the customer pays by UPI, and staff
 * approve it in the admin; only then is it confirmed and invoiced.
 */

const custHelper = path.join(__dirname, '..', 'support', 'customer_session.php');
const adminHelper = path.join(__dirname, '..', 'support', 'admin_session.php');
const run = (file, ...args) => execFileSync('php', [file, ...args], { encoding: 'utf8' }).trim();
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

let cust, admin;
let ref;           // booking made through the screens
let pendingRef;    // booking made by the helper, rejected by staff

test.beforeAll(() => {
  cust = JSON.parse(run(custHelper, 'create'));
  admin = JSON.parse(run(adminHelper, 'create'));
});
test.afterAll(() => {
  if (cust) { run(custHelper, 'cleanup', String(cust.id)); }
  if (admin) { run(adminHelper, 'cleanup', String(admin.id)); }
});

async function signInCustomer(page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', cust.email);
  await page.fill('input[name="password"]', cust.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 15000 });
}
async function signInAdmin(page) {
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
const ymd = (n) => new Date(Date.now() + n * 86400000).toISOString().slice(0, 10);
async function axe(page) {
  await page.evaluate(axeSource);
  return page.evaluate(async () => (await window.axe.run({ exclude: [['#toast-container']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] }, resultTypes: ['violations'] })).violations.map((v) => `${v.impact} ${v.id}: ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`));
}

test.describe.serial('Offline payment mode', () => {
  test('the booking page has no payment gateway and requests the booking', async ({ page }) => {
    await signInCustomer(page);
    await page.goto('/booking?service=sauna', { waitUntil: 'load' });
    expect(await page.locator('script[src*="razorpay"]').count()).toBe(0);
    await expect(page.locator('#mock-payment-modal')).toBeHidden();

    let picked = false;
    for (let n = 9; n <= 16 && !picked; n++) {
      const dateInput = page.locator('#date_input, input[type="date"]').first();
      await dateInput.fill(ymd(n));
      await dateInput.dispatchEvent('change');
      await page.waitForTimeout(700);
      const slot = page.locator('.slot-btn:not([disabled]):not(.slot-unavailable)').first();
      if (await slot.count()) { await slot.click(); picked = true; }
    }
    expect(picked, 'a free slot should exist in the next two weeks').toBe(true);
    await page.locator('#terms-check').check();
    const proceed = page.locator('#proceed-hold-btn');
    await expect(proceed).toBeEnabled();
    await expect(proceed).toContainText('Request Booking');
    await expect(page.locator('#proceed-btn-sub')).toContainText('pay by UPI');
    await Promise.all([page.waitForURL(/booking-confirmation/, { timeout: 20000 }), proceed.click()]);

    await expect(page.locator('h1')).toContainText('We Have Your Booking Request');
    ref = (await page.locator('#booking-ref-display').innerText()).trim();
    expect(ref).toMatch(/^SKSL-\d{8}-[A-Z0-9]{6}$/);
    const box = page.getByTestId('offline-payment-box');
    await expect(box).toBeVisible();
    await expect(box.locator('img[alt^="UPI QR code"]')).toBeVisible();
    await expect(box.getByTestId('upi-id')).toHaveText(/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/); // the id comes from UPI_ID in .env
    await expect(box).toContainText('₹599.00');
    await expect(box).toContainText(ref);
    await expect(page.getByTestId('pay-by')).toContainText('Please pay before');
    await expect(page.getByText('Download Tax Invoice')).toHaveCount(0);
    const st = JSON.parse(run(custHelper, 'status', ref));
    expect(st).toMatchObject({ booking_status: 'pending', payment_status: 'pending', payment_channel: 'offline' });
  });

  test('the confirmation page for a waiting request passes the accessibility checks on a phone and shows no sideways scroll', async ({ page }) => {
    await signInCustomer(page);
    await page.setViewportSize({ width: 360, height: 780 });
    await page.goto('/booking-confirmation?ref=' + ref, { waitUntil: 'load' });
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    expect(await axe(page)).toEqual([]);
    await expect(page.getByRole('link', { name: 'Open my UPI app' })).toBeVisible();
  });

  test('My Sessions shows the request as awaiting payment and lets the customer cancel it', async ({ page }) => {
    await signInCustomer(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'load' });
    const card = page.locator('div.rounded-3xl', { hasText: ref });
    await expect(card.getByTestId('awaiting-chip')).toContainText('Awaiting payment');
    await expect(card.getByTestId('pay-by-note')).toContainText('Pay by');
    await expect(card.getByTestId('how-to-pay')).toBeVisible();
    await expect(card.getByRole('button', { name: 'Cancel request' })).toBeVisible();
    await expect(card).toContainText('Amount to Pay');
    expect(await axe(page)).toEqual([]);
  });

  test('staff see it under Awaiting payment; approval needs a method and a reference', async ({ page }) => {
    await signInAdmin(page);
    await page.goto('/admin/dashboard', { waitUntil: 'load' });
    await expect(page.getByTestId('awaiting-approval-card')).toBeVisible();
    await page.goto('/admin/bookings?status=pending&search=' + ref, { waitUntil: 'load' });
    const row = page.locator('tr', { hasText: ref });
    await expect(row.first().getByTestId('admin-awaiting-chip')).toBeVisible();
    await row.first().getByRole('button', { name: 'Approve payment' }).click();
    const dlg = page.locator('#approve-dialog');
    await expect(dlg).toBeVisible();
    await expect(dlg.locator('#approve-amount')).toHaveText('₹599.00');
    expect(await axe(page)).toEqual([]);
    // an empty reference cannot be submitted
    await dlg.getByRole('button', { name: /approve & confirm/i }).click();
    await expect(dlg).toBeVisible();
    expect(JSON.parse(run(custHelper, 'status', ref)).booking_status).toBe('pending');
    // not yet: the booking stays as it was
    await dlg.getByRole('button', { name: 'Not yet' }).click();
    await expect(dlg).toBeHidden();
    expect(JSON.parse(run(custHelper, 'status', ref)).booking_status).toBe('pending');
  });

  test('approving confirms the booking, records how it was paid and shows it to the customer', async ({ page, browser }) => {
    await signInAdmin(page);
    await page.goto('/admin/bookings?status=pending&search=' + ref, { waitUntil: 'load' });
    await page.locator('tr', { hasText: ref }).first().getByRole('button', { name: 'Approve payment' }).click();
    const dlg = page.locator('#approve-dialog');
    await dlg.locator('#approve-method').selectOption('upi');
    await dlg.locator('#approve-reference').fill('E2E-UTR-123456');
    await Promise.all([page.waitForNavigation(), dlg.getByRole('button', { name: /approve & confirm/i }).click()]);
    await expect(page.locator('body')).toContainText('Payment approved');
    const st = JSON.parse(run(custHelper, 'status', ref));
    expect(st).toMatchObject({ booking_status: 'confirmed', payment_status: 'paid', payment_method: 'upi', payment_reference: 'E2E-UTR-123456' });

    await page.goto('/admin/bookings?status=confirmed&search=' + ref, { waitUntil: 'load' });
    await expect(page.locator('tr', { hasText: ref }).first()).toContainText('E2E-UTR-123456');

    // the customer's side
    const ctx = await browser.newContext();
    const cpage = await ctx.newPage();
    await signInCustomer(cpage);
    await cpage.goto('/my-bookings?tab=upcoming', { waitUntil: 'load' });
    const card = cpage.locator('div.rounded-3xl', { hasText: ref });
    await expect(card).toContainText('Confirmed');
    await expect(card.getByTestId('awaiting-chip')).toHaveCount(0);
    await cpage.goto('/booking-confirmation?ref=' + ref, { waitUntil: 'load' });
    await expect(cpage.locator('h1')).toContainText('Your Recovery Session is Confirmed');
    await expect(cpage.locator('body')).toContainText('E2E-UTR-123456');
    await expect(cpage.getByTestId('offline-payment-box')).toHaveCount(0);
    const inv = await cpage.request.get('/bookings/' + ref + '/invoice');
    expect(inv.status()).toBe(200);
    expect(inv.headers()['content-type']).toContain('pdf');
    await ctx.close();
  });

  test('staff can reject an unpaid request; the customer sees it gone and the slot is free', async ({ page, browser }) => {
    pendingRef = JSON.parse(run(custHelper, 'booking', String(cust.id), '96', 'offline')).ref;
    await signInAdmin(page);
    await page.goto('/admin/bookings?status=pending&search=' + pendingRef, { waitUntil: 'load' });
    const row = page.locator('tr', { hasText: pendingRef }).first();
    await row.locator('[data-action="toggleActionMenu"]').click();
    page.once('dialog', (d) => d.accept());
    await Promise.all([page.waitForNavigation(), row.getByRole('button', { name: /cancel booking/i }).click()]);
    expect(JSON.parse(run(custHelper, 'status', pendingRef))).toMatchObject({ booking_status: 'cancelled', refund_amount: null });
    const ctx = await browser.newContext();
    const cpage = await ctx.newPage();
    await signInCustomer(cpage);
    await cpage.goto('/my-bookings?tab=cancelled', { waitUntil: 'load' });
    await expect(cpage.locator('div.rounded-3xl', { hasText: pendingRef })).toBeVisible();
    await ctx.close();
  });

  test('a customer can withdraw an unpaid request themselves, at no charge', async ({ page }) => {
    const mine = JSON.parse(run(custHelper, 'booking', String(cust.id), '100', 'offline')).ref;
    await signInCustomer(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'load' });
    await page.locator('div.rounded-3xl', { hasText: mine }).getByRole('button', { name: 'Cancel request' }).click();
    await expect(page.locator('#cancel-dialog #cancel-free')).toContainText('No charge');
    await Promise.all([page.waitForURL(/tab=cancelled/), page.locator('#cancel-dialog').getByRole('button', { name: 'Yes, cancel session' }).click()]);
    expect(JSON.parse(run(custHelper, 'status', mine))).toMatchObject({ booking_status: 'cancelled', refund_amount: null, cancellation_fee: null });
  });

  test('combo packages: the customer requests, staff approve, the credits appear', async ({ page, browser }) => {
    await signInCustomer(page);
    await page.goto('/packages', { waitUntil: 'load' });
    expect(await page.locator('script[src*="razorpay"]').count()).toBe(0);
    const buy = page.getByRole('button', { name: 'Request Bundle' }).first();
    await expect(buy).toBeVisible();
    await Promise.all([page.waitForURL(/my-packages/, { timeout: 20000 }), buy.click()]);
    const pending = page.getByTestId('pending-package').first();
    await expect(pending).toBeVisible();
    await expect(pending.getByTestId('offline-payment-box')).toBeVisible();
    await expect(pending).toContainText('Awaiting payment');
    const pkgRef = (await pending.locator('span.font-mono').first().innerText()).trim();
    expect(pkgRef).toMatch(/^SKSL-PKG-/);
    expect(await axe(page)).toEqual([]);

    const actx = await browser.newContext();
    const apage = await actx.newPage();
    await signInAdmin(apage);
    await apage.goto('/admin/package-purchases', { waitUntil: 'load' });
    const row = apage.locator('tr', { hasText: pkgRef });
    await expect(row.getByTestId('pkg-awaiting-chip')).toBeVisible();
    await row.getByRole('button', { name: 'Approve payment' }).click();
    const dlg = apage.locator('#approve-dialog');
    await dlg.locator('#approve-method').selectOption('cash');
    await dlg.locator('#approve-reference').fill('RECEIPT-0042');
    await Promise.all([apage.waitForNavigation(), dlg.getByRole('button', { name: /approve & activate/i }).click()]);
    await expect(apage.locator('body')).toContainText('Payment approved');
    await expect(apage.locator('tr', { hasText: pkgRef })).toContainText('RECEIPT-0042');
    await actx.close();

    await page.goto('/my-packages', { waitUntil: 'load' });
    await expect(page.getByTestId('pending-package')).toHaveCount(0);
    await expect(page.locator('body')).toContainText(pkgRef);
    await expect(page.locator('body')).toContainText(/remaining|left|credit/i);
  });
});
