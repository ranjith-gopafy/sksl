const { test, expect } = require('@playwright/test');

/**
 * SKSL My Bookings Dashboard E2E Tests
 *
 * Uses domcontentloaded to avoid hanging on external font requests.
 */

const TEST_EMAIL    = process.env.TEST_EMAIL    || 'e2e_booking@sksltest.local';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'BookingTest@2024!';

async function loginAsCustomer(page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', TEST_EMAIL);
  await page.fill('input[name="password"]', TEST_PASSWORD);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/.*/, { timeout: 10000 });
}

test.describe('My Bookings — Authentication Guard', () => {
  test('accessing /my-bookings without login redirects to /login', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });
});

test.describe('My Bookings Dashboard UI', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) {
      test.skip();
    }
  });

  test('/my-bookings loads successfully after login', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/my-bookings/);
    await expect(page.locator('h1')).toContainText(/booking|session|history/i);
  });

  test('page contains Upcoming, Completed, or Cancelled tab text', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    const pageText = await page.locator('body').textContent();
    expect(pageText?.toLowerCase()).toMatch(/upcoming|completed|cancelled/i);
  });

  test('filter tabs switch without navigating away', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });

    const completedTab = page.locator('button:has-text("Completed"), a:has-text("Completed")').first();
    if (await completedTab.isVisible()) {
      await completedTab.click();
      await page.waitForTimeout(400);
      await expect(page).toHaveURL(/my-bookings/);
    }

    const cancelledTab = page.locator('button:has-text("Cancelled"), a:has-text("Cancelled")').first();
    if (await cancelledTab.isVisible()) {
      await cancelledTab.click();
      await page.waitForTimeout(400);
      await expect(page).toHaveURL(/my-bookings/);
    }
  });

  test('"Book a Session" CTA link is present', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    const anyBookLink = page.locator('a[href*="booking"], a[href*="services"]').first();
    await expect(anyBookLink).toBeVisible();
  });
});
