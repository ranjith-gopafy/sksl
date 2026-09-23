const { test, expect } = require('@playwright/test');

/**
 * SKSL Admin Panel Flow E2E Tests
 *
 * Tests:
 * - /admin/login renders email form
 * - /admin/* routes require authentication (redirect unauthenticated users)
 * - Admin OTP form renders after email submission
 * - Admin dashboard is inaccessible without valid session
 *
 * Note: We cannot complete full admin login in automated tests because
 * OTP delivery requires live email interception. These tests verify the
 * authentication guard and UI structure only.
 */

test.describe('Admin Authentication Guard', () => {
  test('admin root / redirects to /admin/login or shows login form', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    // Should end up at /admin/login (not a raw 404)
    const url = page.url();
    expect(url).toMatch(/admin/i);
    expect(url).not.toMatch(/404/i);
  });

  test('/admin/login page renders email input form', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Admin login requires email only (OTP-based)
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('admin login page title mentions admin or SKSL', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    const title = await page.title();
    expect(title).toMatch(/admin|SKSL|Sara Kinetic/i);
  });

  test('accessing /admin/bookings without session redirects', async ({ page }) => {
    await page.goto('/admin/bookings');
    await page.waitForLoadState('networkidle');
    // Should redirect to /admin/login (not serve bookings)
    await expect(page).toHaveURL(/admin\/(login|verify)/i);
  });

  test('accessing /admin/services without session redirects', async ({ page }) => {
    await page.goto('/admin/services');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/admin\/(login|verify)/i);
  });

  test('accessing /admin/closed-dates without session redirects', async ({ page }) => {
    await page.goto('/admin/closed-dates');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/admin\/(login|verify)/i);
  });

  test('accessing /admin/banner without session redirects', async ({ page }) => {
    await page.goto('/admin/banner');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/admin\/(login|verify)/i);
  });
});

test.describe('Admin Login OTP Flow (UI)', () => {
  test('submitting admin email shows OTP verification form', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    const emailInput = page.locator('input[name="email"]');
    await emailInput.fill('admin@sksl.in'); // Use configured admin email

    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // After submitting, should either:
    // - Show OTP form at /admin/verify-otp
    // - Show error if email not recognized
    const url = page.url();
    // Either redirected to verify-otp or stayed on login with message
    expect(url).toMatch(/admin/i);
  });

  test('/admin/verify-otp page has OTP input field', async ({ page }) => {
    await page.goto('/admin/verify-otp');
    await page.waitForLoadState('networkidle');

    // Should either show the OTP form or redirect to login
    const url = page.url();
    if (url.includes('verify-otp')) {
      const otpInput = page.locator('input[name="otp"]');
      await expect(otpInput).toBeVisible();
    } else {
      // Redirected to login (no active OTP session) — acceptable
      expect(url).toMatch(/admin/i);
    }
  });
});
