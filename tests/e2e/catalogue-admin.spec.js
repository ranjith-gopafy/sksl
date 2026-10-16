const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

/**
 * Catalogue management through the real screens: the three-dots menu on the bookings list opens fully
 * visible, categories are added / renamed / deleted, a modality is added, shows up for customers under
 * its category, and is deleted again. Also the website menu (no Home link, no admin shortcuts).
 * Throwaway admin, customer, category and modality are created here and removed afterwards.
 */

const adminHelper = path.join(__dirname, '..', 'support', 'admin_session.php');
const custHelper = path.join(__dirname, '..', 'support', 'customer_session.php');
const run = (file, ...args) => execFileSync('php', [file, ...args], { encoding: 'utf8' }).trim();

const suffix = Math.random().toString(36).slice(2, 7);
const catName = 'E2E Cat ' + suffix;
const catRenamed = 'E2E Cat Renamed ' + suffix;
const modName = 'E2E Modality ' + suffix;

let admin, cust;

test.beforeAll(() => {
  admin = JSON.parse(run(adminHelper, 'create'));
  cust = JSON.parse(run(custHelper, 'create'));
  for (let i = 0; i < 3; i++) { run(custHelper, 'booking', String(cust.id), String(60 + i * 5), 'paid'); }
});
test.afterAll(() => {
  run(adminHelper, 'remove-modality', modName);
  run(adminHelper, 'remove-category', catName);
  run(adminHelper, 'remove-category', catRenamed);
  if (admin) { run(adminHelper, 'cleanup', String(admin.id)); }
  if (cust) { run(custHelper, 'cleanup', String(cust.id)); }
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

test.describe.serial('Catalogue management through the screens', () => {
  test('the three-dots menu opens fully visible, also on the last row', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/bookings?status=all', { waitUntil: 'load' });
    const buttons = page.locator('[data-action="toggleActionMenu"]');
    expect(await buttons.count()).toBeGreaterThan(1);

    for (const which of ['first', 'last']) {
      const btn = which === 'first' ? buttons.first() : buttons.last();
      await btn.scrollIntoViewIfNeeded();
      await btn.click();
      const id = await btn.getAttribute('data-id');
      const menu = page.locator('#action-dropdown-' + id);
      await expect(menu).toBeVisible();

      const result = await menu.evaluate((el) => {
        const r = el.getBoundingClientRect();
        const vw = window.innerWidth, vh = window.innerHeight;
        // every corner and the middle of the menu must really be the menu on screen (not clipped by the table box)
        const o = 18; // inside the rounded corners
        const points = [[r.left + o, r.top + o], [r.right - o, r.top + o], [r.left + o, r.bottom - o], [r.right - o, r.bottom - o], [(r.left + r.right) / 2, (r.top + r.bottom) / 2]];
        const hit = points.map(([x, y]) => { const t = document.elementFromPoint(x, y); return !!t && el.contains(t); });
        return { inside: r.left >= 0 && r.top >= 0 && r.right <= vw && r.bottom <= vh, hit, scrollable: el.scrollHeight > el.clientHeight + 1 };
      });
      expect(result.inside, which + ' row: the menu is inside the window').toBe(true);
      expect(result.hit, which + ' row: no part of the menu is clipped').toEqual([true, true, true, true, true]);
      expect(result.scrollable, which + ' row: the menu itself does not need scrolling').toBe(false);

      // an outside click closes it
      await page.mouse.click(5, 5);
      await expect(menu).toBeHidden();
    }
  });

  test('a category can be added, renamed, and deleted when empty', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/categories', { waitUntil: 'load' });
    for (const name of ['Cold & Cryo Immersion', 'Thermal & Sauna', 'Hydrotherapy', 'Myotherapy']) {
      await expect(page.locator('[data-testid="category-row"]', { has: page.locator(`input[value="${name.replace(/&/g, '&')}"]`) })).toHaveCount(1);
    }
    // categories that hold modalities explain why they cannot be deleted
    await expect(page.getByText(/first move or remove its modalities/i).first()).toBeVisible();

    await page.fill('#new_category_name', catName);
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: /add category/i }).click()]);
    await expect(page.getByText(`Added category "${catName}"`)).toBeVisible();

    const row = page.locator('[data-testid="category-row"]', { has: page.locator(`input[value="${catName}"]`) });
    await expect(row).toHaveCount(1);
    await row.locator('input[name="name"]').fill(catRenamed);
    await Promise.all([page.waitForNavigation(), row.getByRole('button', { name: /^save$/i }).click()]);
    await expect(page.getByText(`Saved category "${catRenamed}"`)).toBeVisible();

    // same name again is refused with a clear message
    await page.fill('#new_category_name', catRenamed.toUpperCase());
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: /add category/i }).click()]);
    await expect(page.getByText(/already a category/i)).toBeVisible();
  });

  test('a modality is added, shows for customers under its category, and is deleted', async ({ page, isMobile }) => {
    await signIn(page);
    await page.goto('/admin/services', { waitUntil: 'load' });
    await page.getByTestId('add-modality').click();
    const dialogForm = page.locator('#edit-service-form');
    await expect(dialogForm).toBeVisible();
    await expect(page.locator('#modal-service-title')).toHaveText(/add modality/i);

    // a category is required
    await page.fill('#edit_name', modName);
    await page.fill('#edit_price', '777');
    await page.fill('#edit_capacity', '3');
    await page.fill('#edit_duration', '20');
    await page.fill('#edit_description', 'Added by the browser test.');
    await page.locator('#edit_category').selectOption({ label: catRenamed });
    await Promise.all([page.waitForNavigation(), dialogForm.getByRole('button', { name: /^add modality$/i }).click()]);
    await expect(page.getByText(`Added modality "${modName}"`)).toBeVisible();

    const row = page.locator('tr.service-row', { hasText: modName });
    await expect(row).toHaveCount(1);
    await expect(row.getByTestId('modality-category')).toHaveText(catRenamed);

    // customers: the modality is on the modalities page, under a filter button of its category
    const pub = await page.context().newPage();
    await pub.goto('/services', { waitUntil: 'load' });
    const filter = pub.getByRole('button', { name: catRenamed });
    await expect(filter).toBeVisible();
    await expect(pub.getByText(modName)).toBeVisible();
    await filter.click();
    await expect(pub.locator('.service-item-card:visible')).toHaveCount(1);
    await expect(pub.locator('.service-item-card:visible')).toContainText(modName);
    await pub.getByRole('button', { name: /all modalities/i }).click();
    expect(await pub.locator('.service-item-card:visible').count()).toBeGreaterThan(1);
    await pub.close();

    // edit it: rename and regroup
    await row.getByRole('button', { name: /^edit$/i }).click();
    await expect(page.locator('#edit_name')).toHaveValue(modName);
    await expect(page.locator('#edit_category')).toHaveValue(/\d+/);
    await page.locator('#edit_category').selectOption({ label: 'Hydrotherapy' });
    await Promise.all([page.waitForNavigation(), dialogForm.getByRole('button', { name: /save changes/i }).click()]);
    await expect(page.locator('tr.service-row', { hasText: modName }).getByTestId('modality-category')).toHaveText('Hydrotherapy');

    // delete it: asks first, "keep it" changes nothing, "yes" removes it
    const del = page.locator('tr.service-row', { hasText: modName }).locator('.btn-delete-service');
    page.once('dialog', (d) => { expect(d.message()).toContain(modName); d.dismiss(); });
    await del.click();
    await page.waitForTimeout(400);
    await expect(page.locator('tr.service-row', { hasText: modName })).toHaveCount(1);
    page.once('dialog', (d) => d.accept());
    await Promise.all([page.waitForNavigation(), del.click()]);
    await expect(page.getByText(`Deleted modality "${modName}"`)).toBeVisible();
    await expect(page.locator('tr.service-row', { hasText: modName })).toHaveCount(0);

    // now the (empty) category can go too
    await page.goto('/admin/categories', { waitUntil: 'load' });
    const catRow = page.locator('[data-testid="category-row"]', { has: page.locator(`input[value="${catRenamed}"]`) });
    page.once('dialog', (d) => d.accept());
    await Promise.all([page.waitForNavigation(), catRow.getByRole('button', { name: /^delete$/i }).click()]);
    await expect(page.getByText(`Deleted category "${catRenamed}"`)).toBeVisible();
  });

  test('the website menu has no Home link and no admin shortcuts', async ({ page, isMobile }) => {
    test.skip(isMobile, 'the top menu is a desktop element; phones use the bottom bar');
    await signIn(page);
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const nav = page.locator('nav[aria-label="Primary"]');
    await expect(nav.getByRole('link', { name: 'Home', exact: true })).toHaveCount(0);
    for (const name of ['Bookings', 'Packages', 'Banners']) {
      await expect(nav.getByRole('link', { name, exact: true })).toHaveCount(0);
    }
    for (const label of ['Modalities', 'Combo Packages', 'Physio & Psychology', 'Contact']) {
      await expect(nav.getByRole('link', { name: label })).toBeVisible();
    }
    // the admin keeps a way back to the dashboard
    await expect(page.getByRole('link', { name: /admin control center/i }).first()).toBeVisible();
  });
});
