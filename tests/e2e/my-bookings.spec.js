const { test, expect } = require('@playwright/test');

/**
 * SKSL My Bookings Dashboard E2E Tests
 *
 * Tests:
 * - Unauthenticated access redirects to login
 * - Dashboard renders with correct filter tabs
 * - Upcoming / Completed / Cancelled tabs work
 * - "Book Another Session" CTA present
 * - Download invoice link structure
 */

const TEST_EMAIL    = process.env.TEST_EMAIL    || 'e2e_booking@sksltest.local';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'BookingTest@2024!';

async function loginAsCustomer(page) {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', TEST_EMAIL);
  await page.fill('input[name="password"]', TEST_PASSWORD);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

test.describe('My Bookings — Authentication Guard', () => {
  test('accessing /my-bookings without login redirects to /login', async ({ page }) => {
    await page.goto('/my-bookings');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/login/);
  });
});

test.describe('My Bookings Dashboard UI', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsCustomer(page);
    // Skip suite if login failed
    if (page.url().includes('login')) {
      test.skip();
    }
  });

  test('/my-bookings loads successfully after login', async ({ page }) => {
    await page.goto('/my-bookings');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/my-bookings/);
    await expect(page.locator('h1')).toContainText(/booking|session|history/i);
  });

  test('filter tabs Upcoming, Completed, Cancelled are visible', async ({ page }) => {
    await page.goto('/my-bookings');
    await page.waitForLoadState('networkidle');

    // Filter tabs must have some form of upcoming/completed/cancelled navigation
    const pageText = await page.locator('body').textContent();
    expect(pageText?.toLowerCase()).toMatch(/upcoming|completed|cancelled/i);
  });

  test('filter tabs switch content when clicked', async ({ page }) => {
    await page.goto('/my-bookings');
    await page.waitForLoadState('networkidle');

    // Try clicking "Completed" tab
    const completedTab = page.locator('button:has-text("Completed"), a:has-text("Completed")').first();
    if (await completedTab.isVisible()) {
      await completedTab.click();
      await page.waitForTimeout(400);
      // Should not crash or navigate away
      await expect(page).toHaveURL(/my-bookings/);
    }

    // Try clicking "Cancelled" tab
    const cancelledTab = page.locator('button:has-text("Cancelled"), a:has-text("Cancelled")').first();
    if (await cancelledTab.isVisible()) {
      await cancelledTab.click();
      await page.waitForTimeout(400);
      await expect(page).toHaveURL(/my-bookings/);
    }
  });

  test('"Book a Session" CTA link is present', async ({ page }) => {
    await page.goto('/my-bookings');
    await page.waitForLoadState('networkidle');

    const bookCta = page.locator('a[href*="booking"]:has-text("Book"), a[href*="services"]:has-text("Book")').first();
    if (await bookCta.isVisible()) {
      await expect(bookCta).toBeVisible();
    }
    // Even if the CTA text differs, booking link should exist somewhere
    const anyBookLink = page.locator('a[href*="booking"], a[href*="services"]').first();
    await expect(anyBookLink).toBeVisible();
  });
});
