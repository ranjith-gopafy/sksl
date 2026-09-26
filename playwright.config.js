const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright E2E Configuration for Sara Kinetic Sports Lab (SKSL)
 *
 * Targets: http://localhost/sksl/public/ (XAMPP) or APP_URL env variable.
 * Projects: Desktop Chromium, Mobile Pixel 7, Mobile iPhone 14.
 *
 * Run: npm run test:e2e
 * UI:  npx playwright test --ui
 * Report: npx playwright show-report
 */

module.exports = defineConfig({
  testDir: './tests/e2e',
  globalSetup: require.resolve('./tests/e2e/global-setup.js'),
  timeout: 30000,
  fullyParallel: false,        // Sequential for booking flow tests (shared DB state)
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,                  // Single worker to avoid concurrent booking conflicts in test DB
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }]
  ],
  webServer: {
    command: 'php -S localhost:8000 -t public',
    url: 'http://localhost:8000/health',
    reuseExistingServer: true,
    timeout: 15000,
  },
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 15000,
  },
  projects: [
    {
      name: 'Desktop Chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Mobile Pixel 7',
      use: {
        ...devices['Pixel 7'],
        isMobile: true,
        hasTouch: true,
      },
    },
    {
      name: 'Mobile iPhone 14',
      use: {
        ...devices['iPhone 14'],
        defaultBrowserType: 'chromium',
        isMobile: true,
        hasTouch: true,
      },
    },
  ],
});
