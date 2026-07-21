import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.PROMA_QA_BASE_URL || '').replace(/\/$/, '');
if (!baseUrl) throw new Error('PROMA_QA_BASE_URL is required.');

const viewports = [
  { name: 'mobile-360', width: 360, height: 800 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'desktop-1440', width: 1440, height: 900 },
];
const roles = {
  admin: {
    identifier: process.env.PROMA_QA_ADMIN_USER,
    password: process.env.PROMA_QA_ADMIN_PASSWORD,
    routes: ['dashboard', 'users', 'profile-reviews', 'contracts', 'medals', 'system-health', 'plugins', 'settings'],
  },
  operator: {
    identifier: process.env.PROMA_QA_OPERATOR_USER,
    password: process.env.PROMA_QA_OPERATOR_PASSWORD,
    routes: ['dashboard', 'contracts', 'overdue', 'calendar', 'profile'],
  },
  lawyer: {
    identifier: process.env.PROMA_QA_LAWYER_USER,
    password: process.env.PROMA_QA_LAWYER_PASSWORD,
    routes: ['dashboard', 'lawyer', 'calendar', 'profile'],
  },
  customer: {
    identifier: process.env.PROMA_QA_CUSTOMER_USER,
    password: process.env.PROMA_QA_CUSTOMER_PASSWORD,
    routes: ['dashboard', 'portal/contracts', 'installments/panel', 'ecommerce/landing', 'ecommerce/cart', 'profile'],
  },
};

const report = [];
const browser = await chromium.launch({ headless: true });
try {
  for (const viewport of viewports) {
    for (const [role, spec] of Object.entries(roles)) {
      if (!spec.identifier || !spec.password) throw new Error(`Missing QA credentials for ${role}.`);
      const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height }, locale: 'fa-IR' });
      const page = await context.newPage();
      const pageErrors = [];
      page.on('pageerror', error => pageErrors.push(error.message));

      await page.goto(`${baseUrl}/index.php?route=auth%2Flogin`, { waitUntil: 'networkidle' });
      await page.locator('input[name="identifier"]').fill(spec.identifier);
      await page.locator('input[name="password"]').fill(spec.password);
      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('button[type="submit"]').first().click(),
      ]);
      if (page.url().includes('route=auth')) throw new Error(`${role} could not authenticate.`);
      await page.evaluate(() => {
        const userId = document.body.getAttribute('data-user-id') || 'guest';
        localStorage.setItem('proma-tour-seen-' + userId, '1');
        const tour = document.querySelector('[data-tour]');
        if (tour) tour.hidden = true;
      });

      for (const route of spec.routes) {
        pageErrors.length = 0;
        const response = await page.goto(`${baseUrl}/index.php?route=${encodeURIComponent(route)}`, { waitUntil: 'networkidle' });
        const status = response?.status() || 0;
        if (status !== 200) throw new Error(`${role}/${route} returned HTTP ${status} at ${viewport.name}.`);

        const pageAudit = await page.evaluate(() => {
          const root = document.documentElement;
          const body = document.body;
          const viewportWidth = window.innerWidth;
          const horizontalOverflow = Math.max(root.scrollWidth, body.scrollWidth) - viewportWidth;
          const visible = element => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
          };
          const clippedButtons = [...document.querySelectorAll('button, .btn, .icon-btn')]
            .filter(visible)
            .filter(element => element.scrollWidth > element.clientWidth + 2 || element.scrollHeight > element.clientHeight + 2)
            .slice(0, 10)
            .map(element => (element.textContent || element.getAttribute('aria-label') || element.className).trim().slice(0, 80));
          const escapedControls = [...document.querySelectorAll('input, select, textarea, button')]
            .filter(visible)
            .filter(element => {
              const rect = element.getBoundingClientRect();
              return rect.left < -2 || rect.right > viewportWidth + 2;
            })
            .slice(0, 10)
            .map(element => element.getAttribute('name') || element.textContent?.trim().slice(0, 60) || element.tagName);
          const raw = body.innerHTML;
          return {
            horizontalOverflow,
            clippedButtons,
            escapedControls,
            hasServerError: /Fatal error|Uncaught|Parse error|SQLSTATE\[|Warning:\s/i.test(raw),
          };
        });
        if (pageAudit.hasServerError) throw new Error(`${role}/${route} exposed a PHP or SQL error.`);
        if (pageAudit.horizontalOverflow > 3) throw new Error(`${role}/${route} has ${pageAudit.horizontalOverflow}px horizontal overflow at ${viewport.name}.`);
        if (pageAudit.clippedButtons.length) throw new Error(`${role}/${route} clips button content: ${pageAudit.clippedButtons.join(', ')}.`);
        if (pageAudit.escapedControls.length) throw new Error(`${role}/${route} has controls outside viewport: ${pageAudit.escapedControls.join(', ')}.`);

        const modalTargets = await page.locator('[data-open-modal]').evaluateAll(elements =>
          [...new Set(elements.map(element => element.getAttribute('data-open-modal')).filter(Boolean))]
        );
        for (const target of modalTargets) {
          const trigger = page.locator(`[data-open-modal="${target}"]`).first();
          if (!(await trigger.isVisible())) continue;
          await trigger.click();
          const modal = page.locator(`#${target}`);
          await modal.waitFor({ state: 'visible' });
          const modalAudit = await modal.evaluate((element) => {
            const content = element.querySelector('.modal-content');
            if (!content) return { missingContent: true };
            const rect = content.getBoundingClientRect();
            const controls = [...content.querySelectorAll('input, select, textarea, button')].filter(control => {
              const style = getComputedStyle(control);
              const box = control.getBoundingClientRect();
              return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 0 && box.height > 0;
            });
            return {
              missingContent: false,
              escaped: rect.left < -2 || rect.right > window.innerWidth + 2 || rect.top < -2 || rect.bottom > window.innerHeight + 2,
              controlsOutside: controls.filter(control => {
                const box = control.getBoundingClientRect();
                return box.left < rect.left - 2 || box.right > rect.right + 2;
              }).map(control => control.getAttribute('name') || control.textContent?.trim().slice(0, 50) || control.tagName),
            };
          });
          if (modalAudit.missingContent || modalAudit.escaped || modalAudit.controlsOutside.length) {
            throw new Error(`${role}/${route} modal #${target} is outside ${viewport.name}.`);
          }
          const close = modal.locator('[data-close-modal]').first();
          if (await close.isVisible()) await close.click();
          else await page.keyboard.press('Escape');
        }

        if (pageErrors.length) throw new Error(`${role}/${route} page errors: ${pageErrors.join(' | ')}.`);
        report.push({ viewport: viewport.name, role, route, modals: modalTargets.length, status: 'passed' });

        const shouldCapture = role === 'admin' && ['contracts', 'profile-reviews', 'medals', 'system-health'].includes(route)
          || role === 'customer' && ['dashboard', 'ecommerce/landing'].includes(route);
        if (shouldCapture) {
          const directory = path.join('artifacts', 'responsive', viewport.name);
          await fs.mkdir(directory, { recursive: true });
          await page.screenshot({ path: path.join(directory, `${role}-${route.replaceAll('/', '-')}.png`), fullPage: true });
        }
      }
      await context.close();
    }
  }
} finally {
  await browser.close();
}

await fs.mkdir(path.join('artifacts', 'responsive'), { recursive: true });
await fs.writeFile(
  path.join('artifacts', 'responsive', 'report.json'),
  JSON.stringify({ status: 'PASSED', cases: report.length, results: report }, null, 2)
);
console.log(JSON.stringify({ status: 'RESPONSIVE_V143_OK', cases: report.length }));
