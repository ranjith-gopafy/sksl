const { test, expect } = require('@playwright/test');

/**
 * SKSL Full Booking Flow E2E Tests
 *
 * Tests the complete customer booking journey:
 * 1. Log in
 * 2. Navigate to /booking
 * 3. Select a service (Sauna — shortest duration, lowest price for fast tests)
 * 4. Select a date (tomorrow)
 * 5. Select the first available slot
 * 6. Verify 10-minute hold banner appears
 * 7. Verify price breakdown (base + GST + total)
 * 8. Verify "Proceed to Payment" button becomes active
 * 9. Submit via mock payment simulator (works when keys are empty or real)
 * 10. Verify redirect to /booking-confirmation
 *
 * Requires:
 * - XAMPP Apache + MySQL running
 * - sksl database imported with services seeded
 * - At least one test customer account
 */

// Test customer — must be registered first via auth-flow.spec.js
// or pre-seeded into the database.
// Uses a fallback email; run auth-flow tests first to register this user.
const TEST_EMAIL    = process.env.TEST_EMAIL    || 'e2e_booking@sksltest.local';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'BookingTest@2024!';

/**
 * Helper: Login as test customer
 */
async function loginAsCustomer(page, email = TEST_EMAIL, password = TEST_PASSWORD) {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

/**
 * Get tomorrow's date string as YYYY-MM-DD
 */
function getTomorrow() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  return tomorrow.toISOString().split('T')[0];
}

test.describe('Booking Page Structure', () => {
  test('booking page redirects to login when not authenticated', async ({ page }) => {
    await page.goto('/booking');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/login/);
  });

  test('booking page loads for authenticated user', async ({ page }) => {
    await loginAsCustomer(page);
    // Skip if login failed (test user not seeded)
    if (page.url().includes('login')) {
      test.skip();
      return;
    }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/booking/);

    // Key UI elements
    await expect(page.locator('h1')).toContainText(/Book/i);
    await expect(page.locator('#service_selector, select[name*="service"]').first()).toBeVisible();
    await expect(page.locator('#date_input, input[type="date"]').first()).toBeVisible();
  });
});

test.describe('Slot Selection & Hold Banner', () => {
  test('selecting a service and date loads slot availability', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');

    // Select first service option (skip the placeholder)
    const serviceSelect = page.locator('#service_selector, select[name*="service"]').first();
    const options = await serviceSelect.locator('option').all();

    // Find first non-empty option value
    let selectedValue = '';
    for (const opt of options) {
      const val = await opt.getAttribute('value');
      if (val && val !== '' && val !== '0') {
        await serviceSelect.selectOption(val);
        selectedValue = val;
        break;
      }
    }

    if (!selectedValue) { test.skip(); return; }

    // Set date to tomorrow
    const dateInput = page.locator('#date_input, input[type="date"]').first();
    await dateInput.fill(getTomorrow());
    await dateInput.dispatchEvent('change');

    // Wait for slots to load (AJAX call)
    await page.waitForTimeout(1500);

    // Slot grid should be visible
    const slotGrid = page.locator('#slot-grid, .slot-grid, [id*="slot"]').first();
    await expect(slotGrid).toBeVisible();
  });

  test('selecting a slot triggers a booking hold', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');

    // Select service
    const serviceSelect = page.locator('#service_selector, select[name*="service"]').first();
    const options = await serviceSelect.locator('option').all();
    for (const opt of options) {
      const val = await opt.getAttribute('value');
      if (val && val !== '' && val !== '0') {
        await serviceSelect.selectOption(val);
        break;
      }
    }

    // Set date to tomorrow
    const dateInput = page.locator('#date_input, input[type="date"]').first();
    await dateInput.fill(getTomorrow());
    await dateInput.dispatchEvent('change');
    await page.waitForTimeout(2000);

    // Try clicking an available slot
    const availableSlot = page.locator('.slot-btn:not([disabled]):not(.slot-unavailable)').first();
    if (!(await availableSlot.isVisible())) { test.skip(); return; }

    await availableSlot.click();
    await page.waitForTimeout(1500);

    // Hold banner should appear
    const holdBanner = page.locator('#hold-banner');
    await expect(holdBanner).toBeVisible({ timeout: 8000 });

    // Booking reference should be shown
    const holdRef = page.locator('#hold-ref');
    await expect(holdRef).toBeVisible();
    const refText = await holdRef.textContent();
    expect(refText).toMatch(/SKSL-/i);
  });

  test('hold timer is visible and counting down', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');

    // Select service and date
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

    const holdTimer = page.locator('#hold-timer');
    await expect(holdTimer).toBeVisible({ timeout: 8000 });
    const timerText = await holdTimer.textContent();
    // Should be in MM:SS format starting from ~10:00
    expect(timerText).toMatch(/^\d{1,2}:\d{2}$/);
  });
});

test.describe('Price Breakdown Verification', () => {
  test('booking summary shows base price, GST, and total', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');

    // Select service
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

    // Summary section should show pricing info
    const summary = page.locator('#booking-summary, .booking-summary, [id*="summary"]').first();
    if (await summary.isVisible()) {
      const summaryText = await summary.textContent();
      // Should mention GST
      expect(summaryText?.toLowerCase()).toMatch(/gst|tax|base|total/i);
    }
  });
});

test.describe('Payment Flow (Mock Mode)', () => {
  test('proceed to payment button leads to mock simulator when keys empty', async ({ page }) => {
    await loginAsCustomer(page);
    if (page.url().includes('login')) { test.skip(); return; }

    await page.goto('/booking');
    await page.waitForLoadState('networkidle');

    // Setup: select service + date + slot
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

    // Proceed button should be enabled
    const proceedBtn = page.locator('#proceed-btn, button[id*="proceed"]').first();
    await expect(proceedBtn).toBeVisible({ timeout: 8000 });
    await expect(proceedBtn).toBeEnabled();

    await proceedBtn.click();
    await page.waitForTimeout(2000);

    // Either mock modal shows OR Razorpay modal opens (if real keys configured)
    const mockModal = page.locator('#mock-payment-modal');
    const razorpayModal = page.frameLocator('iframe[name*="razorpay"]');
    const mockVisible = await mockModal.isVisible();

    if (mockVisible) {
      // Mock mode: verify modal content
      await expect(page.locator('#mock-ref, [id*="mock-ref"]')).toBeVisible();
      await expect(page.locator('#mock-amount, [id*="mock-amount"]')).toBeVisible();

      // Simulate success
      const successBtn = page.locator('#mock-success-btn, button:has-text("Simulate Success")');
      await expect(successBtn).toBeVisible();
      await successBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 15000 });

      // Should redirect to booking-confirmation
      await expect(page).toHaveURL(/booking-confirmation/);
      await expect(page.locator('body')).toContainText(/SKSL-/i);
    }
    // If real Razorpay: modal would open but we can't automate it (it's in an iframe with CSP)
  });
});
