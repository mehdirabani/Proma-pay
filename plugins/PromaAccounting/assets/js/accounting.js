(function () {
  'use strict';

  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  var activeConfirmForm = null;

  function english(value) {
    return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit]; });
  }

  function integer(value) {
    var normalized = english(value).replace(/[^0-9]/g, '');
    return Number(normalized || 0);
  }

  function localized(value) {
    return Math.trunc(Number(value) || 0).toLocaleString('fa-IR');
  }

  function money(value) {
    return localized(value) + ' تومان';
  }

  function css(root, name, fallback) {
    var value = window.getComputedStyle(root).getPropertyValue(name).trim();
    return value || fallback;
  }

  function syncCommissionUnit(root) {
    var type = root.querySelector('[data-accounting-commission-type]');
    var unit = root.querySelector('[data-accounting-commission-unit]');
    if (type && unit) unit.textContent = type.value === 'fixed' ? 'تومان' : 'درصد';
  }

  function syncSwitch(input) {
    input.setAttribute('role', 'switch');
    input.setAttribute('aria-checked', input.checked ? 'true' : 'false');
    var target = input.getAttribute('data-accounting-toggle');
    target = target ? document.getElementById(target) : null;
    if (!target) return;
    target.classList.toggle('is-disabled', !input.checked);
    target.querySelectorAll('input, select').forEach(function (control) { control.disabled = !input.checked; });
  }

  function syncRounding(root) {
    var method = root.querySelector('[data-accounting-rounding-method]');
    var unit = root.querySelector('[data-accounting-rounding-unit]');
    if (method && unit) unit.disabled = method.value === 'none';
  }

  function formatMoneyInput(input) {
    if (document.activeElement === input) return;
    input.value = integer(input.value) ? integer(input.value).toLocaleString('fa-IR') : '۰';
  }

  function setupMoneyInputs(root) {
    root.querySelectorAll('[data-accounting-money-input]').forEach(function (input) {
      formatMoneyInput(input);
      input.addEventListener('focus', function () { input.value = String(integer(input.value) || ''); input.select(); });
      input.addEventListener('blur', function () { formatMoneyInput(input); });
    });
  }

  function validateLimits(form) {
    var minEnabled = form.querySelector('[name="minimum_commission_enabled"]');
    var maxEnabled = form.querySelector('[name="maximum_commission_enabled"]');
    var min = form.querySelector('[name="minimum_commission"]');
    var max = form.querySelector('[name="maximum_commission"]');
    if (!min || !max) return true;
    min.setCustomValidity('');
    min.removeAttribute('aria-invalid');
    if (minEnabled && maxEnabled && minEnabled.checked && maxEnabled.checked && integer(min.value) > integer(max.value)) {
      min.setCustomValidity('حداقل کمیسیون نمی‌تواند از حداکثر کمیسیون بیشتر باشد.');
      min.setAttribute('aria-invalid', 'true');
      return false;
    }
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
    if (dialog) dialog.addEventListener('close', function () {
      if (dialog.returnValue === 'leave' && pendingHref) {
        leaving = true;
        window.location.href = pendingHref;
      }
      pendingHref = '';
    });
  }

  function setupLedgerPreview(form) {
    var type = form.querySelector('[name="entry_type"]');
    var amount = form.querySelector('[name="amount"]');
    var effect = form.querySelector('[data-ledger-effect]');
    var amountOutput = form.querySelector('[data-ledger-amount]');
    var after = form.querySelector('[data-ledger-after]');
    var before = Number(form.getAttribute('data-balance') || 0);
    if (!type || !amount || !effect || !amountOutput || !after) return;
    function render() {
      var option = type.options[type.selectedIndex];
      var direction = option ? option.getAttribute('data-direction') : 'increase';
      var value = integer(amount.value);
      effect.textContent = direction === 'decrease' ? 'کاهش مانده' : 'افزایش مانده';
      effect.className = direction === 'decrease' ? 'proma-accounting-negative' : 'proma-accounting-positive';
      amountOutput.textContent = (direction === 'decrease' ? '- ' : '+ ') + money(value);
      amountOutput.className = direction === 'decrease' ? 'proma-accounting-negative' : 'proma-accounting-positive';
      after.textContent = money(direction === 'decrease' ? before - value : before + value);
    }
    type.addEventListener('change', render);
    amount.addEventListener('input', render);
    render();
  }

  function setupConfirmation(root) {
    var dialog = root.querySelector('[data-accounting-confirm-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var reasonInput = dialog.querySelector('[data-confirm-reason]');
    root.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-accounting-confirm]');
      if (!form || form.dataset.confirmed === '1') return;
      event.preventDefault();
      activeConfirmForm = form;
      var operation = form.dataset.confirmOperation || 'عملیات مالی';
      var confirmAmount = form.dataset.confirmAmount || '—';
      var confirmEffect = form.dataset.confirmEffect || 'ثبت در سوابق حسابداری';
      if (form.matches('[data-accounting-ledger-form]')) {
        var type = form.querySelector('[name="entry_type"]');
        var amount = form.querySelector('[name="amount"]');
        var option = type && type.options[type.selectedIndex];
        var direction = option ? option.getAttribute('data-direction') : 'increase';
        operation = option ? 'ثبت سند «' + option.textContent.trim() + '»' : operation;
        confirmAmount = money(amount ? integer(amount.value) : 0);
        confirmEffect = direction === 'decrease' ? 'کاهش مانده حساب' : 'افزایش مانده حساب';
      }
      dialog.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || 'تأیید عملیات مالی';
      dialog.querySelector('[data-confirm-description]').textContent = form.dataset.confirmDescription || form.getAttribute('data-accounting-confirm') || 'اثر این عملیات را بررسی کنید.';
      dialog.querySelector('[data-confirm-person]').textContent = form.dataset.confirmPerson || '—';
      dialog.querySelector('[data-confirm-operation]').textContent = operation;
      dialog.querySelector('[data-confirm-amount]').textContent = confirmAmount;
      dialog.querySelector('[data-confirm-effect]').textContent = confirmEffect;
      reasonInput.value = '';
      reasonInput.required = form.dataset.confirmReasonRequired === '1';
      dialog.showModal();
      window.setTimeout(function () { reasonInput.focus(); }, 30);
    });
    dialog.querySelector('[data-confirm-submit]').addEventListener('click', function () {
      if (!activeConfirmForm) return;
      if (reasonInput.required && !reasonInput.value.trim()) {
        reasonInput.setCustomValidity('علت انجام عملیات را وارد کنید.');
        reasonInput.reportValidity();
        return;
      }
      reasonInput.setCustomValidity('');
      var reason = activeConfirmForm.querySelector('[name="reason"]');
      if (!reason) {
        reason = document.createElement('input');
        reason.type = 'hidden';
        reason.name = 'reason';
        activeConfirmForm.appendChild(reason);
      }
      reason.value = reasonInput.value.trim();
      activeConfirmForm.dataset.confirmed = '1';
      dialog.close();
      activeConfirmForm.requestSubmit();
      activeConfirmForm = null;
    });
    dialog.addEventListener('close', function () { activeConfirmForm = null; });
  }

  function setupSubmitLoading(root) {
    root.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        window.setTimeout(function () {
          form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            if (button.disabled) return;
            button.disabled = true;
            button.classList.add('is-loading');
            var icon = button.querySelector('svg');
            if (icon) icon.outerHTML = '<i data-feather="loader"></i>';
          });
          if (window.feather) window.feather.replace();
        }, 0);
      });
    });
  }

  function setupSettingsNav(root) {
    var links = root.querySelectorAll('[data-accounting-settings-nav] a[href^="#"]');
    if (!links.length || !window.IntersectionObserver) return;
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        links.forEach(function (link) { link.classList.toggle('is-active', link.getAttribute('href') === '#' + entry.target.id); });
      });
    }, {rootMargin: '-20% 0px -70% 0px'});
    links.forEach(function (link) {
      var target = root.querySelector(link.getAttribute('href'));
      if (target) observer.observe(target);
    });
  }

  function setupHelpSearch(root) {
    var input = root.querySelector('[data-accounting-help-search]');
    if (!input) return;
    var sections = root.querySelectorAll('.proma-accounting-help-content > section');
    input.addEventListener('input', function () {
      var query = input.value.trim();
      sections.forEach(function (section) { section.hidden = query !== '' && section.textContent.indexOf(query) === -1; });
    });
  }

  function setupCharts(root) {
    if (!window.Chart) return;
    root.querySelectorAll('[data-accounting-chart]').forEach(function (canvas) {
      var labels;
      var receipts;
      var payments;
      try {
        labels = JSON.parse(canvas.dataset.labels || '[]');
        receipts = JSON.parse(canvas.dataset.receipts || '[]');
        payments = JSON.parse(canvas.dataset.payments || '[]');
      } catch (error) { return; }
      if (!labels.length) return;
      var textColor = css(root, '--pa-muted', '#73768a');
      var borderColor = css(root, '--pa-border', '#e9e6f0');
      new window.Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {labels: labels, datasets: [
          {label: 'دریافت', data: receipts, borderColor: css(root, '--pa-success', '#22a65a'), backgroundColor: 'rgba(34,166,90,.08)', borderWidth: 2, pointRadius: 3, tension: .35, fill: false},
          {label: 'پرداخت', data: payments, borderColor: css(root, '--pa-danger', '#d94a4a'), backgroundColor: 'rgba(217,74,74,.07)', borderWidth: 2, pointRadius: 3, tension: .35, fill: false}
        ]},
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {mode: 'index', intersect: false},
          plugins: {
            legend: {display: false, rtl: true, labels: {color: textColor}},
            tooltip: {
              rtl: true,
              callbacks: {
                label: function (context) { return context.dataset.label + ': ' + money(context.parsed.y); }
              }
            }
          },
          scales: {
            x: {grid: {display: false}, ticks: {color: textColor}},
            y: {
              border: {display: false},
              grid: {color: borderColor},
              ticks: {color: textColor, callback: function (value) { return localized(value); }}
            }
          }
        }
      });
    });
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
    setupMoneyInputs(root);
    setupDirtyWarning(root);
    setupConfirmation(root);
    setupSubmitLoading(root);
    setupSettingsNav(root);
    setupHelpSearch(root);
    setupCharts(root);
  });
})();
