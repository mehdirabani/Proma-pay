(() => {
  'use strict';

  const root = document.querySelector('[data-pip-root]');
  if (!root) return;

  const localizeValidation = (event) => {
    const control = event.target;
    if (!control || !control.willValidate || !control.validity) return;
    const validity = control.validity;
    let message = '';
    if (validity.valueMissing) message = 'لطفاً این فیلد را تکمیل کنید.';
    else if (validity.typeMismatch || validity.badInput || validity.patternMismatch) message = 'مقدار واردشده معتبر نیست.';
    else if (validity.rangeUnderflow || validity.rangeOverflow || validity.stepMismatch) message = 'مقدار واردشده در محدودهٔ مجاز نیست.';
    if (message) control.setCustomValidity(message);
  };
  const clearLocalizedValidation = (event) => {
    const control = event.target;
    if (control && typeof control.setCustomValidity === 'function') control.setCustomValidity('');
  };
  root.addEventListener('invalid', localizeValidation, true);
  root.addEventListener('input', clearLocalizedValidation, true);
  root.addEventListener('change', clearLocalizedValidation, true);

  const parseNumber = (value) => Number(String(value || '').replace(/[۰-۹٠-٩]/g, (digit) => {
    const code = digit.charCodeAt(0);
    return code >= 1776 && code <= 1785 ? String(code - 1776) : String(code - 1632);
  }).replace(/[٬,]/g, '').replace('٫', '.'));

  const pickerLabel = (kind, item) => {
    if (kind === 'product') {
      const parts = [item.sku, item.name, [item.brand, item.model].filter(Boolean).join(' ')].filter(Boolean);
      if (item.color || item.capacity || item.ram) parts.push([item.color, item.capacity, item.ram].filter(Boolean).join(' '));
      parts.push(`موجود: ${new Intl.NumberFormat('fa-IR').format(Number(item.available_qty || 0))}`);
      return parts.join(' — ');
    }
    if (kind === 'contract') {
      const amount = new Intl.NumberFormat('fa-IR').format(Number(item.principal_amount || 0));
      return `قرارداد #${new Intl.NumberFormat('fa-IR').format(Number(item.id))} — ${item.customer_name} — ${amount} تومان`;
    }
    return item.name || '';
  };

  const renderPickerResults = (picker, items) => {
    const kind = picker.dataset.pipPicker;
    const results = picker.querySelector('[data-pip-picker-results]');
    results.replaceChildren();
    if (!items.length) {
      const empty = document.createElement('div');
      empty.className = 'pip-picker-empty';
      empty.textContent = 'موردی پیدا نشد.';
      results.append(empty);
      results.hidden = false;
      return;
    }
    items.forEach((item) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'pip-picker-option';
      option.setAttribute('role', 'option');
      option.textContent = pickerLabel(kind, item);
      option.addEventListener('click', () => {
        const input = picker.querySelector('[data-pip-picker-input]');
        const value = picker.querySelector('[data-pip-picker-value]');
        const selected = picker.querySelector('[data-pip-picker-selected]');
        input.value = option.textContent;
        input.setCustomValidity('');
        value.value = String(item.id);
        value.dataset.label = option.textContent;
        if (kind === 'contract') value.dataset.principalToman = String(item.principal_amount || '');
        selected.textContent = kind === 'contract' ? 'این قرارداد هنگام ثبت فروش اقساطی به‌طور خودکار متصل می‌شود.' : 'انتخاب شد';
        selected.hidden = false;
        picker.querySelector('[data-pip-picker-results]').hidden = true;
        input.setAttribute('aria-expanded', 'false');
        value.dispatchEvent(new Event('change', { bubbles: true }));
        if (kind === 'product') {
          const form = picker.closest('form');
          const contract = form && form.querySelector('[name="contract_id"]');
          const price = form && form.querySelector('[name="unit_sale_toman"]');
          const quantity = form && form.querySelector('[name="quantity"]');
          if (contract && contract.dataset.principalToman && price) {
            const units = Math.max(1, parseNumber(quantity && quantity.value) || 1);
            price.value = (Number(contract.dataset.principalToman) / units).toString();
          } else if (price && !price.value && item.sale_price_rial) {
            price.value = (Number(item.sale_price_rial) / 10).toString();
          }
        }
      });
      results.append(option);
    });
    results.hidden = false;
  };

  root.querySelectorAll('[data-pip-picker]').forEach((picker) => {
    const input = picker.querySelector('[data-pip-picker-input]');
    const value = picker.querySelector('[data-pip-picker-value]');
    const results = picker.querySelector('[data-pip-picker-results]');
    if (!input || !value || !results) return;
    let timer = 0;
    let controller = null;
    const search = () => {
      if (controller) controller.abort();
      controller = new AbortController();
      const url = new URL(picker.dataset.endpoint, window.location.href);
      url.searchParams.set('q', input.value.trim());
      input.setAttribute('aria-expanded', 'true');
      fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal })
        .then((response) => {
          if (!response.ok) throw new Error('search failed');
          return response.json();
        })
        .then((payload) => renderPickerResults(picker, Array.isArray(payload.data) ? payload.data : []))
        .catch((error) => {
          if (error.name !== 'AbortError') renderPickerResults(picker, []);
        });
    };
    input.addEventListener('focus', search);
    input.addEventListener('input', () => {
      value.value = '';
      delete value.dataset.principalToman;
      delete value.dataset.label;
      input.setCustomValidity('');
      const selected = picker.querySelector('[data-pip-picker-selected]');
      if (selected) selected.hidden = true;
      value.dispatchEvent(new Event('change', { bubbles: true }));
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 180);
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { results.hidden = true; input.setAttribute('aria-expanded', 'false'); return; }
      if (event.key === 'ArrowDown') {
        const first = results.querySelector('button');
        if (first) { event.preventDefault(); first.focus(); }
      }
    });
    results.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { results.hidden = true; input.focus(); input.setAttribute('aria-expanded', 'false'); }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        const options = Array.from(results.querySelectorAll('button'));
        const index = options.indexOf(document.activeElement);
        const next = options[index + (event.key === 'ArrowDown' ? 1 : -1)];
        if (next) { event.preventDefault(); next.focus(); }
      }
    });
  });

  root.querySelectorAll('[data-pip-table-filter]').forEach((input) => {
    const tableBody = root.querySelector(`#${CSS.escape(input.dataset.pipTableFilter)}`);
    if (!tableBody) return;
    input.addEventListener('input', () => {
      const query = input.value.trim().toLocaleLowerCase();
      tableBody.querySelectorAll('tr').forEach((row) => {
        row.hidden = query !== '' && !row.textContent.toLocaleLowerCase().includes(query);
      });
    });
  });

  document.addEventListener('click', (event) => {
    root.querySelectorAll('[data-pip-picker]').forEach((picker) => {
      if (!picker.contains(event.target)) {
        const results = picker.querySelector('[data-pip-picker-results]');
        const input = picker.querySelector('[data-pip-picker-input]');
        if (results) results.hidden = true;
        if (input) input.setAttribute('aria-expanded', 'false');
      }
    });
  });

  root.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-pip-open]');
    if (openButton) {
      const dialog = document.getElementById(openButton.dataset.pipOpen);
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
        const firstControl = dialog.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (firstControl) firstControl.focus();
      }
      return;
    }

    const closeButton = event.target.closest('[data-pip-close]');
    if (closeButton) {
      const dialog = closeButton.closest('dialog');
      if (dialog && dialog.open) dialog.close();
      return;
    }

    if (event.target instanceof HTMLDialogElement && event.target.open && event.target === event.target.closest('dialog')) {
      const rect = event.target.getBoundingClientRect();
      if (event.clientY < rect.top || event.clientY > rect.bottom || event.clientX < rect.left || event.clientX > rect.right) {
        event.target.close();
      }
    }
  });

  root.querySelectorAll('form').forEach((form) => {
    if (form.method === 'dialog') return;
    form.addEventListener('submit', (event) => {
      const missingPicker = Array.from(form.querySelectorAll('[data-pip-picker]')).find((picker) => {
        const input = picker.querySelector('[data-pip-picker-input]');
        const value = picker.querySelector('[data-pip-picker-value]');
        return input && input.required && value && !value.value;
      });
      if (missingPicker) {
        const input = missingPicker.querySelector('[data-pip-picker-input]');
        input.setCustomValidity('یک مورد را از فهرست نتایج انتخاب کنید.');
        input.reportValidity();
        event.preventDefault();
        return;
      }
      const submit = form.querySelector('button[type="submit"]');
      if (submit && !submit.disabled) {
        submit.dataset.originalText = submit.textContent.trim();
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = 'در حال ثبت…';
      }
    });
  });

  root.querySelectorAll('[data-pip-picker="contract"]').forEach((picker) => {
    const value = picker.querySelector('[data-pip-picker-value]');
    const form = picker.closest('form');
    if (!value || !form) return;
    value.addEventListener('change', () => {
      const contractAmount = Number(value.dataset.principalToman || 0);
      const price = form.querySelector('[name="unit_sale_toman"]');
      const quantity = form.querySelector('[name="quantity"]');
      const product = form.querySelector('[name="product_id"]');
      if (!contractAmount || !price || !product || !product.value) return;
      const units = Math.max(1, Number(quantity && quantity.value) || 1);
      price.value = (contractAmount / units).toString();
    });
    form.querySelector('[name="quantity"]')?.addEventListener('input', () => {
      const contractAmount = Number(value.dataset.principalToman || 0);
      const units = Math.max(1, parseNumber(form.querySelector('[name="quantity"]')?.value) || 1);
      const price = form.querySelector('[name="unit_sale_toman"]');
      if (contractAmount && price) price.value = (contractAmount / units).toString();
    });
  });

  root.querySelectorAll('[data-pip-adjust-direction]').forEach((select) => {
    const form = select.closest('form');
    if (!form) return;
    const lotField = form.querySelector('[data-pip-adjust-lot]');
    const costField = form.querySelector('[data-pip-adjust-cost]');
    const shares = form.querySelector('[data-pip-adjust-shares]');
    const refresh = () => {
      const inbound = select.value === 'in';
      lotField.hidden = inbound;
      costField.hidden = !inbound;
      shares.hidden = !inbound;
      const lot = lotField.querySelector('select');
      const cost = costField.querySelector('input');
      if (lot) lot.required = !inbound;
      if (cost) cost.required = inbound;
    };
    select.addEventListener('change', refresh);
    refresh();
  });

  root.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        const rect = dialog.getBoundingClientRect();
        if (event.clientY < rect.top || event.clientY > rect.bottom || event.clientX < rect.left || event.clientX > rect.right) {
          dialog.close();
        }
      }
    });
  });

  root.querySelectorAll('form').forEach((form) => {
    const productSelect = form.querySelector('[name="product_id"]');
    const warehouseSelect = form.querySelector('[name="warehouse_id"]');
    const lotSelect = form.querySelector('[name="lot_id"]');
    if (!productSelect || !warehouseSelect || !lotSelect) return;
    const options = Array.from(lotSelect.options).slice(1).map((option) => ({
      element: option,
      product: option.dataset.product,
      warehouse: option.dataset.warehouse,
      available: Number(option.dataset.available || 0),
    }));
    const refreshLots = () => {
      const product = productSelect.value;
      const warehouse = warehouseSelect.value;
      let availableCount = 0;
      options.forEach(({ element, product: optionProduct, warehouse: optionWarehouse, available }) => {
        const matches = (!product || optionProduct === product) && (!warehouse || optionWarehouse === warehouse) && available > 0;
        element.hidden = !matches;
        element.disabled = !matches;
        if (matches) availableCount++;
      });
      if (lotSelect.selectedOptions.length && lotSelect.selectedOptions[0].disabled) lotSelect.value = '';
      lotSelect.dataset.empty = availableCount === 0 ? '1' : '0';
    };
    productSelect.addEventListener('change', refreshLots);
    warehouseSelect.addEventListener('change', refreshLots);
    refreshLots();
  });
})();
