const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const template = fs.readFileSync(path.join(__dirname, '..', 'views', 'installments', 'index.php'), 'utf8');
const scripts = [...template.matchAll(/<script>([\s\S]*?)<\/script>/g)];
const groupScript = scripts.find((entry) => entry[1].includes("document.querySelectorAll('[data-payment-group-form]')"));
assert.ok(groupScript, 'Customer group quote script is missing.');

const listeners = {};
const element = (value = '') => ({ value, textContent: '', disabled: false, addEventListener(event, callback) { listeners[this.key + ':' + event] = callback; } });
const amount = Object.assign(element('3000000'), { key: 'amount' });
const total = element();
const quoteInput = element();
const allocationInputs = { innerHTML: '', items: [], appendChild(item) { this.items.push(item); } };
const scopeInput = element('selected');
const status = element();
const submit = Object.assign(element(), { disabled: true });
const refreshButton = Object.assign(element(), { key: 'refresh' });
const item = Object.assign(element('1'), { key: 'item' });
const controls = {
    '[data-group-amount]': amount, '[data-group-total]': total,
    '[data-group-quote-uuid]': quoteInput, '[data-group-allocation-inputs]': allocationInputs,
    '[data-group-scope]': scopeInput, '[data-group-quote-status]': status,
    '[data-group-submit]': submit, '[data-group-refresh]': refreshButton,
};
const form = {
    querySelector(selector) { return controls[selector]; },
    querySelectorAll(selector) { return selector === '[data-group-item]' || selector === '[data-group-item]:checked' ? [item] : []; },
    getAttribute() { return '/index.php?route=contracts%2FsettlementQuote%2F1'; },
    addEventListener(event, callback) { listeners['form:' + event] = callback; },
};
let requests = 0;
const context = {
    document: { querySelectorAll() { return [form]; }, createElement() { return {}; } },
    fetch(url) {
        requests++;
        const scope = url.includes('scope=schedule') ? 'schedule' : 'selected';
        return Promise.resolve({ json: () => Promise.resolve({ ok: true, quote: {
            quote_uuid: 'qa-' + scope,
            full_settlement_total: scope === 'schedule' ? 7000000 : 3000000,
            allocations: [{ installment_id: 1 }], formatted: { final_payable: '۳٬۰۰۰٬۰۰۰ تومان' },
        } }) });
    },
    AbortController, setTimeout, clearTimeout, Promise, String, Number, Array, parseInt, encodeURIComponent,
};
vm.runInNewContext(groupScript[1], context);

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
(async () => {
    assert.equal(requests, 0, 'Page initialization sent a quote request for an idle payment form.');
    assert.equal(quoteInput.value, '', 'No quote should be authorized before explicit calculation.');
    assert.equal(submit.disabled, true, 'The payment action must stay disabled before explicit calculation.');
    listeners['refresh:click']();
    await wait(720);
    assert.equal(requests, 1, 'One explicit calculation should send exactly one selected quote request.');
    assert.equal(quoteInput.value, 'qa-selected');
    assert.equal(submit.disabled, false);
    amount.value = '4000000';
    listeners['amount:input']();
    assert.equal(submit.disabled, true, 'Changing the amount must invalidate the old quote immediately.');
    await wait(720);
    assert.equal(requests, 2, 'Only the overflow schedule quote should be fetched after amount change.');
    assert.equal(scopeInput.value, 'schedule');
    amount.value = '5000000';
    listeners['amount:input']();
    await wait(100);
    amount.value = '6000000';
    listeners['amount:input']();
    await wait(720);
    assert.equal(requests, 3, 'Rapid edits should be debounced to one additional quote request.');
    console.log('CUSTOMER_GROUP_QUOTE_REQUEST_V204_OK');
})().catch((error) => { console.error(error); process.exitCode = 1; });
