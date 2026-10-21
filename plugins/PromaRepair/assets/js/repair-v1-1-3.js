(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-rich-editor]').forEach(function (source) {
      const tools = document.createElement('div');
      tools.className = 'pr-rich-tools';
      const editor = document.createElement('div');
      editor.className = 'pr-rich-editor';
      editor.contentEditable = 'true';
      editor.setAttribute('role', 'textbox');
      editor.setAttribute('aria-multiline', 'true');
      editor.innerHTML = source.value;
      [['bold', 'پررنگ'], ['italic', 'کج'], ['underline', 'زیرخط'], ['insertUnorderedList', 'فهرست']].forEach(function (entry) {
        const button = document.createElement('button');
        button.type = 'button'; button.textContent = entry[1];
        button.addEventListener('mousedown', function (event) { event.preventDefault(); });
        button.addEventListener('click', function () { editor.focus(); document.execCommand(entry[0], false); });
        tools.appendChild(button);
      });
      source.hidden = true; source.insertAdjacentElement('afterend', tools); tools.insertAdjacentElement('afterend', editor);
      source.form?.addEventListener('submit', function () { source.value = editor.innerHTML; });
    });

    document.querySelectorAll('[data-repair-intake-form]').forEach(function (form) {
      const secretType = form.querySelector('[data-repair-secret-type]');
      const secretInput = form.querySelector('[data-repair-secret-input]');
      const patternOpen = form.querySelector('[data-pattern-open]');
      const patternLabel = form.querySelector('[data-pattern-confirmed]');
      const secretToggle = form.querySelector('[data-secret-toggle]');
      let currentSecretMode = secretType?.value || '';
      const syncSecretUI = function () {
        const mode = secretType?.value || '';
        if (mode !== currentSecretMode && secretInput) secretInput.value = '';
        currentSecretMode = mode;
        if (secretInput) {
          secretInput.hidden = !['password', 'pin'].includes(mode);
          secretInput.type = mode === 'password' ? 'password' : 'text';
          secretInput.inputMode = mode === 'pin' ? 'numeric' : 'text';
          secretInput.pattern = mode === 'pin' ? '[0-9۰-۹]{4,12}' : '';
          secretInput.maxLength = mode === 'pin' ? 12 : 128;
          secretInput.placeholder = mode === 'pin' ? '۴ تا ۱۲ رقم' : 'رمز دستگاه';
          if (mode === 'pattern' || mode === '') secretInput.value = '';
        }
        if (secretToggle) secretToggle.hidden = mode !== 'password';
        if (patternOpen) patternOpen.hidden = mode !== 'pattern';
        if (patternLabel) patternLabel.hidden = mode !== 'pattern' || !secretInput?.value;
        const label = form.querySelector('[data-repair-secret-label]');
        if (label) label.textContent = mode === 'pin' ? 'PIN' : mode === 'password' ? 'رمز دستگاه' : mode === 'pattern' ? 'پترن قفل' : 'رمز دستگاه';
      };
      secretType?.addEventListener('change', syncSecretUI);
      syncSecretUI();
      form.closest('.pr-intake-modal')?.addEventListener('proma:modal-opened', function () {
        const body = form.querySelector('.pr-intake-body');
        if (body) { body.scrollTop = 0; body.scrollLeft = 0; }
        syncSecretUI();
      });
      secretToggle?.addEventListener('click', function () {
        if (!secretInput) return;
        const revealing = secretInput.type === 'password';
        secretInput.type = revealing ? 'text' : 'password';
        secretToggle.setAttribute('aria-label', revealing ? 'مخفی‌کردن رمز' : 'نمایش رمز');
        secretToggle.setAttribute('aria-pressed', revealing ? 'true' : 'false');
      });
      secretInput?.addEventListener('input', function () {
        if (secretType?.value === 'pin') secretInput.value = secretInput.value.replace(/[^0-9۰-۹]/g, '').slice(0, 12);
      });

      const selectedCustomer = form.querySelector('[data-repair-selected-customer]');
      form.addEventListener('proma:customer-selected', function (event) {
        const customer = event.detail || {};
        if (selectedCustomer) selectedCustomer.textContent = (customer.full_name || 'مشتری') + (customer.mobile ? ' · ' + customer.mobile : '');
      });
      form.querySelectorAll('[data-customer-live-search]').forEach(function (box) {
        box.addEventListener('proma:customer-created', function (event) {
          const customer = event.detail || {};
          if (selectedCustomer) selectedCustomer.textContent = (customer.full_name || 'مشتری') + (customer.mobile ? ' · ' + customer.mobile : '');
        });
      });

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const errorBox = form.querySelector('[data-repair-intake-error]');
        const submitter = event.submitter;
        const data = new FormData(form);
        if (submitter?.name) data.set(submitter.name, submitter.value);
        if (!data.get('_intent')) data.set('_intent', 'register');
        if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
        const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
        buttons.forEach(function (button) { button.disabled = true; });
        fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (response) {
            return response.json().then(function (payload) {
              if (!response.ok || !payload.ok) throw new Error(payload.message || 'ثبت پذیرش انجام نشد.');
              return payload;
            });
          }).then(function (payload) {
            window.location.assign(payload.url);
          }).catch(function (failure) {
            if (errorBox) { errorBox.textContent = failure.message || 'ثبت پذیرش انجام نشد.'; errorBox.hidden = false; }
            else if (typeof window.showToast === 'function') window.showToast(failure.message || 'ثبت پذیرش انجام نشد.', 'error');
          }).finally(function () { buttons.forEach(function (button) { button.disabled = false; }); });
      });
    });

    document.querySelectorAll('[data-pattern-grid]').forEach(function (grid) {
      const modal = grid.closest('.modal');
      const form = document.querySelector('[data-repair-intake-form]');
      const target = form?.querySelector('[name="device[device_password]"]');
      const line = modal?.querySelector('[data-pattern-line]');
      const status = modal?.querySelector('[data-pattern-status]');
      const nodes = [];
      let drawing = false;
      const buttons = Array.from(grid.querySelectorAll('button[data-dot]'));

      const paint = function () {
        buttons.forEach(function (button) {
          const index = nodes.indexOf(Number(button.dataset.dot));
          button.classList.toggle('is-selected', index >= 0);
          let step = button.querySelector('em');
          if (index >= 0) {
            if (!step) { step = document.createElement('em'); button.appendChild(step); }
            step.textContent = String(index + 1);
          } else if (step) step.remove();
        });
        if (line) line.setAttribute('points', nodes.map(function (node) { const n = node - 1; return ((n % 3) * 100 + 50) + ',' + (Math.floor(n / 3) * 100 + 50); }).join(' '));
        if (status) status.textContent = nodes.length ? 'ترتیب انتخاب: ' + nodes.join(' ← ') : 'برای رسم، نقطه اول را لمس کنید.';
      };
      const addNode = function (button) {
        if (!drawing || !button) return;
        const node = Number(button.dataset.dot);
        if (node >= 1 && node <= 9 && !nodes.includes(node)) { nodes.push(node); paint(); }
      };
      buttons.forEach(function (button) {
        button.addEventListener('pointerdown', function (event) { event.preventDefault(); drawing = true; addNode(button); });
        button.addEventListener('pointerenter', function () { addNode(button); });
        button.addEventListener('click', function () { if (!drawing && !nodes.includes(Number(button.dataset.dot))) { drawing = true; addNode(button); drawing = false; } });
      });
      grid.addEventListener('pointerup', function () { drawing = false; });
      grid.addEventListener('pointercancel', function () { drawing = false; });
      const clear = function () { nodes.splice(0, nodes.length); drawing = false; paint(); };
      modal?.querySelector('[data-pattern-clear]')?.addEventListener('click', clear);
      modal?.querySelector('[data-pattern-redraw]')?.addEventListener('click', clear);
      modal?.querySelector('[data-pattern-confirm]')?.addEventListener('click', function () {
        if (nodes.length < 4) { if (status) status.textContent = 'برای ثبت، دست‌کم چهار نقطه انتخاب کنید.'; return; }
        if (target) target.value = nodes.join('-');
        const confirmed = form?.querySelector('[data-pattern-confirmed]');
        if (confirmed) confirmed.hidden = false;
        const close = modal.querySelector('[data-close-modal]');
        close?.click();
      });
      modal?.addEventListener('proma:modal-opened', clear);
    });

    document.addEventListener('proma:customer-selected', function (event) {
      const box = event.target?.closest?.('[data-customer-live-search]');
      const form = box?.closest('form');
      if (!form?.matches('[data-repair-intake-form]')) return;
      const label = form.querySelector('[data-repair-selected-customer]');
      if (label) label.textContent = (event.detail?.full_name || 'مشتری') + (event.detail?.mobile ? ' · ' + event.detail.mobile : '');
    });

    const requestedModal = new URLSearchParams(window.location.search).get('open');
    if (requestedModal) {
      window.setTimeout(function () {
        const opener = Array.from(document.querySelectorAll('[data-open-modal]')).find(function (item) { return item.getAttribute('data-open-modal') === requestedModal; });
        if (opener) opener.click();
        else {
          const modal = document.getElementById(requestedModal);
          modal?.classList.add('open');
          modal?.setAttribute('aria-hidden', 'false');
          document.body.classList.add('proma-modal-open');
          const body = modal?.querySelector('.modal-body');
          if (body) { body.scrollTop = 0; body.scrollLeft = 0; }
          modal?.dispatchEvent(new CustomEvent('proma:modal-opened'));
        }
      }, 0);
    }
  });
})();
