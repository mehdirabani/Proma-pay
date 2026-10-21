/* Real browser regression; uses isolated QA accounts/database, never production. */
const { chromium } = require('playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.PROMA_QA_BASE_URL || 'http://127.0.0.1:15200';
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname), 'Local QA server required');
const contracts = (process.env.PROMA_QA_CONTRACT_IDS || '117,124').split(',');
const output = path.join(__dirname, '../output/playwright/v202');
fs.mkdirSync(output, { recursive: true });
const report = { viewports: [], tabs: [], quickActions: [], roles: [], errors: [] };
const credentials = {
  admin: ['qa150admin', 'Admin#150Pass'], operator: ['qa150operator', 'Operator#150Pass'],
  lawyer: ['qa150lawyer', 'Lawyer#150Pass'], customer: ['customer8000001504', '1504'],
};
async function main() {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    for (const [role, fallback] of Object.entries(credentials)) {
      const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
      const page = await context.newPage();
      page.on('pageerror', error => report.errors.push({ role, type: 'pageerror', message: error.message }));
      page.on('console', message => {
        if (message.type() !== 'error') return;
        const location = message.location();
        if (location.url.endsWith('/favicon.ico') && message.text().includes('404')) return;
        report.errors.push({ role, type: 'console', url: page.url(), location, message: message.text() });
      });
      await page.goto(base + '/index.php?route=auth%2Flogin');
      await page.getByRole('textbox', { name: 'نام کاربری، کد ملی، موبایل یا ایمیل' }).fill(process.env['PROMA_QA_' + role.toUpperCase() + '_USER'] || fallback[0]);
      await page.getByRole('textbox', { name: 'رمز عبور', exact: true }).fill(process.env['PROMA_QA_' + role.toUpperCase() + '_PASSWORD'] || fallback[1]);
      await Promise.all([page.waitForURL(url => !url.searchParams.get('route')?.startsWith('auth/')), page.getByRole('button', { name: 'ورود', exact: true }).click()]);
      await page.goto(base + '/index.php?route=dashboard');
      const skip = page.getByRole('button', { name: 'رد کردن', exact: true });
      if (await skip.isVisible()) await skip.click();
      await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
      for (const width of [320, 375, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(280);
        const layout = await page.evaluate(() => ({ viewport: innerWidth, document: document.documentElement.scrollWidth,
          largeIcons: [...document.querySelectorAll('.proma-navigation-brand svg, [data-navigation-toggle] svg')].filter(el => el.getBoundingClientRect().width > 50).length }));
        assert(layout.document <= layout.viewport + 1, role + ' page overflows at ' + width);
        assert.equal(layout.largeIcons, 0, 'Oversized menu icon');
        if (width >= 992) assert.equal(await page.locator('[data-navigation-toggle]:visible').count(), 0, 'Desktop close button is visible');
        report.viewports.push({ role, width, ...layout });
        if ([375, 1440].includes(width)) await page.screenshot({ path: path.join(output, role + '-' + width + '.png') });
      }
      await page.setViewportSize({ width: 375, height: 900 });
      const toggle = page.locator('.page-header [data-navigation-toggle]');
      await toggle.click();
      assert(await page.locator('#proma-navigation').evaluate(el => el.classList.contains('open')));
      await page.keyboard.press('Escape');
      assert(await page.locator('#proma-navigation').evaluate(el => el.inert && !el.classList.contains('open')));
      assert(await toggle.evaluate(el => el === document.activeElement));

      if (role === 'admin') {
        for (const id of contracts) {
          const response = await page.goto(base + '/index.php?route=contracts%2Fshow%2F' + encodeURIComponent(id));
          await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
          assert.equal(response.status(), 200);
          const root = page.locator('[data-contract-tabs-root]');
          assert.equal(await root.count(), 1, 'Contract details did not render for id ' + id + ' at ' + page.url() + ': ' + (await page.locator('body').innerText()).slice(0, 500));
          assert.equal(await page.locator('[data-contract-tab-panel]').count(), await root.locator('[data-contract-tab-panel]').count(), 'Orphan tab panels');
          for (const name of ['installments', 'settlement']) {
            await root.locator('[data-contract-tab-link="' + name + '"]').click();
            assert(await root.locator('[data-contract-tab-panel="' + name + '"]').isVisible(), 'Header quick action missed its tab: ' + name);
            report.quickActions.push({ id, name, passed: true });
          }
          await root.locator('[data-contract-tab-open="summary"]').click();
          for (const width of [375, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.waitForTimeout(280);
            const names = await root.locator('[data-contract-tab-open]').evaluateAll(els => els.map(el => el.dataset.contractTabOpen));
            for (const name of names) {
              await root.locator('[data-contract-tab-open="' + name + '"]').click();
              const panels = await root.locator('[data-contract-tab-panel]').evaluateAll(els => els.map(el => ({ name: el.dataset.contractTabPanel, hidden: el.hidden, display: getComputedStyle(el).display })));
              assert(panels.some(p => p.name === name && !p.hidden), 'Empty active tab: ' + name);
              assert(panels.every(p => p.name === name ? !p.hidden : (p.hidden && p.display === 'none')), 'Mixed tab content: ' + name);
              assert.equal(await page.locator('.proma-contract-installments-table').isVisible(), name === 'installments');
              const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
              assert(!overflow, 'Contract page overflow: ' + width + '/' + name);
              report.tabs.push({ id, width, name, passed: true });
              if (name === 'installments' && id === contracts[0]) {
                const actions = root.locator('.proma-contract-installments-table td.actions button').first();
                if (await actions.count()) {
                  const inView = await actions.evaluate(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; });
                  assert(inView, 'Payment action requires horizontal scrolling at ' + width);
                }
                if ([375, 1440].includes(width)) await page.screenshot({ path: path.join(output, 'contract-' + width + '.png') });
              }
            }
            // Direct deep-link navigation must select only payments, too.
            await page.goto(base + '/index.php?route=contracts%2Fshow%2F' + id + '#contract-payments');
            await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
            assert(await page.locator('[data-contract-tab-panel="payments"]').isVisible());
            assert(!(await page.locator('.proma-contract-installments-table').isVisible()));
          }
        }
        for (const route of ['settings', 'settings/contracts/template', 'legal', 'medals', 'contracts', 'installments']) {
          await page.setViewportSize({ width: 375, height: 900 });
          const response = await page.goto(base + '/index.php?route=' + encodeURIComponent(route));
          await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
          assert.equal(response.status(), 200, 'Route failed: ' + route);
          assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Route overflow: ' + route);
          report.roles.push({ role, route, status: response.status() });
        }
      }
      report.roles.push({ role, dashboard: 'pass', navigation: 'pass' });
      await context.close();
    }
    assert.equal(report.errors.length, 0, JSON.stringify(report.errors));
    console.log('BROWSER_V202_OK ' + report.viewports.length + ' role/viewport checks; ' + report.tabs.length + ' tab checks');
  } finally {
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
    await browser.close();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
