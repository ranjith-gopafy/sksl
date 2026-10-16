const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

/**
 * SKSL Admin dashboard E2E tests (signed in).
 *
 * Signs in through the real admin flow (email -> one-time code) with a throwaway administrator
 * created by tests/support/admin_session.php, then checks the dashboard shell on every device size:
 *   - no public navigation bar, footer or marketing bar
 *   - the sidebar lists every admin page; on phones it is a drawer opened by the menu button
 *   - every admin page loads inside the shell, has one h1, and never scrolls sideways
 */

const helper = path.join(__dirname, '..', 'support', 'admin_session.php');
const php = (...args) => execFileSync('php', [helper, ...args], { encoding: 'utf8' }).trim();

const PAGES = [
  ['/admin/dashboard', 'Dashboard'],
  ['/admin/bookings', 'Bookings Operations'],
  ['/admin/services', null],
  ['/admin/availability', null],
  ['/admin/manual-blocks', null],
  ['/admin/packages', null],
  ['/admin/package-purchases', 'Package Purchases'],
  ['/admin/closed-dates', null],
  ['/admin/banner', null],
];

let admin;

test.beforeAll(() => { admin = JSON.parse(php('create')); });
test.afterAll(() => { if (admin) { php('cleanup', String(admin.id)); } });

async function signIn(page) {
  php('reset-limits'); // the sign-in throttle (10 code requests per IP per 15 min) would otherwise stop a long test run
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', admin.email);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/verify-otp/);
  php('otp', String(admin.id));
  await page.fill('input[name="otp"]', '246810');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL(/admin\/dashboard/, { timeout: 15000 });
}

test.describe('Admin sign-in screens', () => {
  test('login and code screens have no public navigation bar or footer', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header')).toHaveCount(0);
    await expect(page.locator('footer')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Back to website' })).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
  });
});

