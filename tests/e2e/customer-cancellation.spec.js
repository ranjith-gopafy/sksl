const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

/**
 * Customer cancellation in My Sessions: a Cancel button for sessions more than 24 hours away, a
 * confirmation dialog that shows the 10% charge and the refund, a closed notice inside 24 hours,
 * and the cancelled tab afterwards. A throwaway customer and bookings come from tests/support/customer_session.php.
 */

const helper = path.join(__dirname, '..', 'support', 'customer_session.php');
const php = (...args) => execFileSync('php', [helper, ...args], { encoding: 'utf8' }).trim();

let cust;
let paid, late, pkg, centre;

test.beforeAll(() => {
  cust = JSON.parse(php('create'));
  paid = JSON.parse(php('booking', String(cust.id), '50', 'paid')).ref;
  late = JSON.parse(php('booking', String(cust.id), '10', 'paid')).ref;
  pkg = JSON.parse(php('booking', String(cust.id), '60', 'package')).ref;
  centre = JSON.parse(php('booking', String(cust.id), '72', 'centre')).ref;
});
test.afterAll(() => { if (cust) { php('cleanup', String(cust.id)); } });

async function signIn(page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', cust.email);
  await page.fill('input[name="password"]', cust.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 15000 });
}

const card = (page, ref) => page.locator('div.rounded-3xl', { hasText: ref });

test.describe('Customer cancellation', () => {
  test('sessions inside 24 hours show a closed notice instead of a button', async ({ page }) => {
    await signIn(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'domcontentloaded' });
    await expect(card(page, late).getByTestId('cancel-closed')).toContainText('24 h');
    await expect(card(page, late).getByRole('button', { name: 'Cancel session' })).toHaveCount(0);
    await expect(card(page, paid).getByRole('button', { name: 'Cancel session' })).toBeVisible();
  });

  test('keeping the session closes the dialog and changes nothing', async ({ page }) => {
    await signIn(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'domcontentloaded' });
    await card(page, paid).getByRole('button', { name: 'Cancel session' }).click();
    await expect(page.locator('#cancel-dialog')).toBeVisible();
    await page.getByRole('button', { name: 'Keep my session' }).click();
    await expect(page.locator('#cancel-dialog')).toBeHidden();
    expect(JSON.parse(php('status', paid)).booking_status).toBe('confirmed');
  });

  test('cancelling a paid session shows the charge and refund, then records them', async ({ page }) => {
    await signIn(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'domcontentloaded' });
    await card(page, paid).getByRole('button', { name: 'Cancel session' }).click();
    const dlg = page.locator('#cancel-dialog');
    await expect(dlg).toBeVisible();
    await expect(dlg.locator('#cancel-amount')).toHaveText('₹599.00');
    await expect(dlg.locator('#cancel-fee')).toContainText('₹59.90');
    await expect(dlg.locator('#cancel-refund')).toHaveText('₹539.10');
    await dlg.getByRole('button', { name: 'Yes, cancel session' }).click();
    await page.waitForURL(/tab=cancelled/);
    await expect(page.getByText('A cancellation charge of ₹59.90 applies').first()).toBeVisible();
    const row = card(page, paid);
    await expect(row.getByTestId('refund-note')).toContainText('₹539.10');
    await expect(row.getByRole('button', { name: 'Cancel session' })).toHaveCount(0);
    const st = JSON.parse(php('status', paid));
    expect(st).toMatchObject({ booking_status: 'cancelled', cancelled_by: 'customer', payment_status: 'paid' });
    expect(Number(st.refund_amount)).toBe(539.1);
  });

  test('a package session and a pay-at-centre appointment cancel with no charge', async ({ page }) => {
    await signIn(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'domcontentloaded' });
    await card(page, pkg).getByRole('button', { name: 'Cancel session' }).click();
    await expect(page.locator('#cancel-dialog #cancel-free')).toContainText('package credit will be returned');
    await expect(page.locator('#cancel-dialog #cancel-money')).toBeHidden();
    await page.locator('#cancel-dialog').getByRole('button', { name: 'Yes, cancel session' }).click();
    await page.waitForURL(/tab=cancelled/);
    expect(JSON.parse(php('status', pkg)).booking_status).toBe('cancelled');

    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'domcontentloaded' });
    await card(page, centre).getByRole('button', { name: 'Cancel session' }).click();
    await expect(page.locator('#cancel-dialog #cancel-free')).toContainText('Nothing was paid online');
    await page.locator('#cancel-dialog').getByRole('button', { name: 'Yes, cancel session' }).click();
    await page.waitForURL(/tab=cancelled/);
    expect(JSON.parse(php('status', centre)).booking_status).toBe('cancelled');
  });

  test('the cancellation policy page states the rule', async ({ page }) => {
    await page.goto('/cancellation-refund', { waitUntil: 'domcontentloaded' });
    const body = page.locator('main, body').first();
    await expect(body).toContainText('24 hours before');
    await expect(body).toContainText('10% of the amount paid');
    await expect(body).not.toContainText('no self-service');
  });
});
