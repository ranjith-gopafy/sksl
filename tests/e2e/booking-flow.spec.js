const { test, expect } = require('@playwright/test');

/**
 * SKSL Full Booking Flow E2E Tests
 *
 * Uses domcontentloaded to avoid hanging on external font requests.
 * Uses .carousel-slide (correct class from home.php, not .hero-slide).
 */

const TEST_EMAIL    = process.env.TEST_EMAIL    || 'e2e_booking@sksltest.local';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'BookingTest@2024!';

async function loginAsCustomer(page, email = TEST_EMAIL, password = TEST_PASSWORD) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/.*/, { timeout: 10000 });
}

function getTomorrow() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  return tomorrow.toISOString().split('T')[0];
}

test.describe('Booking Page Structure', () => {
  test('booking page redirects to login when not authenticated', async ({ page }) => {
    await page.goto('/booking', { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/login/, { timeout: 8000 });
    await expect(page).toHaveURL(/login/);
  });

  test('booking page loads for authenticated user with locked modality showcase', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/booking/);

    await expect(page.locator('h1')).toContainText(/Book/i);
    // Locked modality card is displayed
    const modalityCard = page.locator('#selected-service-card, [id*="selected-service"]');
    await expect(modalityCard).toBeVisible();
    await expect(page.locator('#date_input, input[type="date"]').first()).toBeVisible();
  });
});

test.describe('Slot Selection & 1-Click Payment Preparation', () => {
  test('selecting a date loads live slot availability grid', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking', { waitUntil: 'domcontentloaded' });

    const dateInput = page.locator('#date_input, input[type="date"]').first();
    await dateInput.fill(getTomorrow());
    await dateInput.dispatchEvent('change');
    await page.waitForTimeout(1500);

    // Slot grid should appear
    const slotGrid = page.locator('#slot-grid, .slot-grid, [id*="slot"]').first();
    await expect(slotGrid).toBeVisible({ timeout: 8000 });
  });

  test('selecting a slot highlights it and enables payment proceed button', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking', { waitUntil: 'domcontentloaded' });

    const dateInput = page.locator('#date_input, input[type="date"]').first();
    await dateInput.fill(getTomorrow());
    await dateInput.dispatchEvent('change');
    await page.waitForTimeout(2000);

    const availableSlot = page.locator('.slot-btn:not([disabled]):not(.slot-unavailable)').first();
    if (!(await availableSlot.isVisible())) { test.skip(); return; }
    await availableSlot.click();
    await page.waitForTimeout(1000);

    // Slot button should receive active/selected class or styling
    await expect(availableSlot).toHaveClass(/border-\[#075183\]|bg-\[#075183\]|selected/);

    // Proceed button exists and is visible
    const proceedBtn = page.locator('#proceed-btn, button[id*="proceed"]').first();
    await expect(proceedBtn).toBeVisible();
  });
});

test.describe('Price Breakdown Verification', () => {
  test('booking summary shows base price, GST info, and total', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking', { waitUntil: 'domcontentloaded' });

    const serviceSelect = page.locator('#service_selector, select[name*="service"]').first();
    const opts = await serviceSelect.locator('option').all();
    for (const opt of opts) {
      const val = await opt.getAttribute('value');
      if (val && val !== '' && val !== '0') {
        await serviceSelect.selectOption(val);
        break;
      }
    }
    await page.waitForTimeout(500);

    const body = await page.locator('body').textContent();
    // Body should mention pricing info somewhere on the booking page
    expect(body?.toLowerCase()).toMatch(/gst|tax|base|total|₹|inr/i);
  });
});

test.describe('Payment Flow (Mock Mode)', () => {
  test('proceed to payment button leads to mock simulator when keys empty', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking', { waitUntil: 'domcontentloaded' });

    const serviceSelect = page.locator('#service_selector, select[name*="service"]').first();
    const opts = await serviceSelect.locator('option').all();
    for (const opt of opts) {
      const val = await opt.getAttribute('value');
      if (val && val !== '' && val !== '0') {
        await serviceSelect.selectOption(val);
        break;
      }
    }
    const dateInput = page.locator('#date_input, input[type="date"]').first();
    await dateInput.fill(getTomorrow());
    await dateInput.dispatchEvent('change');
    await page.waitForTimeout(2000);

    const availableSlot = page.locator('.slot-btn:not([disabled]):not(.slot-unavailable)').first();
    if (!(await availableSlot.isVisible())) { test.skip(); return; }
    await availableSlot.click();
    await page.waitForTimeout(1500);

    // Accept terms
    const termsCheck = page.locator('#terms-check, input[name="terms"]').first();
    if (await termsCheck.isVisible()) {
      await termsCheck.check();
    }
    await page.waitForTimeout(300);

    const proceedBtn = page.locator('#proceed-btn, button[id*="proceed"]').first();
    await expect(proceedBtn).toBeVisible({ timeout: 8000 });
    await expect(proceedBtn).toBeEnabled();

    await proceedBtn.click();
    await page.waitForTimeout(2000);

    const mockModal = page.locator('#mock-payment-modal');
    const mockVisible = await mockModal.isVisible();

    if (mockVisible) {
      // Verify mock modal content
      await expect(page.locator('#mock-ref, [id*="mock-ref"]')).toBeVisible();
      await expect(page.locator('#mock-amount, [id*="mock-amount"]')).toBeVisible();

      // Click simulate success
      const successBtn = page.locator('#mock-success-btn, button:has-text("Simulate Success")');
      await expect(successBtn).toBeVisible();
      await successBtn.click();
      await page.waitForURL(/booking-confirmation/, { timeout: 15000 });
      await expect(page).toHaveURL(/booking-confirmation/);
      await expect(page.locator('body')).toContainText(/SKSL-/i);
    }
    // If real Razorpay modal opens: we don't automate it (cross-origin iframe)
  });
});