test.describe('Admin dashboard shell', () => {
  test('signing in lands on the dashboard, without the public site chrome', async ({ page, isMobile }) => {
    await signIn(page);
    await expect(page).toHaveURL(/admin\/dashboard/);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('h1')).toContainText('Dashboard');
    // none of the public site's chrome
    await expect(page.locator('nav[aria-label="Primary"]')).toHaveCount(0);
    await expect(page.locator('nav[aria-label="Mobile"]')).toHaveCount(0);
    await expect(page.locator('footer')).toHaveCount(0);
    await expect(page.getByText('Open Daily:')).toHaveCount(0);
    if (!isMobile) {
      await expect(page.locator('#admin-sidebar')).toBeVisible();
    }
  });

  test('the sidebar reaches every admin page (drawer on phones)', async ({ page, isMobile }) => {
    await signIn(page);
    if (isMobile) {
      await expect(page.locator('#admin-sidebar')).toBeHidden();
      await page.locator('#admin-menu-open').click();
      await expect(page.locator('#admin-sidebar')).toBeVisible();
    }
    const nav = page.locator('#admin-sidebar nav[aria-label="Admin"]');
    for (const label of ['Dashboard', 'Bookings', 'Package Purchases', 'Modalities', 'Combo Packages', 'Practitioner Schedules', 'Manual Blocks', 'Closed Dates', 'Hero Banner']) {
      await expect(nav.getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    await expect(page.locator('#admin-sidebar').getByRole('button', { name: 'Sign out' })).toBeVisible();
    if (isMobile) {
      await page.keyboard.press('Escape');
      await expect(page.locator('#admin-sidebar')).toBeHidden();
    }
  });

  test('every admin page loads inside the shell with one heading and no sideways scrolling', async ({ page }) => {
    test.setTimeout(90000);
    await signIn(page);
    for (const [url, heading] of PAGES) {
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      const res = await page.goto(url, { waitUntil: 'load' });
      expect(res.status(), url).toBe(200);
      await expect(page.locator('h1'), url).toHaveCount(1);
      if (heading) { await expect(page.locator('h1'), url).toContainText(heading); }
      await expect(page.locator('footer'), url).toHaveCount(0);
      await expect(page.locator('#admin-sidebar nav a[aria-current="page"]'), url).toHaveCount(1);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
      expect(overflow, `${url} must not scroll sideways`).toBe(false);
      expect(errors, url).toEqual([]);
    }
  });

  test('the modalities page is a table whose rows can be searched, with every action reachable', async ({ page, isMobile }) => {
    await signIn(page);
    await page.goto('/admin/services', { waitUntil: 'load' });
    const rows = page.locator('#services-table .service-row');
    expect(await rows.count()).toBeGreaterThan(3);
    const first = rows.first();
    await expect(first.getByRole('button', { name: 'Edit' })).toBeVisible();
    await expect(first.getByRole('button', { name: 'Durations' })).toBeVisible();
    await expect(first.getByRole('button', { name: /Activate|Deactivate/ })).toBeVisible();
    if (!isMobile) {
      // the Edit button must not be clipped by the table's own scroll area
      const box = await first.getByRole('button', { name: 'Edit' }).boundingBox();
      const wrap = await page.locator('#services-table').locator('xpath=..').boundingBox();
      expect(box.x + box.width).toBeLessThanOrEqual(wrap.x + wrap.width + 1);
    }
    await page.fill('#service-search-input', 'zzzz-no-such-modality');
    await expect(page.locator('#services-no-match')).toBeVisible();
    await page.fill('#service-search-input', 'sauna');
    await expect(page.locator('#services-no-match')).toBeHidden();
    await expect(page.locator('#services-table .service-row:not(.hidden)')).toHaveCount(1);
  });

  test('the Edit pop-up opens filled in, saves, and the table shows the change', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/services', { waitUntil: 'load' });
    const row = page.locator('#services-table .service-row', { hasText: 'Sauna' }).first();
    await row.getByRole('button', { name: 'Edit' }).click();
    const modal = page.locator('#edit-service-modal');
    await expect(modal).toBeVisible();
    const original = await modal.locator('#edit_capacity').inputValue();
    expect(Number(original)).toBeGreaterThan(0);
    await expect(modal.locator('#edit_price')).not.toHaveValue('');
    const changed = String(Number(original) + 1);
    await modal.locator('#edit_capacity').fill(changed);
    await Promise.all([page.waitForNavigation(), modal.getByRole('button', { name: /save/i }).click()]);
    await expect(page.locator('#services-table .service-row', { hasText: 'Sauna' }).first()).toContainText(`${changed} athletes`);
    // put it back through the same pop-up
    await page.locator('#services-table .service-row', { hasText: 'Sauna' }).first().getByRole('button', { name: 'Edit' }).click();
    await page.locator('#edit-service-modal #edit_capacity').fill(original);
    await Promise.all([page.waitForNavigation(), page.locator('#edit-service-modal').getByRole('button', { name: /save/i }).click()]);
    await expect(page.locator('#services-table .service-row', { hasText: 'Sauna' }).first()).toContainText(`${original} athletes`);
  });

  test('the Durations pop-up adds and removes a session length', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/services', { waitUntil: 'load' });
    const open = async () => {
      await page.locator('#services-table .service-row', { hasText: 'Sauna' }).first().getByRole('button', { name: 'Durations' }).click();
      await expect(page.locator('#durations-modal')).toBeVisible();
    };
    await open();
    await page.locator('#new_duration_minutes').fill('21');
    await page.locator('#new_duration_price').fill('333');
    await Promise.all([page.waitForNavigation(), page.locator('#add-duration-form').getByRole('button', { name: /add duration/i }).click()]);
    await open();
    await expect(page.locator('#durations-list')).toContainText('21 Minutes');
    await expect(page.locator('#durations-list')).toContainText('Price (incl. GST): ₹333.00');
    const mine = page.locator('#durations-list > div', { hasText: '21 Minutes' });
    page.once('dialog', (d) => d.accept()); // "Remove 21m duration option?"
    await Promise.all([page.waitForNavigation(), mine.getByRole('button', { name: 'Remove' }).click()]);
    await open();
    await expect(page.locator('#durations-list')).not.toContainText('21 Minutes');
  });

  test('signing out returns to the sign-in screen', async ({ page, isMobile }) => {
    await signIn(page);
    if (isMobile) { await page.locator('#admin-menu-open').click(); }
    await page.locator('#admin-sidebar').getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL(/admin\/login/);
    await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/admin\/login/);
  });
});
