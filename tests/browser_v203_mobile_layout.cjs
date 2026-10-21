/* Mobile regression checks for the v2 shell and installment tables. */
const { chromium } = require('playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.PROMA_QA_BASE_URL || 'http://127.0.0.1:15200';
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname), 'Local QA server required');
const output = path.join(__dirname, '../output/playwright/v203-mobile');
fs.mkdirSync(output, { recursive: true });

const users = {
  customer: ['2052221263', '3400'],
  admin: ['qa150admin', 'Admin#150Pass'],
};

async function login(page, role, fallback) {
  await page.goto(base + '/index.php?route=auth%2Flogin');
  await page.locator('input[name="identifier"]').fill(process.env['PROMA_QA_' + role.toUpperCase() + '_USER'] || fallback[0]);
  await page.locator('input[name="password"]').fill(process.env['PROMA_QA_' + role.toUpperCase() + '_PASSWORD'] || fallback[1]);
  await Promise.all([
    page.waitForURL(url => !url.searchParams.get('route')?.startsWith('auth/')),
    page.getByRole('button', { name: 'ورود', exact: true }).click(),
  ]);
}

async function inspectMobileCards(page, route, role, report) {
  const response = await page.goto(base + '/index.php?route=' + encodeURIComponent(route));
  assert.equal(response.status(), 200, role + ' route failed: ' + route);
  await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  const widthResults = [];
  for (const width of [320, 360, 375, 390, 430]) {
    await page.setViewportSize({ width, height: 844 });
    await page.waitForTimeout(70);
    const metrics = await page.evaluate(() => {
    const rect = element => {
      const box = element.getBoundingClientRect();
      return { x: Math.round(box.x), width: Math.round(box.width), right: Math.round(box.right) };
    };
    return {
      viewport: innerWidth,
      document: document.documentElement.scrollWidth,
      bodyV2: document.body.classList.contains('proma-v2'),
      contractCards: Array.from(document.querySelectorAll('.proma-contract-card')).slice(0, 3).map(card => {
        const identity = card.querySelector('.proma-contract-card__identity');
        const name = card.querySelector('.proma-contract-card__identity h6');
        const nameRange = document.createRange();
        if (name) nameRange.selectNodeContents(name);
        return {
          width: rect(card).width,
          identityWidth: identity ? rect(identity).width : 0,
          nameWidth: name ? rect(name).width : 0,
          nameScrollWidth: name ? name.scrollWidth : 0,
          nameLineCount: name ? nameRange.getClientRects().length : 0,
          name: name ? name.innerText.trim() : '',
        };
      }),
      tables: Array.from(document.querySelectorAll('.proma-page-content table')).map(table => ({
        classes: table.className,
        display: getComputedStyle(table).display,
        width: rect(table).width,
        scrollWidth: table.scrollWidth,
        clientWidth: table.clientWidth,
        wrappers: table.closest('.table-wrap, .table-responsive') ? {
          scrollWidth: table.closest('.table-wrap, .table-responsive').scrollWidth,
          clientWidth: table.closest('.table-wrap, .table-responsive').clientWidth,
        } : null,
        rows: Array.from(table.querySelectorAll('tbody tr')).slice(0, 2).map(row => ({
          width: rect(row).width,
          scrollWidth: row.scrollWidth,
          cells: Array.from(row.cells).map(cell => ({ label: cell.dataset.label || '', width: rect(cell).width, scrollWidth: cell.scrollWidth, display: getComputedStyle(cell).display, whiteSpace: getComputedStyle(cell).whiteSpace, text: cell.innerText.trim().slice(0, 60) })),
        })),
        overflowChildren: Array.from(table.querySelectorAll('*')).filter(element => element.scrollWidth > element.clientWidth + 2).slice(0, 10).map(element => ({ tag: element.tagName, className: element.className.toString(), width: rect(element).width, scrollWidth: element.scrollWidth, clientWidth: element.clientWidth, whiteSpace: getComputedStyle(element).whiteSpace, text: element.innerText.trim().slice(0, 60) })),
        identityCells: Array.from(table.querySelectorAll('tbody td')).filter(cell => /مشتری|نام/.test(cell.dataset.label || '')).slice(0, 3).map(cell => ({
          display: getComputedStyle(cell).display,
          width: rect(cell).width,
          scrollWidth: cell.scrollWidth,
          columns: getComputedStyle(cell).gridTemplateColumns,
          text: cell.innerText.trim(),
        })),
      })),
    };
    });
    report.checks.push({ role, route, metrics });
    widthResults.push(metrics);
    assert(metrics.bodyV2, role + ' did not load the v2 shell');
    assert(metrics.document <= metrics.viewport + 1, role + ' document overflows at ' + route + '/' + width + ': ' + JSON.stringify(metrics));
    const cardTables = metrics.tables.filter(table => table.classes.includes('proma-v2-data-table'));
    assert(cardTables.length > 0 || metrics.contractCards.length > 0, role + ' has no responsive data cards at ' + route + '/' + width);
    for (const card of metrics.contractCards) {
      assert(card.width <= metrics.viewport + 1, role + ' contract card is wider than viewport: ' + JSON.stringify(card));
      assert(card.identityWidth >= 100 && card.nameWidth >= 72, role + ' customer name column is too narrow: ' + JSON.stringify(card));
      assert(card.nameScrollWidth <= card.nameWidth + 1, role + ' customer name overflows its card: ' + JSON.stringify(card));
      assert(card.nameLineCount <= 4, role + ' customer name is broken into vertical letters: ' + JSON.stringify(card));
    }
    for (const table of cardTables) {
      assert.equal(table.display, 'block', role + ' table is not rendered as cards at ' + route + '/' + width);
      assert(table.width <= metrics.viewport + 1, role + ' card table is wider than viewport: ' + JSON.stringify(table));
      assert(table.scrollWidth <= table.clientWidth + 1, role + ' card table has internal horizontal overflow: ' + JSON.stringify(table));
      assert(!table.wrappers || table.wrappers.clientWidth >= table.wrappers.scrollWidth - 1, role + ' table still requires horizontal scrolling: ' + JSON.stringify(table));
      assert.equal(table.overflowChildren.length, 0, role + ' has clipped text in a mobile card: ' + JSON.stringify(table.overflowChildren));
      for (const cell of table.identityCells) {
        const columns = cell.columns.split(' ').map(value => parseFloat(value));
        assert(columns.every(Number.isFinite) && columns.every(value => value >= 72), role + ' name/identity columns are too narrow: ' + JSON.stringify(cell));
      }
    }
    if (width === 390) {
      const firstRow = page.locator('.proma-v2-data-table:visible tbody tr:visible, .proma-contract-card:visible').first();
      if (await firstRow.count() && await firstRow.isVisible()) await firstRow.screenshot({ path: path.join(output, role + '-' + route.replaceAll('/', '-') + '-390-row.png') });
    }
  }
  return widthResults;
}

