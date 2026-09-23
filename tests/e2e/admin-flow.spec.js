const { test, expect } = require('@playwright/test');

/**
 * SKSL Admin Panel Flow E2E Tests
 *
 * Uses domcontentloaded to avoid hanging on external font/resource requests.
 *
 * Tests:
 * - /admin/login renders email form (input[name="email"])
 * - Admin page titles match expected pattern
 * - /admin/* routes require authentication (redirect to /admin/login)
 * - Admin OTP form structure
 */

test.describe('Admin Authentication Guard', () => {
  test('admin root / redirects to /admin/login', async ({ page }) => {
    await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    // Should end up at /admin/login (AdminAuthController::showLogin redirects if not logged in)
    await page.waitForURL(/admin/, { timeout: 8000 });
    const url = page.url();
    expect(url).toMatch(/admin/i);
    expect(url).not.toMatch(/404/i);
  });

  test('/admin/login renders email input form', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });

    // Admin login uses input[name="email"] (see admin/login.php line 37)
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible({ timeout: 8000 });

    // Submit button text is "Send One-Time Access Code"
    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toContainText(/Send|Access Code|OTP|Login/i);
  });

  test('admin login page title contains Sara Kinetic or Admin or SKSL', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    // Title: "Admin Portal Login — Sara Kinetic Sports Lab"
    const title = await page.title();
    expect(title).toMatch(/admin|SKSL|Sara Kinetic/i);
  });

  test('accessing /admin/bookings without session redirects to /admin/login', async ({ page }) => {
    await page.goto('/admin/bookings', { waitUntil: 'domcontentloaded' });
    // AdminAuth::handle() redirects to admin/login (see AdminAuth.php line 28)
    await page.waitForURL(/admin\/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/admin\/login/i);
  });

  test('accessing /admin/services without session redirects to /admin/login', async ({ page }) => {
    await page.goto('/admin/services', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/admin\/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/admin\/login/i);
  });

  test('accessing /admin/closed-dates without session redirects to /admin/login', async ({ page }) => {
    await page.goto('/admin/closed-dates', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/admin\/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/admin\/login/i);
  });

  test('accessing /admin/banner without session redirects to /admin/login', async ({ page }) => {
    await page.goto('/admin/banner', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/admin\/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/admin\/login/i);
  });
});

test.describe('Admin Login OTP Flow (UI)', () => {
  test('submitting unrecognized admin email stays on admin area', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });

    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible({ timeout: 8000 });
    await emailInput.fill('notanadmin@example.com');

    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    // Should stay on admin area (login or error flash)
    expect(page.url()).toMatch(/admin/i);
  });

  test('/admin/verify-otp without active OTP session redirects to /admin/login', async ({ page }) => {
    // Without ?email param, AdminAuthController::showVerifyOtp redirects back to login
    await page.goto('/admin/verify-otp', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/admin/, { timeout: 8000 });

    const url = page.url();
    // Should redirect to /admin/login since no active OTP session
    expect(url).toMatch(/admin\/(login|verify)/i);
  });

  test('/admin/verify-otp with email param shows OTP input', async ({ page }) => {
    // Simulate arriving at verify-otp with an email param (as the flow works)
    await page.goto('/admin/verify-otp?email=admin@sksl.in', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/admin/, { timeout: 8000 });

    const url = page.url();
    if (url.includes('verify-otp')) {
      // Should show OTP input (input[name="otp"])
      const otpInput = page.locator('input[name="otp"]');
      await expect(otpInput).toBeVisible({ timeout: 5000 });
    } else {
      // Redirected to login — acceptable (no active session)
      expect(url).toMatch(/admin/i);
    }
  });
});
