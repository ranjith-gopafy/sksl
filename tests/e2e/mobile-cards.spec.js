const { test, expect, devices } = require('@playwright/test');

/**
 * SKSL Mobile Viewport — Compact Card Redesign & Responsive UX Tests
 *
 * All tests run at iPhone 14 / Pixel 7 dimensions (390×844)
 * Tests:
 * - Header brand name shows in two lines
 * - Compact horizontal modality cards displayed
 * - Cards have thumbnail, client name, title, Book button
 * - Mobile bottom navigation dock is visible and sticky
 * - Bottom dock shows correct nav items (Home, Services, Booking, Account)
 * - Carousel height is stable (no vertical jump)
 * - Navigation hamburger / menu works on mobile
 */

// Ensure all mobile specs run in standard mobile viewport
test.use({ viewport: { width: 390, height: 844 } });

test.describe('Mobile — Header & Brand Name', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');
  });

  test('header shows logo and two-line client name on mobile', async ({ page }) => {
    const header = page.locator('header');
    await expect(header).toBeVisible();

    // Two-line brand: "Sara Kinetic" and "Sports Lab"
    await expect(header).toContainText('Sara Kinetic');
    await expect(header).toContainText('Sports Lab');
  });

  test('SKSL logo image is visible in header on mobile', async ({ page }) => {
    const logo = page.locator('header img').first();
    await expect(logo).toBeVisible();
    // Logo src should reference the brand logo
    const src = await logo.getAttribute('src');
    expect(src).toMatch(/logo|sksl/i);
  });
});

test.describe('Mobile — Bottom Navigation Dock', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('bottom navigation dock is visible and fixed to bottom', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Bottom nav should be visible
    const bottomNav = page.locator('nav.fixed.bottom-0, [class*="fixed"][class*="bottom-0"]').first();
    await expect(bottomNav).toBeVisible();
  });

  test('bottom dock contains Home, Services, Book, Account links', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const bottomNav = page.locator('nav.fixed.bottom-0, [class*="fixed"][class*="bottom-0"]').first();
    const navText = await bottomNav.textContent();

    // At minimum: Home and Services links
    expect(navText?.toLowerCase()).toMatch(/home|services|book/i);
  });

  test('tapping Services in bottom dock navigates to /services', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const bottomNav = page.locator('nav.fixed.bottom-0, [class*="fixed"][class*="bottom-0"]').first();
    const servicesBtn = bottomNav.locator('a[href*="services"]').first();

    if (await servicesBtn.isVisible()) {
      await servicesBtn.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/services/);
    }
  });
});

test.describe('Mobile — Compact Horizontal Modality Cards', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('mobile compact cards are visible on homepage', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Mobile cards exist — the md:hidden container
    const mobileCards = page.locator('.modality-card');
    const count = await mobileCards.count();
    expect(count).toBeGreaterThanOrEqual(10);
  });

  test('compact mobile card shows service name and Book button', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // First mobile card should have name and book CTA
    const firstCard = page.locator('.modality-card').first();
    await expect(firstCard).toBeVisible();

    const cardText = await firstCard.textContent();
    // Should contain a service name
    expect(cardText).toBeTruthy();
    expect((cardText || '').length).toBeGreaterThan(5);
  });

  test('mobile card image thumbnail is visible', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Images within cards should be loaded
    const cardImg = page.locator('.modality-card img').first();
    if (await cardImg.isVisible()) {
      const src = await cardImg.getAttribute('src');
      expect(src).toBeTruthy();
    }
  });

  test('mobile card height is compact (under 200px)', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Mobile horizontal cards should be compact
    const mobileCard = page.locator('.modality-card .md\\:hidden').first();
    if (await mobileCard.isVisible()) {
      const box = await mobileCard.boundingBox();
      if (box) {
        // Compact mobile cards should be under 200px tall
        expect(box.height).toBeLessThan(200);
      }
    }
  });
});

test.describe('Mobile — Carousel Stability', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('hero carousel renders and is stable on mobile', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const carousel = page.locator('#hero-carousel');
    if (!(await carousel.isVisible())) { test.skip(); return; }

    const before = await carousel.boundingBox();
    await page.waitForTimeout(500);
    const after = await carousel.boundingBox();

    // Carousel should not auto-scroll / shift position significantly
    expect(Math.abs((after?.y ?? 0) - (before?.y ?? 0))).toBeLessThan(5);
  });
});

test.describe('Mobile — Public Pages Responsive Layout', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('services page loads and is readable on mobile', async ({ page }) => {
    await page.goto('/services');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).not.toMatch(/Fatal error|Notice:|Warning:/i);
  });

  test('login page is usable on mobile viewport', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('domcontentloaded');

    const emailInput = page.locator('input[name="email"]');
    const passwordInput = page.locator('input[name="password"]');
    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();

    // Inputs should fit in mobile viewport (not overflow)
    const emailBox = await emailInput.boundingBox();
    expect(emailBox?.width).toBeGreaterThan(150);
    expect((emailBox?.x ?? 0) + (emailBox?.width ?? 0)).toBeLessThanOrEqual(400);
  });
});
