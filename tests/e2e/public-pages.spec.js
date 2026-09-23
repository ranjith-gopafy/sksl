const { test, expect } = require('@playwright/test');

test.describe('Public Pages & Authentication Views', () => {
  test('should load services catalog page with modalities', async ({ page }) => {
    await page.goto('/services');
    await expect(page).toHaveTitle(/Services|Modality|Sara Kinetic/i);
    await expect(page.locator('h1').first()).toContainText(/Services|Modalities|Recovery/i);
  });

  test('should load legal & compliance pages', async ({ page }) => {
    const pages = [
      { path: '/privacy-policy', titleMatch: /Privacy Policy/i },
      { path: '/terms', titleMatch: /Terms/i },
      { path: '/cancellation-refund', titleMatch: /Cancellation|Refund/i },
      { path: '/contact', titleMatch: /Contact/i }
    ];

    for (const item of pages) {
      await page.goto(item.path);
      await expect(page).toHaveTitle(item.titleMatch);
    }
  });

  test('should render customer login and register pages with form controls', async ({ page }) => {
    // Login page
    await page.goto('/login');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    // Register page
    await page.goto('/register');
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });
});
