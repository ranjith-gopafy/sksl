const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Screen sizes and accessibility.
 *
 *  1. Every page, at every common screen width from a small phone (320px) to a wide monitor (1920px),
 *     must not scroll sideways and must not push any element outside the screen.
 *  2. axe-core (WCAG 2.0/2.1/2.2 A and AA rules: colour contrast, names for buttons, labels for fields,
 *     headings, landmarks, ARIA) is run on public, customer and admin pages on a phone and a desktop.
 *  3. Keyboard: the page can be used without a mouse (focus is visible, menus and dialogs close with Esc).
 *
 * Runs once (Desktop Chromium project); the widths are set by the tests themselves.
 */

const custHelper = path.join(__dirname, '..', 'support', 'customer_session.php');
const adminHelper = path.join(__dirname, '..', 'support', 'admin_session.php');
const run = (file, ...args) => execFileSync('php', [file, ...args], { encoding: 'utf8' }).trim();
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const WIDTHS = [[320, 640], [375, 812], [768, 1024], [1024, 768], [1280, 800], [1920, 1080]];
const PUBLIC = ['/', '/services', '/packages', '/contact', '/login', '/register', '/forgot-password', '/privacy-policy', '/terms', '/cancellation-refund', '/booking', '/consultations'];
const CUSTOMER = ['/my-bookings', '/my-bookings?tab=all', '/my-packages', '/profile'];
const ADMIN = ['/admin/dashboard', '/admin/bookings?status=all', '/admin/services', '/admin/availability', '/admin/manual-blocks', '/admin/packages', '/admin/package-purchases', '/admin/closed-dates', '/admin/banner'];

let cust, admin;
test.beforeAll(() => {
  cust = JSON.parse(run(custHelper, 'create'));
  run(custHelper, 'booking', String(cust.id), '60', 'paid');
  run(custHelper, 'booking', String(cust.id), '90', 'package');
  admin = JSON.parse(run(adminHelper, 'create'));
});
test.afterAll(() => {
  if (cust) { run(custHelper, 'cleanup', String(cust.id)); }
  if (admin) { run(adminHelper, 'cleanup', String(admin.id)); }
});
test.beforeEach(({}, info) => { test.skip(info.project.name !== 'Desktop Chromium', 'sizes are set inside the tests'); });

async function signInCustomer(page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', cust.email);
  await page.fill('input[name="password"]', cust.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 15000 });
}
async function signInAdmin(page) {
  run(adminHelper, 'reset-limits');
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', admin.email);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/verify-otp/);
  run(adminHelper, 'otp', String(admin.id));
  await page.fill('input[name="otp"]', '246810');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL(/admin\/dashboard/, { timeout: 15000 });
}

/** Elements that stick out of the screen (ignoring ones inside a deliberately scrolling box). */
async function offenders(page) {
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const out = [];
    const scrolls = (el) => { for (let p = el.parentElement; p; p = p.parentElement) { const o = getComputedStyle(p).overflowX; if ((o === 'auto' || o === 'scroll' || o === 'hidden') && p.scrollWidth > p.clientWidth) { return true; } } return false; };
    for (const el of document.querySelectorAll('body *')) {
      if (el.closest('#toast-container')) { continue; } // toasts slide in from the side by design
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || cs.position === 'fixed' && el.getBoundingClientRect().width === 0) { continue; }
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) { continue; }
      if ((r.right > vw + 1 || r.left < -1) && !scrolls(el) && cs.position !== 'fixed') {
        out.push(`${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}.${String(el.className).split(' ').slice(0, 3).join('.')} [${Math.round(r.left)}..${Math.round(r.right)}] of ${vw}`);
        if (out.length >= 5) { break; }
      }
    }
    return out;
  });
}

async function axeViolations(page, extra = {}) {
  await page.evaluate(axeSource);
  return page.evaluate(async (opts) => {
    const res = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] }, resultTypes: ['violations'], ...opts });
    return res.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 3).map((n) => n.target.join(' ') + ' :: ' + (n.failureSummary || '').split('\n').slice(1, 2).join(' ').trim()) }));
  }, extra);
}
const show = (v) => v.map((x) => `${x.impact} ${x.id}: ${x.help}\n    ${x.nodes.join('\n    ')}`).join('\n');

test.describe('Screen sizes: no sideways scrolling, nothing off-screen', () => {
  test.setTimeout(240000);
  for (const [w, h] of WIDTHS) {
    test(`public pages at ${w}px`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      const bad = [];
      for (const url of PUBLIC) {
        await page.goto(url, { waitUntil: 'load' });
        const wide = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (wide > 1) { bad.push(`${url}: page is ${wide}px wider than the screen; ${(await offenders(page)).join(' | ')}`); continue; }
        const off = await offenders(page);
        if (off.length) { bad.push(`${url}: ${off.join(' | ')}`); }
      }
      expect(bad, bad.join('\n')).toEqual([]);
    });
  }

  for (const [w, h] of [[320, 640], [768, 1024], [1280, 800]]) {
    test(`signed-in customer pages at ${w}px`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await signInCustomer(page);
      const bad = [];
      for (const url of CUSTOMER) {
        await page.goto(url, { waitUntil: 'load' });
        const wide = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        const off = await offenders(page);
        if (wide > 1 || off.length) { bad.push(`${url}: +${wide}px ${off.join(' | ')}`); }
      }
      expect(bad, bad.join('\n')).toEqual([]);
    });

    test(`admin pages at ${w}px`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await signInAdmin(page);
      const bad = [];
      for (const url of ADMIN) {
        await page.goto(url, { waitUntil: 'load' });
        const wide = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        const off = await offenders(page);
        if (wide > 1 || off.length) { bad.push(`${url}: +${wide}px ${off.join(' | ')}`); }
      }
      expect(bad, bad.join('\n')).toEqual([]);
    });
  }
});