async function main() {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const report = { checks: [], errors: [] };
  try {
    for (const [role, credentials] of Object.entries(users)) {
      const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
      const page = await context.newPage();
      page.on('pageerror', error => report.errors.push({ role, message: error.message }));
      await login(page, role, credentials);
      const dashboardResponse = await page.goto(base + '/index.php?route=dashboard');
      assert.equal(dashboardResponse.status(), 200, role + ' dashboard failed');
      const skipTour = page.getByRole('button', { name: 'رد کردن', exact: true });
      if (await skipTour.isVisible().catch(() => false)) await skipTour.click();
      await page.locator('.loader-wrapper').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
      if (role === 'admin') {
        const toggle = page.locator('.page-header [data-navigation-toggle]');
        await toggle.click();
        const menu = page.locator('#proma-navigation .proma-navigation-menu');
        await menu.waitFor({ state: 'visible' });
        const scrollResult = await menu.evaluate(element => {
          const before = element.scrollTop;
          element.scrollTop = element.scrollHeight;
          const lastLink = element.querySelector('.proma-navigation-list > li:last-child a, .proma-navigation-list > li:last-child button');
          const menuBox = element.getBoundingClientRect();
          const linkBox = lastLink.getBoundingClientRect();
          return {
            clientHeight: element.clientHeight,
            scrollHeight: element.scrollHeight,
            before,
            after: element.scrollTop,
            lastLinkVisible: linkBox.top >= menuBox.top && linkBox.bottom <= menuBox.bottom,
          };
        });
        assert(scrollResult.scrollHeight > scrollResult.clientHeight, 'Admin navigation unexpectedly has no overflow to scroll');
        assert(scrollResult.after > scrollResult.before, 'Admin mobile sidebar menu cannot scroll: ' + JSON.stringify(scrollResult));
        assert(scrollResult.lastLinkVisible, 'Admin final sidebar option is not reachable: ' + JSON.stringify(scrollResult));
        report.checks.push({ role, navigation: scrollResult });
      }
      if (role === 'customer') {
        await inspectMobileCards(page, 'installments/panel', role, report);
        await inspectMobileCards(page, 'dashboard', role, report);
      }
      if (role === 'admin') {
        for (const route of ['dashboard', 'contracts', 'contracts/show/' + (process.env.PROMA_QA_CONTRACT_ID || '7')]) {
          await inspectMobileCards(page, route, role, report);
        }
      }
      await context.close();
    }
    assert.equal(report.errors.length, 0, JSON.stringify(report.errors));
    console.log('BROWSER_V203_MOBILE_OK ' + report.checks.length + ' mobile layout checks');
  } finally {
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
    await browser.close();
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
