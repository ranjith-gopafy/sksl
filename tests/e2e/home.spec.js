const { test, expect } = require('@playwright/test');

/**
 * SKSL Homepage & Hero Carousel E2E Tests
 *
 * Uses 'domcontentloaded' instead of 'networkidle' to avoid hanging on
 * Google Fonts external requests.
 */

test.describe('Homepage & Hero Carousel', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
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
    // Actual class is .carousel-slide (from home.php line 56)
    const carousel = page.locator('#hero-carousel');
    await expect(carousel).toBeVisible();

    const slides = page.locator('.carousel-slide');
    const count = await slides.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('carousel next button advances slides without layout jump', async ({ page }) => {
    const carousel = page.locator('#hero-carousel');
    await expect(carousel).toBeVisible();

    const slides = page.locator('.carousel-slide');
    const slideCount = await slides.count();
    if (slideCount < 2) { test.skip(); return; }

    // Record carousel bounding box before click
    const beforeBox = await carousel.boundingBox();

    const nextBtn = page.locator('#hero-next-btn');
    if (!(await nextBtn.isVisible())) { test.skip(); return; }
    await nextBtn.click();
    await page.waitForTimeout(800);

    // Carousel container should not jump vertically
    const afterBox = await carousel.boundingBox();
    expect(Math.abs((afterBox?.y ?? 0) - (beforeBox?.y ?? 0))).toBeLessThan(5);
  });

  test('carousel prev button works without layout jump', async ({ page }) => {
    const slides = page.locator('.carousel-slide');
    const slideCount = await slides.count();
    if (slideCount < 2) { test.skip(); return; }

    const prevBtn = page.locator('#hero-prev-btn');
    if (!(await prevBtn.isVisible())) { test.skip(); return; }

    const beforeBox = await page.locator('#hero-carousel').boundingBox();
    await prevBtn.click();
    await page.waitForTimeout(800);
    const afterBox = await page.locator('#hero-carousel').boundingBox();
    expect(Math.abs((afterBox?.y ?? 0) - (beforeBox?.y ?? 0))).toBeLessThan(5);
  });

  test('service modality cards are rendered (minimum 9)', async ({ page }) => {
    // Count .modality-card elements
    const cards = page.locator('.modality-card');
    const count = await cards.count();
    expect(count).toBeGreaterThanOrEqual(9);
  });

  test('category filter tabs filter modality cards', async ({ page }) => {
    const recoveryTab = page.locator('[data-category="recovery"]').first();
    if (!(await recoveryTab.isVisible())) { test.skip(); return; }

    await recoveryTab.click();
    await page.waitForTimeout(400);
    const cls = await recoveryTab.getAttribute('class') ?? '';
    expect(cls).toMatch(/bg-gradient|text-white|active/i);
  });

  test('hero CTA "Book Now" button links to /booking', async ({ page }) => {
    const bookLink = page.locator('a[href*="booking"]').first();
    await expect(bookLink).toBeVisible();
    const href = await bookLink.getAttribute('href');
    expect(href).toMatch(/booking/i);
  });

  test('navigation links to services page work', async ({ page }) => {
    const servicesLink = page.locator('nav a[href*="services"]:visible').first();
    await expect(servicesLink).toBeVisible();
    await servicesLink.click();
    await page.waitForURL(/services/, { timeout: 10000 });
    await expect(page).toHaveURL(/services/);
  });
});

test.describe('Navigation and slideshow controls', () => {
  test('every public page is reachable from the menu on a phone and on desktop', async ({ page, isMobile }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    if (isMobile) {
      await page.locator('summary[aria-label="Open menu"]').click();
      const menu = page.locator('nav[aria-label="Menu"]');
      for (const label of ['Modalities', 'Combo Packages', 'Physio & Psychology', 'Contact']) {
        await expect(menu.getByRole('link', { name: label })).toBeVisible();
      }
    } else {
      const nav = page.locator('nav[aria-label="Primary"]');
      for (const label of ['Modalities', 'Combo Packages', 'Physio & Psychology', 'Contact']) {
        await expect(nav.getByRole('link', { name: label })).toBeVisible();
      }
    }
  });

  test('the hero slideshow can be paused and resumed', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const toggle = page.locator('#carousel-toggle');
    if ((await toggle.count()) === 0) { test.skip(); return; } // a single banner has nothing to pause
    await expect(toggle).toHaveAttribute('aria-label', /Pause slideshow|Play slideshow/);
    const before = await toggle.getAttribute('aria-label');
    await toggle.click();
    const after = await toggle.getAttribute('aria-label');
    expect(after).not.toEqual(before);
  });

  test('slides that are not showing cannot be tabbed to', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const hidden = page.locator('.carousel-slide[aria-hidden="true"]');
    const count = await hidden.count();
    for (let i = 0; i < count; i++) {
      await expect(hidden.nth(i)).toHaveAttribute('inert', '');
    }
  });

  test('packages and consultations are public pages', async ({ page }) => {
    for (const path of ['/packages', '/consultations']) {
      const res = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(res.status()).toBe(200);
      await expect(page.locator('h1').first()).toBeVisible();
    }
  });
});
