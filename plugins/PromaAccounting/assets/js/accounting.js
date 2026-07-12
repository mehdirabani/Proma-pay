(function () {
  'use strict';

  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};

  function integer(value) {
    var normalized = String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit]; }).replace(/[^0-9]/g, '');
    return Number(normalized || 0);
  }

  function money(value) {
    return Math.trunc(Number(value) || 0).toLocaleString('fa-IR') + ' تومان';
  }

  function syncCommissionUnit(root) {
    var type = root.querySelector('[data-accounting-commission-type]');
    var unit = root.querySelector('[data-accounting-commission-unit]');
    if (type && unit) {
      unit.textContent = type.value === 'fixed' ? 'تومان' : 'درصد';
    }
  }

  function syncSwitch(input) {
    input.setAttribute('role', 'switch');
    input.setAttribute('aria-checked', input.checked ? 'true' : 'false');
    var targetId = input.getAttribute('data-accounting-toggle');
    var target = targetId ? document.getElementById(targetId) : null;
    if (target) {
      target.classList.toggle('is-disabled', !input.checked);
      target.querySelectorAll('input, select').forEach(function (control) {
        control.disabled = !input.checked;
      });
    }
  }

  function syncRounding(root) {
    var method = root.querySelector('[data-accounting-rounding-method]');
    var unit = root.querySelector('[data-accounting-rounding-unit]');
    if (method && unit) {
      unit.disabled = method.value === 'none';
    }
  }

  function validateLimits(form) {
    var minEnabled = form.querySelector('[name="minimum_commission_enabled"]');
    var maxEnabled = form.querySelector('[name="maximum_commission_enabled"]');
    var min = form.querySelector('[name="minimum_commission"]');
    var max = form.querySelector('[name="maximum_commission"]');
    if (!min || !max) return true;
    min.setCustomValidity('');
    if (minEnabled && maxEnabled && minEnabled.checked && maxEnabled.checked && integer(min.value) > integer(max.value)) {
      min.setCustomValidity('حداقل کمیسیون نمی‌تواند از حداکثر کمیسیون بیشتر باشد.');
      min.setAttribute('aria-invalid', 'true');
      return false;
    }
    min.removeAttribute('aria-invalid');
    return true;
  }

  function setupDirtyWarning(root) {
    var form = root.querySelector('[data-accounting-dirty-form]');
    var dialog = root.querySelector('[data-accounting-unsaved-dialog]');
    if (!form) return;
    var dirty = false;
    var leaving = false;
    var pendingHref = '';
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function (event) {
      if (!validateLimits(form)) {
        event.preventDefault();
        form.reportValidity();
        return;
      }
      dirty = false;
    });
    window.addEventListener('beforeunload', function (event) {
      if (!dirty || leaving) return;
      event.preventDefault();
      event.returnValue = '';
    });
    document.addEventListener('click', function (event) {
      var link = event.target.closest('a[href]');
      if (!dirty || !link || link.target === '_blank' || link.getAttribute('href').charAt(0) === '#') return;
      if (!dialog || typeof dialog.showModal !== 'function') return;
      event.preventDefault();
      pendingHref = link.href;
      dialog.showModal();
    });
    if (dialog) {
      dialog.addEventListener('close', function () {
        if (dialog.returnValue === 'leave' && pendingHref) {
          leaving = true;
          window.location.href = pendingHref;
        }
        pendingHref = '';
      });
    }
  }

  function setupLedgerPreview(form) {
    var type = form.querySelector('[name="entry_type"]');
    var amount = form.querySelector('[name="amount"]');
    var effect = form.querySelector('[data-ledger-effect]');
    var amountOutput = form.querySelector('[data-ledger-amount]');
    var after = form.querySelector('[data-ledger-after]');
    var before = Number(form.getAttribute('data-balance') || 0);
    function render() {
      var option = type.options[type.selectedIndex];
      var direction = option ? option.getAttribute('data-direction') : 'increase';
      var value = integer(amount.value);
      effect.textContent = direction === 'decrease' ? 'کاهش مانده' : 'افزایش مانده';
      amountOutput.textContent = money(value);
      after.textContent = money(direction === 'decrease' ? before - value : before + value);
    }
    type.addEventListener('change', render);
    amount.addEventListener('input', render);
    render();
  }

  document.querySelectorAll('.proma-accounting').forEach(function (root) {
    syncCommissionUnit(root);
    syncRounding(root);
    root.querySelectorAll('.proma-accounting-switch-row > input[type="checkbox"]').forEach(function (input) {
      syncSwitch(input);
      input.addEventListener('change', function () { syncSwitch(input); });
    });
    root.querySelectorAll('[data-accounting-commission-type]').forEach(function (input) {
      input.addEventListener('change', function () { syncCommissionUnit(root); });
    });
    var method = root.querySelector('[data-accounting-rounding-method]');
    if (method) method.addEventListener('change', function () { syncRounding(root); });
    var ledger = root.querySelector('[data-accounting-ledger-form]');
    if (ledger) setupLedgerPreview(ledger);
    setupDirtyWarning(root);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-accounting-confirm]');
    if (form && !window.confirm(form.getAttribute('data-accounting-confirm'))) {
      event.preventDefault();
    }
  });
})();
