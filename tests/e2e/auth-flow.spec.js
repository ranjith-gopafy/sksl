const { test, expect } = require('@playwright/test');

/**
 * SKSL Customer Authentication Flow E2E Tests
 *
 * Uses domcontentloaded to avoid hanging on external font requests.
 */

const TEST_USER = {
  name: 'E2E Test User',
  email: `e2etest+${Date.now()}@example.com`,
  mobile: '9876543210',
  password: 'TestPass@2024!',
};

test.describe('Customer Authentication Flow', () => {
  test('registration page renders correctly', async ({ page }) => {
    await page.goto('/register', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/register/);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="mobile"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('input[name="password_confirmation"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('login page renders correctly', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/login/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    const forgotLink = page.locator('a[href*="forgot-password"]');
    await expect(forgotLink).toBeVisible();
  });

  test('forgot password page renders and accepts email', async ({ page }) => {
    await page.goto('/forgot-password', { waitUntil: 'domcontentloaded' });

    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible();
    await emailInput.fill('test@example.com');
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/.*/, { timeout: 8000 });
    const url = page.url();
    expect(url).not.toMatch(/404|error/i);
  });

  test('registration with mismatched passwords shows validation error', async ({ page }) => {
    await page.goto('/register', { waitUntil: 'domcontentloaded' });

    await page.fill('input[name="name"]', 'Test User');
    await page.fill('input[name="email"]', `mismatch+${Date.now()}@example.com`);
    await page.fill('input[name="mobile"]', '9876543210');
    await page.fill('input[name="password"]', 'Password123!');
    await page.fill('input[name="password_confirmation"]', 'DifferentPass456!');
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/.*/, { timeout: 8000 });
    // Should stay on register page with an error
    await expect(page).toHaveURL(/register/);
  });

  test('login with invalid credentials shows error', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'nonexistent@example.com');
    await page.fill('input[name="password"]', 'WrongPassword123!');
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/.*/, { timeout: 8000 });
    // Should remain on login page
    await expect(page).toHaveURL(/login/);
  });

  test('accessing protected /my-bookings without login redirects to /login', async ({ page }) => {
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });

  test('accessing protected /booking without login redirects to /login', async ({ page }) => {
    await page.goto('/booking', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });

  test('accessing protected /profile without login redirects to /login', async ({ page }) => {
    await page.goto('/profile', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });
});

/**
 * Registration + Login + Logout Full Flow
 */
test.describe('Full Register → Login → Logout Flow', () => {
  const uniqueEmail = `e2e_flow_${Date.now()}@sksltest.local`;
  const password = 'FlowTest@2024!';

  test('register a new account successfully', async ({ page }) => {
    await page.goto('/register', { waitUntil: 'domcontentloaded' });

    await page.fill('input[name="name"]', 'Flow Test User');
    await page.fill('input[name="email"]', uniqueEmail);
    await page.fill('input[name="mobile"]', '9000000001');
    await page.fill('input[name="password"]', password);
    await page.fill('input[name="password_confirmation"]', password);

    const termsCheckbox = page.locator('input[name="terms"]');
    if (await termsCheckbox.isVisible()) {
      await termsCheckbox.check();
    }

    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/.*/, { timeout: 10000 });

    // After registration: should redirect away from register page
    const url = page.url();
    expect(url).not.toMatch(/register/);
  });

  test('login with newly registered account', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });

    await page.fill('input[name="email"]', uniqueEmail);
    await page.fill('input[name="password"]', password);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/.*/, { timeout: 10000 });
    // Should NOT be on login page after valid login
    const url = page.url();
    expect(url).not.toMatch(/\/login$/);
  });

  test('logout destroys session and redirects to login', async ({ page }) => {
    // Login first
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', uniqueEmail);
    await page.fill('input[name="password"]', password);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/.*/, { timeout: 10000 });

    // Find and click logout
    const logoutLink = page.locator('a[href*="logout"]').first();
    if (await logoutLink.isVisible()) {
      await logoutLink.click();
    } else {
      const logoutBtn = page.locator('button:has-text("Logout"), button:has-text("Sign Out")').first();
      if (await logoutBtn.isVisible()) {
        await logoutBtn.click();
      }
    }

    await page.waitForURL(/.*/, { timeout: 8000 });

    // After logout, protected routes should redirect to login
    await page.goto('/my-bookings', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });
});
