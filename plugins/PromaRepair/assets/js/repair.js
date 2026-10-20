(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-repair-receipt-template]').forEach(function (workspace) {
      const source = workspace.querySelector('[data-repair-receipt-source]');
      const field = source?.closest('.pr-receipt-template-source');
      const editor = source?._promaQuill || null;
      const editorShell = field?.querySelector('.proma-rich-editor-shell');
      const preview = workspace.querySelector('[data-repair-receipt-preview]');
      const frame = workspace.querySelector('[data-repair-receipt-preview-frame]');
      const status = workspace.querySelector('[data-repair-receipt-status]');
      const modes = Array.from(workspace.querySelectorAll('[data-repair-receipt-mode]'));
      let mode = 'visual';

      const syncSource = function () {
        if (editor && mode !== 'source') source.value = editor.root.innerHTML.trim();
      };
      const safePreview = function (markup) {
        const doc = document.implementation.createHTMLDocument('receipt-preview');
        doc.body.innerHTML = markup;
        doc.body.querySelectorAll('script,style,iframe,object,embed,form').forEach(function (node) { node.remove(); });
        doc.body.querySelectorAll('*').forEach(function (node) {
          Array.from(node.attributes).forEach(function (attribute) {
            if (/^on/i.test(attribute.name) || attribute.name === 'style' || attribute.name === 'srcdoc') node.removeAttribute(attribute.name);
            if (['href', 'src'].includes(attribute.name) && !/^(https?:|mailto:|data:image\/)/i.test(attribute.value)) node.removeAttribute(attribute.name);
          });
        });
        return doc.body.innerHTML;
      };
      const show = function (nextMode) {
        mode = nextMode;
        if (nextMode !== 'source') syncSource();
        if (nextMode === 'visual' && editor && window.PromaQuillHtml) window.PromaQuillHtml(editor, source.value);
        field?.classList.toggle('is-source', nextMode === 'source');
        workspace.classList.toggle('is-preview', nextMode === 'preview');
        if (preview) preview.hidden = nextMode !== 'preview';
        if (field) field.hidden = nextMode === 'preview';
        if (editorShell) editorShell.hidden = nextMode === 'source' || nextMode === 'preview';
        if (nextMode === 'preview' && frame) {
          syncSource();
          const samples = {
            copy_label: 'نسخه مشتری', business_name: 'مرکز تعمیر نمونه', contact: '۰۲۱-۱۲۳۴۵۶۷۸', address: 'تهران، خیابان نمونه',
            tracking_code: 'PR-REP-1405-001', repair_number: '#۱۲۳', accepted_at: '۱۴۰۵/۰۷/۱۳', customer_name: 'نام مشتری', customer_mobile: '۰۹۱۲۱۲۳۴۵۶۷',
            device: 'موبایل نمونه', device_identifiers: 'IMEI: ۳۵۲۰۱۲۳۴۵۶۷۸۹۰۱', status: 'در حال بررسی', estimated_delivery: '۱۴۰۵/۰۷/۱۵', accessories: 'شارژر، قاب', conditions: 'خط و خش',
            issue_summary: 'شرح نمونه خرابی', condition_notes: 'بدون توضیحات اضافی', warranty: '۳۰ روز', footer: 'از اعتماد شما سپاسگزاریم',
            logo_html: '', qr_html: '<div class="repair-preview-qr">QR</div>', financial_summary: '<p><strong>برآورد هزینه:</strong> طبق اعلام مرکز تعمیر</p>',
            signatures_html: '<div class="repair-doc-signatures"><div class="repair-doc-signature"><strong>امضای مشتری</strong><span></span></div><div class="repair-doc-signature"><strong>امضای فروشنده / مرکز تعمیر</strong><span></span></div></div>',
            receipt_terms_html: '<h2>شرایط پذیرش</h2><p>شرایط نمونه رسید تعمیر</p>', receipt_union_html: ''
          };
          const body = source.value.replace(/{{\s*([a-z0-9_.]+)\s*}}/gi, function (match, key) {
            return Object.prototype.hasOwnProperty.call(samples, key) ? samples[key] : '';
          });
          const previewBody = safePreview(body);
          const previewStyle = '*{box-sizing:border-box}body{font:14px/1.8 Tahoma,Arial,sans-serif;color:#20283a;background:#f4f6fa;padding:24px}.repair-document{background:#fff;border:1px solid #d9deea;border-radius:12px;max-width:760px;margin:auto;padding:28px}.repair-doc-header{display:grid;grid-template-columns:1fr auto;gap:18px;border-bottom:2px solid #4434ce;padding-bottom:16px}.repair-doc-brand{display:flex;align-items:center;gap:14px}.repair-doc-brand strong{font-size:22px;color:#172554}.repair-doc-brand span,.repair-doc-code span{display:block;color:#59657a;font-size:12px}.repair-doc-code{background:#f5f6ff;border:1px solid #e2e5ff;border-radius:8px;padding:10px;text-align:center}.repair-doc-code>*{display:block}.repair-doc-code>strong{color:#3024b7;font-size:16px}.repair-doc-section{border:1px solid #e0e4ee;border-radius:8px;margin-top:14px;padding:12px 14px}.repair-doc-section h2,.repair-doc-terms h2{font-size:16px;color:#172554;border-bottom:1px solid #d9deea;padding-bottom:6px}.repair-doc-facts{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}.repair-doc-facts>div{display:grid;grid-template-columns:34% 1fr;border-bottom:1px solid #edf0f5;padding:5px}.repair-doc-facts dt{color:#64708a}.repair-doc-facts dd{margin:0;font-weight:bold}.repair-doc-note{background:#f8f9fc;border-right:2px solid #8c82ed;margin-top:8px;padding:8px}.repair-doc-signatures{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:30px}.repair-doc-signature{border-top:1px solid #778198;min-height:72px;text-align:center;padding-top:6px}.repair-doc-signature strong{display:block}.repair-doc-footer{display:flex;justify-content:space-between;border-top:1px solid #d9deea;margin-top:16px;padding-top:8px;color:#69758a;font-size:12px}.repair-preview-qr{display:inline-grid;place-items:center;width:72px;height:72px;border:1px dashed #8991a1;color:#687080}h1{font-size:22px}p{margin:6px 0}@media(max-width:580px){.repair-doc-header,.repair-doc-facts{grid-template-columns:1fr}.repair-document{padding:14px}}';
          frame.srcdoc = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>' + previewStyle + '</style></head><body>' + previewBody + '</body></html>';
        }
        modes.forEach(function (button) {
          const active = button.dataset.repairReceiptMode === nextMode;
          button.classList.toggle('active', active);
          button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (status) status.textContent = nextMode === 'preview' ? 'پیش‌نمایش با اطلاعات نمونه نمایش داده می‌شود.' : nextMode === 'source' ? 'حالت HTML پیشرفته فعال است.' : 'ویرایش بصری آماده است.';
      };
      modes.forEach(function (button) { button.addEventListener('click', function () { show(button.dataset.repairReceiptMode || 'visual'); }); });
      source?.addEventListener('input', function () {
        if (mode === 'source' && editor && window.PromaQuillHtml) window.PromaQuillHtml(editor, source.value);
      });
      source?.form?.addEventListener('submit', syncSource);
      workspace.querySelectorAll('[data-repair-receipt-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
          if (mode === 'preview') show('visual');
          const token = button.dataset.repairReceiptVariable || '';
          if (mode === 'source' || !editor) {
            source.focus();
            const start = source.selectionStart ?? source.value.length;
            const end = source.selectionEnd ?? start;
            source.value = source.value.slice(0, start) + token + source.value.slice(end);
            source.setSelectionRange(start + token.length, start + token.length);
            source.dispatchEvent(new Event('input', { bubbles: true }));
          } else {
            editor.focus();
            const range = editor.getSelection(true);
            editor.insertText(range ? range.index : editor.getLength(), token, 'user');
          }
        });
      });
      show('visual');
    });

    document.querySelectorAll('[data-repair-intake-form]').forEach(function (form) {
      form.querySelectorAll('[data-technician-picker]').forEach(function (picker) {
        const filter = picker.querySelector('[data-technician-filter]');
        const select = picker.querySelector('[data-technician-select]');
        filter?.addEventListener('input', function () {
          const query = filter.value.trim().toLocaleLowerCase();
          Array.from(select?.options || []).forEach(function (option) {
            if (!option.value) { option.hidden = false; return; }
            option.hidden = query !== '' && !(option.dataset.search || option.textContent.toLocaleLowerCase()).includes(query);
          });
          if (select && select.selectedOptions[0]?.hidden) select.value = '0';
        });
      });
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

    document.querySelectorAll('[data-customer-create-modal] form[data-customer-create-form]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const errorBox = form.querySelector('[data-customer-create-error]');
        const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
        if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
        buttons.forEach(function (button) { button.disabled = true; });
        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(async function (response) {
          let payload = null;
          try { payload = await response.json(); } catch (_) {}
          if (!response.ok || !payload?.ok || !payload.customer) {
            const requestId = response.headers.get('X-Request-Id');
            throw new Error(payload?.message || ('ثبت مشتری انجام نشد.' + (requestId ? ' شناسه پیگیری: ' + requestId : '')));
          }
          return payload.customer;
        }).then(function (customer) {
          const intakeForm = document.querySelector('[data-repair-intake-form]');
          const customerBox = intakeForm?.querySelector('[data-customer-live-search]');
          const customerSelect = customerBox?.querySelector('[data-customer-select]');
          const customerInput = customerBox?.querySelector('[data-customer-search-input]');
          if (customerSelect) {
            customerSelect.value = String(customer.id);
            customerSelect.dataset.customerLabel = customer.full_name || '';
            customerSelect.dataset.customerName = customer.full_name || '';
            customerSelect.dataset.customerMobile = customer.mobile || '';
            customerSelect.dataset.customerNationalId = customer.national_id || '';
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (customerInput) customerInput.value = customer.full_name || '';
          if (customerBox) {
            customerBox.dispatchEvent(new CustomEvent('proma:customer-selected', { bubbles: true, detail: customer }));
            customerBox.dispatchEvent(new CustomEvent('proma:customer-created', { bubbles: true, detail: customer }));
          }
          form.reset();
          form.closest('[data-customer-create-modal]')?.querySelector('[data-close-modal]')?.click();
        }).catch(function (failure) {
          if (errorBox) { errorBox.textContent = failure.message || 'ثبت مشتری انجام نشد.'; errorBox.hidden = false; }
          else if (typeof window.showToast === 'function') window.showToast(failure.message || 'ثبت مشتری انجام نشد.', 'error');
        }).finally(function () {
          buttons.forEach(function (button) { button.disabled = false; });
        });
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