test.describe('Accessibility (axe-core, WCAG 2.2 A/AA)', () => {
  test.setTimeout(240000);
  for (const [w, h, label] of [[390, 844, 'phone'], [1280, 800, 'desktop']]) {
    test(`public pages on a ${label}`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      const bad = [];
      for (const url of PUBLIC) {
        await page.goto(url, { waitUntil: 'load' });
        const v = await axeViolations(page);
        if (v.length) { bad.push(`${url}\n  ${show(v)}`); }
      }
      expect(bad, bad.join('\n')).toEqual([]);
    });

    test(`customer pages and the cancel dialog on a ${label}`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await signInCustomer(page);
      const bad = [];
      for (const url of CUSTOMER) {
        await page.goto(url, { waitUntil: 'load' });
        const v = await axeViolations(page);
        if (v.length) { bad.push(`${url}\n  ${show(v)}`); }
      }
      await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'load' });
      await page.getByRole('button', { name: 'Cancel session' }).first().click();
      await expect(page.locator('#cancel-dialog')).toBeVisible();
      const v = await axeViolations(page);
      if (v.length) { bad.push(`cancel dialog\n  ${show(v)}`); }
      expect(bad, bad.join('\n')).toEqual([]);
    });

    test(`admin pages and pop-ups on a ${label}`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await signInAdmin(page);
      const bad = [];
      for (const url of ADMIN) {
        await page.goto(url, { waitUntil: 'load' });
        const v = await axeViolations(page);
        if (v.length) { bad.push(`${url}\n  ${show(v)}`); }
      }
      await page.goto('/admin/services', { waitUntil: 'load' });
      await page.locator('.btn-edit-service').first().click();
      await expect(page.locator('#edit-service-modal')).toBeVisible();
      let v = await axeViolations(page);
      if (v.length) { bad.push(`edit pop-up\n  ${show(v)}`); }
      await page.goto('/admin/services', { waitUntil: 'load' });
      await page.locator('.btn-durations-service').first().click();
      await expect(page.locator('#durations-modal')).toBeVisible();
      v = await axeViolations(page);
      if (v.length) { bad.push(`durations pop-up\n  ${show(v)}`); }
      expect(bad, bad.join('\n')).toEqual([]);
    });
  }
});

test.describe('Keyboard use', () => {
  test('the home page can be tabbed through with a visible focus ring, and every control has a name', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/', { waitUntil: 'load' });
    const seen = [];
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press('Tab');
      seen.push(await page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) { return null; }
        const cs = getComputedStyle(el);
        const ring = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0 || cs.boxShadow !== 'none';
        const name = (el.getAttribute('aria-label') || el.textContent || el.getAttribute('title') || el.getAttribute('alt') || '').trim();
        return { tag: el.tagName, ring, name };
      }));
    }
    const real = seen.filter(Boolean);
    expect(real.length, 'Tab should move through controls').toBeGreaterThan(5);
    expect(real.filter((s) => !s.ring).map((s) => s.tag + ':' + s.name), 'every focused control needs a visible focus indicator').toEqual([]);
    expect(real.filter((s) => s.name === '').map((s) => s.tag), 'every focused control needs a name').toEqual([]);
  });

  test('the first Tab offers a skip-to-content link', async ({ page }) => {
    await page.goto('/', { waitUntil: 'load' });
    await page.keyboard.press('Tab');
    const first = await page.evaluate(() => { const el = document.activeElement; return { text: (el.textContent || '').trim().toLowerCase(), href: el.getAttribute('href') }; });
    expect(first.text).toMatch(/skip/);
    expect(first.href).toMatch(/^#/);
  });

  test('the phone menu opens with the keyboard and closes with Escape', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/', { waitUntil: 'load' });
    const btn = page.locator('button[aria-controls][aria-expanded]').first();
    if (await btn.count() === 0) { test.skip(true, 'no expandable phone menu button'); }
    await btn.focus();
    await page.keyboard.press('Enter');
    await expect(btn).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(btn).toHaveAttribute('aria-expanded', 'false');
  });

  test('the cancel dialog keeps focus off the page behind it, closes with Escape and returns focus to its button', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await signInCustomer(page);
    await page.goto('/my-bookings?tab=upcoming', { waitUntil: 'load' });
    const opener = page.getByRole('button', { name: 'Cancel session' }).first();
    await opener.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#cancel-dialog')).toBeVisible();
    for (let i = 0; i < 6; i++) {
      await page.keyboard.press('Tab');
      // inside the dialog, or out in the browser's own controls (body); never on the page behind it
      expect(await page.evaluate(() => { const el = document.activeElement; return !el || el === document.body || !!el.closest('#cancel-dialog'); })).toBe(true);
    }
    await page.keyboard.press('Escape');
    await expect(page.locator('#cancel-dialog')).toBeHidden();
    expect(await page.evaluate(() => document.activeElement && document.activeElement.textContent.trim())).toBe('Cancel session');
  });

  test('the admin pop-ups close with Escape', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await signInAdmin(page);
    await page.goto('/admin/services', { waitUntil: 'load' });
    await page.locator('.btn-edit-service').first().click();
    await expect(page.locator('#edit-service-modal')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#edit-service-modal')).toBeHidden();
    await page.locator('.btn-durations-service').first().click();
    await expect(page.locator('#durations-modal')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#durations-modal')).toBeHidden();
  });
});
