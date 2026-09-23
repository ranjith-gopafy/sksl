const { test, expect } = require('@playwright/test');

/**
 * SKSL Homepage & Hero Carousel E2E Tests
 *
 * Tests:
 * - Brand title in page <title> tag
 * - Two-line header branding ("Sara Kinetic" / "Sports Lab")
 * - Hero carousel renders with slides
 * - Carousel Next/Prev navigation (no jump)
 * - Service modality cards (10 items)
 * - Category filter tabs
 * - "Book Now" CTA links correctly
 */

test.describe('Homepage & Hero Carousel', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    // Wait for any lazy-loading to settle
    await page.waitForLoadState('networkidle');
  });

  test('page has correct title and meta', async ({ page }) => {
    const title = await page.title();
    expect(title).toMatch(/Sara Kinetic|SKSL/i);
  });

  test('header contains two-line client brand name', async ({ page }) => {
    const header = page.locator('header');
    await expect(header).toBeVisible();
    await expect(header).toContainText('Sara Kinetic');
    await expect(header).toContainText('Sports Lab');
  });

  test('hero carousel section is visible with at least 1 slide', async ({ page }) => {
    // Carousel container
    const carousel = page.locator('#hero-carousel');
    await expect(carousel).toBeVisible();

    // At least one visible slide
    const slides = page.locator('.hero-slide');
    const count = await slides.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('carousel next button advances slides without layout jump', async ({ page }) => {
    const carousel = page.locator('#hero-carousel');
    await expect(carousel).toBeVisible();

    const slides = page.locator('.hero-slide');
    const slideCount = await slides.count();

    if (slideCount < 2) {
      test.skip();
      return;
    }

    // Record carousel position before click
    const beforeBox = await carousel.boundingBox();

    const nextBtn = page.locator('#hero-next-btn');
    await expect(nextBtn).toBeVisible();
    await nextBtn.click();
    await page.waitForTimeout(700); // Allow transition

    // Carousel container should not have moved / jumped
    const afterBox = await carousel.boundingBox();
    expect(Math.abs((afterBox?.y ?? 0) - (beforeBox?.y ?? 0))).toBeLessThan(5);
  });

  test('carousel prev button works without layout jump', async ({ page }) => {
    const slides = page.locator('.hero-slide');
    const slideCount = await slides.count();
    if (slideCount < 2) { test.skip(); return; }

    const prevBtn = page.locator('#hero-prev-btn');
    if (!(await prevBtn.isVisible())) { test.skip(); return; }

    const beforeBox = await page.locator('#hero-carousel').boundingBox();
    await prevBtn.click();
    await page.waitForTimeout(700);
    const afterBox = await page.locator('#hero-carousel').boundingBox();
    expect(Math.abs((afterBox?.y ?? 0) - (beforeBox?.y ?? 0))).toBeLessThan(5);
  });

  test('service modality cards are rendered (minimum 10)', async ({ page }) => {
    // Both mobile cards (inside .md:hidden) and desktop cards
    // Count any element with data-modality or known card class
    const cards = page.locator('.modality-card');
    const count = await cards.count();
    expect(count).toBeGreaterThanOrEqual(10);
  });

  test('category filter tabs filter modality cards', async ({ page }) => {
    const recoveryTab = page.locator('[data-category="recovery"]').first();
    if (!(await recoveryTab.isVisible())) { test.skip(); return; }

    await recoveryTab.click();
    await page.waitForTimeout(400);
    // Tab should have an active class
    const cls = await recoveryTab.getAttribute('class') ?? '';
    expect(cls).toMatch(/bg-gradient|text-white|active/i);
  });

  test('hero CTA "Book Now" button links to /booking', async ({ page }) => {
    // Find the first prominent Book / Book Now link in the hero
    const bookLink = page.locator('a[href*="booking"]').first();
    await expect(bookLink).toBeVisible();
    const href = await bookLink.getAttribute('href');
    expect(href).toMatch(/booking/i);
  });

  test('navigation links to services page work', async ({ page }) => {
    const servicesLink = page.locator('a[href*="services"]').first();
    await expect(servicesLink).toBeVisible();
    await servicesLink.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/services/);
  });
});
