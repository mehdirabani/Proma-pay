(function () {
  const toEnglishDigits = function (value) {
    return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
      return '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(digit) % 10;
    });
  };

  const parseMoney = function (value) {
    return Number(toEnglishDigits(value).replace(/[^\d.]/g, '')) || 0;
  };

  const formatMoney = function (value) {
    return Math.ceil(Number(value) || 0).toLocaleString('fa-IR') + ' تومان';
  };

  const toPersianDigits = function (value) {
    return String(value || '').replace(/\d/g, function (digit) {
      return '۰۱۲۳۴۵۶۷۸۹'[Number(digit)];
    });
  };

  const gregorianToJalali = function (gy, gm, gd) {
    const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];
    let jy = -1595 + (33 * Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);
    return [jy, jm, jd];
  };

  const jalaliToGregorian = function (jy, jm, jd) {
    jy += 1595;
    let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd;
    days += jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186;
    let gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * Math.floor(--days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      gy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    let gd = days + 1;
    const leap = (gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0);
    const months = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    while (gm < 13 && gd > months[gm]) {
      gd -= months[gm];
      gm++;
    }
    return [gy, gm, gd];
  };

  const todayJalali = function () {
    const now = new Date();
    return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
  };

  const jalaliMonthLength = function (year, month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    const g = jalaliToGregorian(year, 12, 30);
    const j = gregorianToJalali(g[0], g[1], g[2]);
    return j[1] === 12 && j[2] === 30 ? 30 : 29;
  };

  const formatJalaliDate = function (year, month, day) {
    return toPersianDigits(String(year).padStart(4, '0') + '/' + String(month).padStart(2, '0') + '/' + String(day).padStart(2, '0'));
  };

  const parseJalaliParts = function (value) {
    const clean = toEnglishDigits(value).trim();
    const match = clean.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (!match) return todayJalali();
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (year > 1600) return gregorianToJalali(year, month, day);
    return [year, Math.max(1, Math.min(12, month)), Math.max(1, Math.min(31, day))];
  };

  const showToast = function (message, type) {
    const toast = document.createElement('div');
    toast.className = 'proma-toast ' + (type || '');
    toast.textContent = message;
    document.body.appendChild(toast);
    window.setTimeout(function () { toast.classList.add('show'); }, 20);
    window.setTimeout(function () {
      toast.classList.remove('show');
      window.setTimeout(function () { toast.remove(); }, 220);
    }, 4200);
  };

  const decodeHtmlEntities = function (value) {
    if (!value || value.indexOf('&') === -1) return value || '';
    const box = document.createElement('textarea');
    let decoded = String(value);
    for (let i = 0; i < 2; i += 1) {
      box.innerHTML = decoded;
      if (box.value === decoded) break;
      decoded = box.value;
    }
    return decoded;
  };

  const initAuthTabs = function () {
    document.querySelectorAll('[data-open-auth]').forEach(function (button) {
      button.addEventListener('click', function () {
        const targetId = button.getAttribute('data-open-auth');
        document.querySelectorAll('[data-open-auth]').forEach(function (item) {
          item.classList.toggle('active', item.getAttribute('data-open-auth') === targetId);
        });
        document.querySelectorAll('.auth-form').forEach(function (form) {
          form.classList.toggle('active', form.id === targetId);
        });
      });
    });
  };

  const initMoneyInputs = function () {
    document.querySelectorAll('[data-money]').forEach(function (input) {
      if (input.dataset.moneyBound === '1') return;
      input.dataset.moneyBound = '1';
      const format = function () {
        const raw = toEnglishDigits(input.value).replace(/[^\d.]/g, '');
        if (raw === '') {
          input.value = '';
          return;
        }
        const parts = raw.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = parts.join('.');
      };
      input.addEventListener('input', format);
      format();
    });
  };

  const initModals = function () {
    let lastOpener = null;
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const openModals = function () {
      return Array.from(document.querySelectorAll('.modal.open'));
    };
    const syncPageState = function () {
      const isOpen = openModals().length > 0;
      document.body.classList.toggle('proma-modal-open', isOpen);
      document.documentElement.classList.toggle('proma-modal-open', isOpen);
    };
    const focusInitial = function (modal) {
      window.requestAnimationFrame(function () {
        const explicit = modal.querySelector('[data-modal-autofocus]');
        const focusable = explicit || modal.querySelector(focusableSelector);
        if (focusable) focusable.focus({ preventScroll: true });
      });
    };
    const showModal = function (modal, opener) {
      if (!modal) return;
      lastOpener = opener || document.activeElement;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      syncPageState();
      focusInitial(modal);
    };
    const hideModal = function (modal, restoreFocus) {
      if (!modal) return;
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      syncPageState();
      if (restoreFocus !== false && lastOpener && typeof lastOpener.focus === 'function' && document.contains(lastOpener)) {
        lastOpener.focus({ preventScroll: true });
      }
    };
    const closeTopmostModal = function () {
      const opened = openModals();
      const modal = opened[opened.length - 1];
      if (modal && modal.getAttribute('data-modal-static') !== 'true') hideModal(modal);
    };

    document.querySelectorAll('.modal').forEach(function (modal) {
      if (!modal.hasAttribute('role')) modal.setAttribute('role', 'dialog');
      if (!modal.hasAttribute('tabindex')) modal.setAttribute('tabindex', '-1');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-hidden', modal.classList.contains('open') ? 'false' : 'true');
      const heading = modal.querySelector('.modal-header h1, .modal-header h2, .modal-header h3, .modal-header h4, .modal-header h5, .modal-header h6');
      if (heading) {
        if (!heading.id) heading.id = 'modal-title-' + Math.random().toString(36).slice(2, 10);
        modal.setAttribute('aria-labelledby', heading.id);
      }
    });

    document.querySelectorAll('[data-open-modal]').forEach(function (button) {
      if (button.dataset.modalOpenBound === '1') return;
      button.dataset.modalOpenBound = '1';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const modal = document.getElementById(button.getAttribute('data-open-modal'));
        showModal(modal, button);
      });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
      if (button.dataset.modalCloseBound === '1') return;
      button.dataset.modalCloseBound = '1';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const modal = button.closest('.modal');
        hideModal(modal);
      });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
      if (modal.dataset.modalBackdropBound === '1') return;
      modal.dataset.modalBackdropBound = '1';
      modal.addEventListener('click', function (event) {
        if (event.target === modal && modal.getAttribute('data-modal-static') !== 'true') hideModal(modal);
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeTopmostModal();
        return;
      }
      if (event.key !== 'Tab') return;
      const opened = openModals();
      const modal = opened[opened.length - 1];
      if (!modal) return;
      const focusable = Array.from(modal.querySelectorAll(focusableSelector)).filter(function (element) {
        return element.offsetParent !== null;
      });
      if (!focusable.length) {
        event.preventDefault();
        modal.focus();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    const params = new URLSearchParams(window.location.search);
    const targetId = params.get('open') || (window.location.hash ? window.location.hash.slice(1) : '');
    if (targetId && /^[a-z0-9_-]+$/i.test(targetId)) {
      const targetModal = document.getElementById(targetId);
      if (targetModal && targetModal.classList.contains('modal')) showModal(targetModal, document.activeElement);
    }
    syncPageState();
  };

  const initFilters = function () {
    document.querySelectorAll('[data-select-filter]').forEach(function (input) {
      if (input.dataset.selectFilterBound === '1') return;
      input.dataset.selectFilterBound = '1';
      input.addEventListener('input', function () {
        const target = document.getElementById(input.getAttribute('data-select-filter'));
        if (!target) return;
        const needle = toEnglishDigits(input.value).toLowerCase();
        Array.from(target.options).forEach(function (option) {
          const haystack = toEnglishDigits(option.textContent).toLowerCase();
          option.hidden = needle && !haystack.includes(needle);
        });
      });
    });

    document.querySelectorAll('[data-exclusive]').forEach(function (group) {
      if (group.dataset.exclusiveBound === '1') return;
      group.dataset.exclusiveBound = '1';
      const boxes = Array.from(group.querySelectorAll('input[type="checkbox"]'));
      boxes.forEach(function (box) {
        box.addEventListener('change', function () {
          if (box.checked) {
            boxes.forEach(function (other) {
              if (other !== box) other.checked = false;
            });
          }
          if (!boxes.some(function (item) { return item.checked; }) && boxes[0]) {
            boxes[0].checked = true;
          }
        });
      });
    });
  };

  const initContactActions = function () {
    document.querySelectorAll('[data-copy-phone]').forEach(function (button) {
      if (button.dataset.copyPhoneBound === '1') return;
      button.dataset.copyPhoneBound = '1';
      button.addEventListener('click', function () {
        const value = String(button.getAttribute('data-copy-phone') || '').trim();
        if (!value) {
          showToast('شماره قابل کپی نیست.', 'error');
          return;
        }
        const copied = function () { showToast('شماره تماس کپی شد.', 'success'); };
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(value).then(copied).catch(function () { fallbackCopy(value, copied); });
          return;
        }
        fallbackCopy(value, copied);
      });
    });
  };

  const fallbackCopy = function (value, onSuccess) {
    const input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    try {
      if (document.execCommand('copy')) onSuccess();
      else showToast('کپی خودکار انجام نشد.', 'error');
    } catch (error) {
      showToast('کپی خودکار انجام نشد.', 'error');
    }
    input.remove();
  };

  const hydrateAjaxContent = function () {
    initMoneyInputs();
    initModals();
    initFilters();
    initCustomerLiveSearch();
    initGuarantorLiveSearch();
    initUserLiveSearch();
    initContractPickers();
    initRepeaters();
    initContractForms();
    initContractNewCustomerPopup();
    initPaymentMethodShells();
    initPaymentPreviews();
    initJalaliDateInputs();
    initCalendarReminderFields();
    initLoadingForms();
    initRequiredLabels();
    initCharts();
    initCardLinks();
    initContactActions();
    if (window.feather && typeof window.feather.replace === 'function') {
      window.feather.replace();
    }
  };

  const initAjaxFilters = function () {
    document.querySelectorAll('form[data-ajax-filter]').forEach(function (form) {
      if (form.dataset.ajaxFilterBound === '1') return;
      form.dataset.ajaxFilterBound = '1';
      const targetSelector = form.getAttribute('data-ajax-target');
      const status = form.querySelector('[data-ajax-status]');
      const delay = parseInt(form.getAttribute('data-ajax-delay') || '450', 10);
      let timer = null;
      let controller = null;

      const setStatus = function (message, state) {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('loading', state === 'loading');
        status.classList.toggle('error', state === 'error');
      };

      const buildUrl = function () {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        const data = new FormData(form);
        Array.from(url.searchParams.keys()).forEach(function (key) {
          if (key !== 'route') url.searchParams.delete(key);
        });
        data.forEach(function (value, key) {
          if (value !== null && String(value).trim() !== '') {
            url.searchParams.set(key, value);
          } else if (key !== 'route') {
            url.searchParams.delete(key);
          }
        });
        url.searchParams.set('ajax', '1');
        return url;
      };

      const load = function () {
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) return;
        if (controller) controller.abort();
        controller = new AbortController();
        const requestUrl = buildUrl();
        setStatus('در حال جستجو...', 'loading');
        target.classList.add('proma-ajax-loading');
        fetch(requestUrl.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          signal: controller.signal,
          credentials: 'same-origin'
        }).then(function (response) {
          if (!response.ok) throw new Error('درخواست جستجو ناموفق بود.');
          return response.text();
        }).then(function (html) {
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const next = doc.querySelector(targetSelector);
          if (!next) throw new Error('بخش نتیجه در پاسخ پیدا نشد.');
          target.innerHTML = next.innerHTML;
          target.classList.remove('proma-ajax-loading');
          setStatus('نتایج به‌روزرسانی شد.', '');
          const cleanUrl = new URL(requestUrl.toString());
          cleanUrl.searchParams.delete('ajax');
          if (window.history) {
            window.history.replaceState(null, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
          }
          hydrateAjaxContent();
        }).catch(function (error) {
          if (error.name === 'AbortError') return;
          target.classList.remove('proma-ajax-loading');
          setStatus('خطا در دریافت نتیجه. اتصال یا فیلترها را بررسی کنید.', 'error');
          showToast('جستجو انجام نشد. چند لحظه بعد دوباره تلاش کنید.', 'error');
        });
      };

      const schedule = function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(load, Math.max(150, delay));
      };

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(timer);
        load();
      });
      form.querySelectorAll('input, select').forEach(function (field) {
        if (field.type === 'hidden' || field.type === 'file') return;
        field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', schedule);
      });
    });
  };

  const initSettingsTabs = function () {
    const activeInput = document.querySelector('[data-settings-active-tab]');
    document.querySelectorAll('[data-settings-tab]').forEach(function (tab) {
      tab.addEventListener('click', function (event) {
        const key = tab.getAttribute('data-settings-tab');
        const panel = document.querySelector('[data-settings-panel="' + key + '"]');
        if (!panel) return;
        event.preventDefault();
        document.querySelectorAll('[data-settings-tab]').forEach(function (item) {
          item.classList.toggle('active', item === tab);
        });
        document.querySelectorAll('[data-settings-panel]').forEach(function (item) {
          item.classList.toggle('active', item === panel);
        });
        if (activeInput) activeInput.value = key;
        if (window.history && tab.href) {
          window.history.replaceState(null, '', tab.href);
        }
      });
    });
  };

  const initCustomerLiveSearch = function () {
    document.querySelectorAll('[data-customer-live-search]').forEach(function (box) {
      if (box.dataset.customerLiveBound === '1') return;
      box.dataset.customerLiveBound = '1';
      const endpoint = box.getAttribute('data-search-url');
      const input = box.querySelector('[data-customer-search-input]');
      const results = box.querySelector('[data-customer-search-results]');
      const form = box.closest('form');
      const select = box.querySelector('[data-customer-select]') || (form ? form.querySelector('[data-customer-select]') : null);
      if (!endpoint || !input || !results || !select) return;
      let timer = null;
      let request = null;
      let highlighted = -1;

      const close = function () {
        results.hidden = true;
        results.innerHTML = '';
        highlighted = -1;
      };

      const choose = function (item) {
        if (select.tagName === 'SELECT') {
          select.innerHTML = '';
          const option = document.createElement('option');
          option.value = String(item.id);
          option.textContent = item.full_name + ' - ' + (item.national_id || '') + ' - ' + (item.mobile || '');
          option.selected = true;
          select.appendChild(option);
        } else {
          select.value = String(item.id);
          select.dataset.customerLabel = item.full_name || '';
          select.dataset.customerName = item.full_name || '';
          select.dataset.customerMobile = item.mobile || '';
          select.dataset.customerNationalId = item.national_id || '';
        }
        select.value = String(item.id);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        input.value = item.full_name || '';
        box.dispatchEvent(new CustomEvent('proma:customer-selected', { bubbles: true, detail: item }));
        close();
      };

      const render = function (items) {
        results.innerHTML = '';
        if (!items.length) {
          const empty = document.createElement('span');
          empty.className = 'proma-live-empty';
          empty.textContent = 'نتیجه‌ای پیدا نشد.';
          results.appendChild(empty);
          results.hidden = false;
          return;
        }
        items.forEach(function (item) {
          const button = document.createElement('button');
          const title = document.createElement('strong');
          const meta = document.createElement('small');
          const badge = document.createElement('em');
          button.type = 'button';
          title.textContent = item.full_name || 'بدون نام';
          meta.textContent = 'موبایل: ' + (item.mobile || '-') + ' | کد ملی: ' + (item.national_id || '-') + ' | شناسه: ' + (item.customer_number || item.id || '-');
          badge.textContent = item.status_label || item.status || '-';
          button.appendChild(title);
          button.appendChild(meta);
          button.appendChild(badge);
          button.addEventListener('click', function () { choose(item); });
          results.appendChild(button);
        });
        results.hidden = false;
        highlighted = -1;
      };

      const setHighlight = function (index) {
        const buttons = Array.from(results.querySelectorAll('button'));
        if (!buttons.length) return;
        highlighted = (index + buttons.length) % buttons.length;
        buttons.forEach(function (button, buttonIndex) {
          button.classList.toggle('is-highlighted', buttonIndex === highlighted);
        });
        buttons[highlighted].scrollIntoView({ block: 'nearest' });
      };

      input.addEventListener('input', function () {
        const query = input.value.trim();
        select.value = '';
        if (select.tagName === 'SELECT') {
          select.innerHTML = '<option value=""></option>';
        } else {
          delete select.dataset.customerLabel;
          delete select.dataset.customerName;
          delete select.dataset.customerMobile;
          delete select.dataset.customerNationalId;
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
        window.clearTimeout(timer);
        if (toEnglishDigits(query).length < 2) {
          close();
          return;
        }
        timer = window.setTimeout(function () {
          if (request && typeof request.abort === 'function') request.abort();
          request = typeof AbortController !== 'undefined' ? new AbortController() : null;
          const separator = endpoint.indexOf('?') >= 0 ? '&' : '?';
          fetch(endpoint + separator + 'q=' + encodeURIComponent(query), request ? { signal: request.signal } : {}).then(function (response) {
            return response.json();
          }).then(function (json) {
            render(json.ok && Array.isArray(json.items) ? json.items : []);
          }).catch(function (error) {
            if (error && error.name === 'AbortError') return;
            close();
          });
        }, 300);
      });

      input.addEventListener('keydown', function (event) {
        if (results.hidden) return;
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          setHighlight(highlighted + 1);
        } else if (event.key === 'ArrowUp') {
          event.preventDefault();
          setHighlight(highlighted - 1);
        } else if (event.key === 'Enter' && highlighted >= 0) {
          event.preventDefault();
          const button = results.querySelectorAll('button')[highlighted];
          if (button) button.click();
        } else if (event.key === 'Escape') {
          close();
        }
      });

      document.addEventListener('click', function (event) {
        if (!box.contains(event.target)) close();
      });
    });
  };

  const initGuarantorLiveSearch = function () {
    document.querySelectorAll('[data-guarantor-live-search]').forEach(function (box) {
      if (box.dataset.guarantorLiveBound === '1') return;
      box.dataset.guarantorLiveBound = '1';
      const endpoint = box.getAttribute('data-search-url');
      const input = box.querySelector('[data-guarantor-search-input]');
      const results = box.querySelector('[data-guarantor-search-results]');
      const chips = box.querySelector('[data-guarantor-chips]');
      const form = box.closest('form');
      const customerSelect = form ? form.querySelector('[data-customer-select]') : null;
      if (!endpoint || !input || !results || !chips) return;
      let timer = null;

      const close = function () {
        results.hidden = true;
        results.innerHTML = '';
      };

      const selectedIds = function () {
        return Array.from(chips.querySelectorAll('[data-guarantor-chip]')).map(function (chip) {
          return chip.getAttribute('data-guarantor-id');
        });
      };

      const removeChip = function (chip) {
        if (chip) chip.remove();
      };

      const bindChip = function (chip) {
        const closeButton = chip.querySelector('button');
        if (!closeButton || closeButton.getAttribute('data-bound') === '1') return;
        closeButton.setAttribute('data-bound', '1');
        closeButton.addEventListener('click', function () {
          removeChip(chip);
          input.focus();
        });
      };

      const addChip = function (item) {
        const id = String(item.id || '');
        if (!id) return;
        if (customerSelect && customerSelect.value && String(customerSelect.value) === id) {
          showToast('مشتری اصلی نمی‌تواند ضامن خودش باشد.', 'error');
          return;
        }
        if (selectedIds().includes(id)) {
          showToast('این ضامن قبلاً انتخاب شده است.', 'error');
          return;
        }
        const chip = document.createElement('span');
        const hidden = document.createElement('input');
        const closeButton = document.createElement('button');
        chip.className = 'proma-chip';
        chip.setAttribute('data-guarantor-chip', '1');
        chip.setAttribute('data-guarantor-id', id);
        chip.appendChild(document.createTextNode((item.full_name || 'ضامن') + (item.mobile ? ' - ' + item.mobile : '')));
        hidden.type = 'hidden';
        hidden.name = 'guarantors[]';
        hidden.value = id;
        closeButton.type = 'button';
        closeButton.textContent = '×';
        closeButton.setAttribute('aria-label', 'حذف انتخاب');
        chip.appendChild(hidden);
        chip.appendChild(closeButton);
        chips.appendChild(chip);
        bindChip(chip);
        input.value = '';
        close();
      };

      const renderState = function (message, className) {
        results.innerHTML = '';
        const empty = document.createElement('span');
        empty.className = className || 'proma-live-empty';
        empty.textContent = message;
        results.appendChild(empty);
        results.hidden = false;
      };

      const render = function (items) {
        results.innerHTML = '';
        if (!items.length) {
          renderState('موردی یافت نشد.', 'proma-live-empty');
          return;
        }
        items.forEach(function (item) {
          const button = document.createElement('button');
          const title = document.createElement('strong');
          const meta = document.createElement('small');
          const badge = document.createElement('em');
          button.type = 'button';
          title.textContent = item.full_name || 'بدون نام';
          meta.textContent = 'موبایل: ' + (item.mobile || '-') + ' | کد ملی: ' + (item.national_id || '-') + ' | شماره مشتری: ' + (item.customer_number || item.id || '-');
          badge.textContent = 'ضامن';
          button.appendChild(title);
          button.appendChild(meta);
          button.appendChild(badge);
          button.addEventListener('click', function () { addChip(item); });
          results.appendChild(button);
        });
        results.hidden = false;
      };

      chips.querySelectorAll('[data-guarantor-chip]').forEach(bindChip);
      input.addEventListener('input', function () {
        const query = input.value.trim();
        window.clearTimeout(timer);
        if (toEnglishDigits(query).length < 2) {
          close();
          return;
        }
        renderState('در حال جستجو...', 'proma-live-empty');
        timer = window.setTimeout(function () {
          fetch(endpoint + '&q=' + encodeURIComponent(query)).then(function (response) {
            return response.json();
          }).then(function (json) {
            render(json.ok && Array.isArray(json.items) ? json.items : []);
          }).catch(function () {
            renderState('خطا در جستجو. دوباره تلاش کنید.', 'proma-live-empty');
          });
        }, 300);
      });

      document.addEventListener('click', function (event) {
        if (!box.contains(event.target)) close();
      });
    });
  };

  const initUserLiveSearch = function () {
    document.querySelectorAll('[data-user-live-search]').forEach(function (box) {
      if (box.dataset.userLiveBound === '1') return;
      box.dataset.userLiveBound = '1';
      const endpoint = box.getAttribute('data-search-url');
      const input = box.querySelector('[data-user-search-input]');
      const results = box.querySelector('[data-user-search-results]');
      const hidden = box.querySelector('[data-user-id-input]');
      const chipRow = box.querySelector('[data-user-chip]');
      if (!endpoint || !input || !results || !hidden) return;
      let timer = null;

      const close = function () {
        results.hidden = true;
        results.innerHTML = '';
      };

      const syncChip = function (item) {
        if (!chipRow) return;
        chipRow.innerHTML = '';
        if (!item) return;
        const chip = document.createElement('span');
        const closeButton = document.createElement('button');
        chip.className = 'proma-chip';
        chip.appendChild(document.createTextNode(item.full_name + (item.role_label ? ' - ' + item.role_label : '')));
        closeButton.type = 'button';
        closeButton.textContent = '×';
        closeButton.setAttribute('aria-label', 'حذف انتخاب');
        closeButton.addEventListener('click', function () {
          hidden.value = '';
          input.value = '';
          chipRow.innerHTML = '';
          input.focus();
        });
        chip.appendChild(closeButton);
        chipRow.appendChild(chip);
      };

      const choose = function (item) {
        hidden.value = String(item.id);
        input.value = item.full_name || '';
        syncChip(item);
        close();
      };

      const render = function (items) {
        results.innerHTML = '';
        if (!items.length) {
          const empty = document.createElement('span');
          empty.className = 'proma-live-empty';
          empty.textContent = 'نتیجه‌ای پیدا نشد.';
          results.appendChild(empty);
          results.hidden = false;
          return;
        }
        items.forEach(function (item) {
          const button = document.createElement('button');
          const title = document.createElement('strong');
          const meta = document.createElement('small');
          const badge = document.createElement('em');
          button.type = 'button';
          title.textContent = item.full_name || 'بدون نام';
          meta.textContent = 'موبایل: ' + (item.mobile || '-') + ' | کد ملی: ' + (item.national_id || '-');
          badge.textContent = item.role_label || item.role || '-';
          button.appendChild(title);
          button.appendChild(meta);
          button.appendChild(badge);
          button.addEventListener('click', function () { choose(item); });
          results.appendChild(button);
        });
        results.hidden = false;
      };

      input.addEventListener('input', function () {
        hidden.value = '';
        if (chipRow) chipRow.innerHTML = '';
        const value = input.value.trim();
        window.clearTimeout(timer);
        if (value.length < 2) {
          close();
          return;
        }
        timer = window.setTimeout(function () {
          fetch(endpoint + '&q=' + encodeURIComponent(value)).then(function (response) {
            return response.json();
          }).then(function (json) {
            render(json.items || []);
          }).catch(function () {
            render([]);
          });
        }, 220);
      });

      document.addEventListener('click', function (event) {
        if (!box.contains(event.target)) close();
      });
    });
  };

  const initContractPickers = function () {
    document.querySelectorAll('[data-contract-picker]').forEach(function (box) {
      if (box.dataset.contractPickerBound === '1') return;
      box.dataset.contractPickerBound = '1';
      const endpoint = box.getAttribute('data-search-url');
      const input = box.querySelector('[data-contract-picker-input]');
      const hidden = box.querySelector('[data-contract-picker-id]');
      const results = box.querySelector('[data-contract-picker-results]');
      const options = Array.from(box.querySelectorAll('option[data-contract-id]'));
      if (!input || !hidden) return;
      let timer = null;

      const close = function () {
        if (!results) return;
        results.hidden = true;
        results.innerHTML = '';
      };

      const choose = function (item) {
        hidden.value = String(item.id || '');
        input.value = item.label || [item.contract_number, item.customer_name, item.mobile].filter(Boolean).join(' - ');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
      };

      const renderState = function (message) {
        if (!results) return;
        results.innerHTML = '';
        const empty = document.createElement('span');
        empty.className = 'proma-live-empty';
        empty.textContent = message;
        results.appendChild(empty);
        results.hidden = false;
      };

      const render = function (items) {
        if (!results) return;
        results.innerHTML = '';
        if (!items.length) {
          renderState('قراردادی پیدا نشد.');
          return;
        }
        items.forEach(function (item) {
          const button = document.createElement('button');
          const title = document.createElement('strong');
          const meta = document.createElement('small');
          const badge = document.createElement('em');
          button.type = 'button';
          title.textContent = item.contract_number || 'بدون شماره';
          meta.textContent = (item.customer_name || '-') + ' | موبایل: ' + (item.mobile || '-') + ' | کد ملی: ' + (item.national_id || '-');
          badge.textContent = item.status_label || item.status || '-';
          button.appendChild(title);
          button.appendChild(meta);
          button.appendChild(badge);
          button.addEventListener('click', function () { choose(item); });
          results.appendChild(button);
        });
        results.hidden = false;
      };

      if (endpoint && results) {
        input.addEventListener('input', function () {
          const query = input.value.trim();
          hidden.value = '';
          window.clearTimeout(timer);
          if (toEnglishDigits(query).length < 2) {
            close();
            return;
          }
          renderState('در حال جستجو...');
          timer = window.setTimeout(function () {
            const separator = endpoint.indexOf('?') === -1 ? '?' : '&';
            fetch(endpoint + separator + 'q=' + encodeURIComponent(query)).then(function (response) {
              return response.json();
            }).then(function (json) {
              render(json.ok && Array.isArray(json.items) ? json.items : []);
            }).catch(function () {
              renderState('خطا در جستجو. دوباره تلاش کنید.');
            });
          }, 260);
        });
        document.addEventListener('click', function (event) {
          if (!box.contains(event.target)) close();
        });
        return;
      }

      if (!options.length) return;
      const sync = function () {
        const value = input.value.trim();
        const exact = options.find(function (option) { return option.value === value; });
        hidden.value = exact ? exact.getAttribute('data-contract-id') : '';
      };
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      sync();
    });
  };

  const initRepeaters = function () {
    document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
      if (repeater.dataset.repeaterBound === '1') return;
      repeater.dataset.repeaterBound = '1';
      const list = repeater.querySelector('[data-repeater-list]');
      const template = repeater.querySelector('[data-repeater-template]');
      const add = repeater.querySelector('[data-repeater-add]');
      if (!list || !template || !add) return;

      const bindRemove = function (row) {
        const remove = row.querySelector('[data-repeater-remove]');
        if (!remove) return;
        remove.addEventListener('click', function () {
          row.remove();
        });
      };

      list.querySelectorAll('[data-repeater-row]').forEach(bindRemove);
      add.addEventListener('click', function () {
        const index = String(Date.now()) + String(Math.floor(Math.random() * 1000));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, index).trim();
        const row = wrapper.firstElementChild;
        if (!row) return;
        bindRemove(row);
        list.appendChild(row);
      });
    });

    document.querySelectorAll('[data-guarantee-type]').forEach(function (select) {
      if (select.dataset.guaranteeTypeBound === '1') return;
      select.dataset.guaranteeTypeBound = '1';
      const section = select.closest('.proma-form-section') || select.closest('form');
      const other = section ? section.querySelector('[data-guarantee-other]') : null;
      const toggle = function () {
        if (other) other.hidden = select.value !== 'سایر';
      };
      select.addEventListener('change', toggle);
      toggle();
    });
  };

  const initContractForms = function () {
    document.querySelectorAll('[data-contract-form]').forEach(function (form) {
      if (form.dataset.contractFormBound === '1') return;
      form.dataset.contractFormBound = '1';
      const principal = form.querySelector('[name="principal_amount"]');
      const downPayment = form.querySelector('[name="down_payment_amount"]');
      const months = form.querySelector('[name="months"]');
      const rate = form.querySelector('[name="monthly_interest_rate"]');
      const financed = form.querySelector('[data-financed-balance]');
      const error = form.querySelector('[data-contract-error]');
      const customerSelect = form.querySelector('[data-customer-select]');
      const customerChip = form.querySelector('[data-customer-chip]');
      const guarantorSelect = form.querySelector('[data-guarantor-select]');
      const guarantorChips = form.querySelector('[data-guarantor-chips]');
      const newCustomerFields = form.querySelector('[data-new-customer-fields]');
      const newCustomerSummary = form.querySelector('[data-new-customer-summary]');
      const customerModeTabs = Array.from(form.querySelectorAll('[data-customer-mode-tab]'));
      const customerModePanels = Array.from(form.querySelectorAll('[data-customer-mode-panel]'));
      const identityStatus = form.querySelector('[data-customer-identity-status]');
      const identityEndpoint = form.getAttribute('data-identity-check-url');
      const previewUrl = form.getAttribute('data-preview-url');
      let previewTimer = null;

      const checkedInterestType = function () {
        const checked = form.querySelector('[name="interest_type"]:checked');
        return checked ? checked.value : 'simple';
      };

      const setText = function (selector, text) {
        const target = form.querySelector(selector);
        if (target) target.textContent = text;
      };
      const setHtml = function (selector, html, fallbackText) {
        const target = form.querySelector(selector);
        if (!target) return;
        if (html) {
          target.innerHTML = html;
        } else {
          target.textContent = fallbackText || '';
        }
      };

      const showError = function (message) {
        if (!error) return;
        error.hidden = !message;
        error.textContent = message || '';
      };

      const syncCustomerChip = function () {
        if (!customerSelect || !customerChip) return;
        customerChip.innerHTML = '';
        const option = customerSelect.tagName === 'SELECT' ? customerSelect.selectedOptions[0] : null;
        const value = customerSelect.value;
        if (!value) return;
        const label = option ? option.textContent : (customerSelect.dataset.customerLabel || 'مشتری انتخاب‌شده');
        const chip = document.createElement('span');
        const close = document.createElement('button');
        chip.className = 'proma-chip';
        chip.appendChild(document.createTextNode(label));
        close.type = 'button';
        close.textContent = '×';
        close.addEventListener('click', function () {
          customerSelect.value = '';
          if (customerSelect.tagName === 'SELECT') customerSelect.innerHTML = '<option value=""></option>';
          delete customerSelect.dataset.customerLabel;
          delete customerSelect.dataset.customerName;
          delete customerSelect.dataset.customerMobile;
          delete customerSelect.dataset.customerNationalId;
          const liveInput = form.querySelector('[data-customer-search-input]');
          if (liveInput) liveInput.value = '';
          syncCustomerChip();
          syncGuarantorChips();
        });
        chip.appendChild(close);
        customerChip.appendChild(chip);
      };

      const setCustomerMode = function (mode, clearSelection) {
        mode = mode === 'new' ? 'new' : 'existing';
        form.dataset.customerMode = mode;
        customerModeTabs.forEach(function (tab) {
          const active = tab.getAttribute('data-customer-mode-tab') === mode;
          tab.classList.toggle('active', active);
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        customerModePanels.forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-customer-mode-panel') !== mode;
        });
        if (newCustomerFields) {
          const nameField = newCustomerFields.querySelector('[name="new_customer_full_name"]');
          if (nameField) nameField.required = mode === 'new';
        }
        if (customerSelect) {
          customerSelect.required = mode === 'existing';
          if (mode === 'new' && clearSelection) {
            customerSelect.value = '';
            delete customerSelect.dataset.customerLabel;
            delete customerSelect.dataset.customerName;
            delete customerSelect.dataset.customerMobile;
            delete customerSelect.dataset.customerNationalId;
            const liveInput = form.querySelector('[data-customer-search-input]');
            if (liveInput) liveInput.value = '';
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      };

      const selectCustomer = function (item) {
        if (!customerSelect || !item || !item.id) return;
        customerSelect.value = String(item.id);
        customerSelect.dataset.customerLabel = item.full_name || '';
        customerSelect.dataset.customerName = item.full_name || '';
        customerSelect.dataset.customerMobile = item.mobile || '';
        customerSelect.dataset.customerNationalId = item.national_id || '';
        const liveInput = form.querySelector('[data-customer-search-input]');
        if (liveInput) liveInput.value = item.full_name || '';
        setCustomerMode('existing', false);
        customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        syncCustomerChip();
        syncGuarantorChips();
        syncLiveGuarantorChips();
      };

      const syncGuarantorChips = function () {
        if (!guarantorSelect || !guarantorChips) return;
        guarantorChips.innerHTML = '';
        const customerId = customerSelect ? customerSelect.value : '';
        Array.from(guarantorSelect.selectedOptions).forEach(function (option) {
          if (customerId && option.value === customerId) {
            option.selected = false;
            return;
          }
          const chip = document.createElement('span');
          const close = document.createElement('button');
          chip.className = 'proma-chip';
          chip.appendChild(document.createTextNode(option.textContent));
          close.type = 'button';
          close.textContent = '×';
          close.addEventListener('click', function () {
            option.selected = false;
            syncGuarantorChips();
          });
          chip.appendChild(close);
          guarantorChips.appendChild(chip);
        });
      };

      const syncLiveGuarantorChips = function () {
        if (!guarantorChips || guarantorSelect) return;
        const customerId = customerSelect ? customerSelect.value : '';
        if (!customerId) return;
        guarantorChips.querySelectorAll('[data-guarantor-chip]').forEach(function (chip) {
          if (chip.getAttribute('data-guarantor-id') === String(customerId)) {
            chip.remove();
          }
        });
      };

      const clearNewCustomerDraft = function () {
        if (!newCustomerFields) return;
        newCustomerFields.querySelectorAll('[name^="new_customer_"]').forEach(function (input) {
          input.value = '';
        });
        if (newCustomerSummary) {
          newCustomerSummary.hidden = true;
          newCustomerSummary.innerHTML = '';
        }
      };

      const updatePreview = function () {
        const principalValue = parseMoney(principal ? principal.value : 0);
        const downValue = parseMoney(downPayment ? downPayment.value : 0);
        const monthsValue = Number(toEnglishDigits(months ? months.value : 1).replace(/[^\d]/g, '')) || 1;
        const financedValue = Math.max(0, principalValue - downValue);
        if (financed) financed.textContent = formatMoney(financedValue);
        setText('[data-preview-principal]', formatMoney(principalValue));
        setText('[data-preview-down-payment]', formatMoney(downValue));
        setText('[data-preview-financed]', formatMoney(financedValue));
        setText('[data-preview-installment]', formatMoney(0));
        setText('[data-preview-total]', formatMoney(0));
        showError(downValue > principalValue ? 'مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ اصل قرارداد باشد.' : '');
        if (!previewUrl || principalValue <= 0 || downValue > principalValue) return;
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(function () {
          const params = new URLSearchParams({
            principal_amount: principal ? principal.value : '',
            down_payment_amount: downPayment ? downPayment.value : '',
            months: months ? months.value : '',
            monthly_interest_rate: rate ? rate.value : '',
            interest_type: checkedInterestType()
          });
          fetch(previewUrl + '&' + params.toString()).then(function (response) {
            return response.json();
          }).then(function (json) {
            if (!json.ok || !json.preview || !json.preview.formatted) return;
            setText('[data-preview-principal]', json.preview.formatted.principal_amount);
            setText('[data-preview-down-payment]', json.preview.formatted.down_payment_amount);
            setText('[data-preview-financed]', json.preview.formatted.financed_amount);
            setText('[data-preview-installment]', json.preview.formatted.installment_amount);
            setText('[data-preview-total]', json.preview.formatted.total_payable);
            if (financed) financed.textContent = json.preview.formatted.financed_amount;
          }).catch(function () {});
        }, 220);
      };

      customerModeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          setCustomerMode(tab.getAttribute('data-customer-mode-tab'), true);
        });
      });

      form.addEventListener('proma:customer-selected', function (event) {
        selectCustomer(event.detail || {});
      });

      let identityTimer = null;
      let identityRequest = null;
      let identitySequence = 0;
      const identityFields = ['national_id', 'mobile', 'email'].map(function (name) {
        return newCustomerFields ? newCustomerFields.querySelector('[data-new-customer-field="' + name + '"]') : null;
      }).filter(Boolean);
      const clearIdentityStatus = function () {
        form.dataset.identityConflict = '0';
        if (!identityStatus) return;
        identityStatus.hidden = true;
        identityStatus.className = 'proma-customer-identity-status';
        identityStatus.innerHTML = '';
      };
      const renderIdentityStatus = function (json) {
        if (!identityStatus) return;
        identityStatus.hidden = false;
        identityStatus.className = 'proma-customer-identity-status ' + (json.conflict ? 'is-warning' : (json.items && json.items.length ? 'is-warning' : 'is-success'));
        identityStatus.innerHTML = '';
        const message = document.createElement('strong');
        message.textContent = json.message || '';
        identityStatus.appendChild(message);
        form.dataset.identityConflict = json.conflict ? '1' : '0';
        if (json.items && json.items.length === 1 && !json.conflict) {
          const item = json.items[0];
          const row = document.createElement('div');
          row.className = 'proma-identity-match';
          const details = document.createElement('span');
          details.textContent = item.full_name + ' · ' + (item.mobile || '-') + ' · ' + (item.national_id || '-');
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn small secondary';
          button.textContent = 'انتخاب همین مشتری';
          button.addEventListener('click', function () { selectCustomer(item); });
          row.appendChild(details);
          row.appendChild(button);
          identityStatus.appendChild(row);
        }
      };
      const checkIdentity = function () {
        if (!identityEndpoint || !newCustomerFields) return;
        const values = {};
        identityFields.forEach(function (field) {
          values[field.getAttribute('data-new-customer-field')] = field.value.trim();
        });
        const national = toEnglishDigits(values.national_id || '').replace(/\D/g, '');
        const mobile = toEnglishDigits(values.mobile || '').replace(/\D/g, '');
        const email = values.email || '';
        if (national.length < 10 && mobile.length < 7 && !email.includes('@')) {
          clearIdentityStatus();
          return;
        }
        const sequence = ++identitySequence;
        if (identityRequest && typeof identityRequest.abort === 'function') identityRequest.abort();
        identityRequest = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const url = new URL(identityEndpoint, window.location.href);
        url.searchParams.set('national_id', national);
        url.searchParams.set('mobile', mobile);
        url.searchParams.set('email', email);
        fetch(url.toString(), identityRequest ? { signal: identityRequest.signal } : {}).then(function (response) {
          return response.json();
        }).then(function (json) {
          if (sequence !== identitySequence) return;
          renderIdentityStatus(json.ok ? json : { conflict: false, items: [], message: 'امکان بررسی اطلاعات مشتری وجود ندارد.' });
        }).catch(function (error) {
          if (error && error.name === 'AbortError') return;
          if (sequence === identitySequence) clearIdentityStatus();
        });
      };
      identityFields.forEach(function (field) {
        field.addEventListener('input', function () {
          window.clearTimeout(identityTimer);
          identityTimer = window.setTimeout(checkIdentity, 300);
        });
      });
      setCustomerMode(form.querySelector('[data-customer-select]') && form.querySelector('[data-customer-select]').value ? 'existing' : 'existing', false);

      [principal, downPayment, months, rate].forEach(function (field) {
        if (!field) return;
        field.addEventListener('input', updatePreview);
        field.addEventListener('change', updatePreview);
      });
      form.querySelectorAll('[name="interest_type"]').forEach(function (field) {
        field.addEventListener('change', updatePreview);
      });
      if (customerSelect) {
        customerSelect.addEventListener('change', function () {
          syncCustomerChip();
          syncGuarantorChips();
          syncLiveGuarantorChips();
        });
      }
      if (guarantorSelect) {
        guarantorSelect.addEventListener('change', syncGuarantorChips);
      }

      form.addEventListener('submit', function (event) {
        const principalValue = parseMoney(principal ? principal.value : 0);
        const downValue = parseMoney(downPayment ? downPayment.value : 0);
        const customerMode = form.dataset.customerMode === 'new' ? 'new' : 'existing';
        const hasCustomer = customerSelect && customerSelect.value;
        const newName = form.querySelector('[name="new_customer_full_name"]');
        if (principalValue <= 0) {
          event.preventDefault();
          showError('مبلغ اصل قرارداد را وارد کنید.');
          return;
        }
        if (downValue > principalValue) {
          event.preventDefault();
          showError('مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ اصل قرارداد باشد.');
          return;
        }
        if (customerMode === 'new' && form.dataset.identityConflict === '1') {
          event.preventDefault();
          showError('اطلاعات مشتری جدید با سوابق موجود تعارض دارد. مشتری موجود را انتخاب کنید یا اطلاعات را اصلاح کنید.');
          return;
        }
        if (customerMode === 'existing' && !hasCustomer) {
          event.preventDefault();
          showError('یک مشتری موجود را از نتایج جست‌وجو انتخاب کنید.');
          return;
        }
        if (customerMode === 'new' && (!newName || !newName.value.trim())) {
          event.preventDefault();
          showError('مشتری موجود را انتخاب کنید یا اطلاعات مشتری تازه را وارد کنید.');
        }
      });

      syncCustomerChip();
      syncGuarantorChips();
      syncLiveGuarantorChips();
      updatePreview();
    });
  };

  const initContractNewCustomerPopup = function () {
    document.querySelectorAll('[data-contract-customer-popup]').forEach(function (modal) {
      if (modal.dataset.newCustomerPopupBound === '1') return;
      modal.dataset.newCustomerPopupBound = '1';
      const apply = modal.querySelector('[data-apply-new-customer]');
      const draft = modal.querySelector('[data-new-customer-draft]');
      const form = document.getElementById('create-contract-form');
      if (!apply || !draft || !form) return;

      const fieldNames = ['full_name', 'father_name', 'issued_from', 'national_id', 'mobile', 'secondary_phone', 'address'];
      const getDraftValue = function (name) {
        const input = draft.querySelector('[data-new-customer-field="' + name + '"]');
        return input ? input.value.trim() : '';
      };

      apply.addEventListener('click', function () {
        const fullName = getDraftValue('full_name');
        if (!fullName) {
          showToast('نام مشتری تازه را وارد کنید.', 'error');
          return;
        }
        fieldNames.forEach(function (name) {
          const target = form.querySelector('[name="new_customer_' + name + '"]');
          if (target) target.value = getDraftValue(name);
        });
        const customerSelect = form.querySelector('[data-customer-select]');
        if (customerSelect) {
          customerSelect.value = '';
          customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const summary = form.querySelector('[data-new-customer-summary]');
        if (summary) {
          summary.hidden = false;
          const mobile = getDraftValue('mobile');
          summary.innerHTML = '';
          const chip = document.createElement('span');
          chip.className = 'proma-chip';
          chip.textContent = 'مشتری تازه: ' + fullName + (mobile ? ' - ' + mobile : '');
          summary.appendChild(chip);
        }
        modal.classList.remove('open');
        showToast('اطلاعات مشتری تازه به فرم قرارداد اضافه شد.', 'success');
      });
    });
  };

  const initPaymentPreviews = function () {
    document.querySelectorAll('[data-payment-preview]').forEach(function (form) {
      if (form.dataset.paymentPreviewBound === '1') return;
      form.dataset.paymentPreviewBound = '1';
      const endpoint = form.getAttribute('data-preview-url');
      const amount = form.querySelector('[name="amount"]');
      const date = form.querySelector('[name="payment_date"]');
      const installment = form.querySelector('[name="installment_id"]');
      const message = form.querySelector('[data-payment-message]');
      if (!endpoint || !amount || !installment) return;
      const setText = function (selector, text) {
        const target = form.querySelector(selector);
        if (target) target.textContent = text;
      };
      let timer = null;
      const update = function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          const params = new URLSearchParams({
            installment_id: installment.value,
            payment_amount: amount.value,
            payment_date: date ? date.value : ''
          });
          fetch(endpoint + '&' + params.toString()).then(function (response) {
            return response.json();
          }).then(function (json) {
            if (!json.ok || !json.preview || !json.preview.formatted) return;
            setText('[data-payment-remaining-before]', json.preview.formatted.remaining_before_payment);
            setHtml('[data-payment-penalty]', json.preview.formatted.calculated_penalty_html, json.preview.formatted.calculated_penalty);
            setText('[data-payment-reward]', json.preview.formatted.calculated_reward);
            setText('[data-payment-payable]', json.preview.formatted.payable_on_payment_date);
            setText('[data-payment-remaining-after]', json.preview.formatted.remaining_after_payment);
            if (message) message.textContent = json.preview.message || '';
          }).catch(function () {});
        }, 220);
      };
      amount.addEventListener('input', update);
      amount.addEventListener('change', update);
      if (date) {
        date.addEventListener('input', update);
        date.addEventListener('change', update);
      }
      update();
    });
  };

  const decodeBase64Utf8 = function (value) {
    if (!value) return '';
    try {
      const binary = window.atob(value);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
      }
      if (window.TextDecoder) {
        return new TextDecoder('utf-8').decode(bytes);
      }
      let fallback = '';
      for (let j = 0; j < bytes.length; j++) {
        fallback += '%' + ('00' + bytes[j].toString(16)).slice(-2);
      }
      return decodeURIComponent(fallback);
    } catch (error) {
      return '';
    }
  };

  const hashString = function (value) {
    let hash = 2166136261;
    for (let i = 0; i < value.length; i++) {
      hash ^= value.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  };

  const createPseudoRandom = function (seed) {
    let state = seed || 1;
    return function () {
      state ^= state << 13;
      state ^= state >>> 17;
      state ^= state << 5;
      return (state >>> 0) / 4294967296;
    };
  };

  const buildPaymentQrMatrix = function (payload) {
    const size = 29;
    const seed = hashString(payload || 'proma-payment');
    const random = createPseudoRandom(seed || 1);
    const matrix = [];
    for (let y = 0; y < size; y++) {
      matrix[y] = [];
      for (let x = 0; x < size; x++) {
        const inFinder = (x < 7 && y < 7) || (x >= size - 7 && y < 7) || (x < 7 && y >= size - 7);
        let dark = false;
        if (inFinder) {
          dark = x === 0 || x === 6 || y === 0 || y === 6 || (x >= 2 && x <= 4 && y >= 2 && y <= 4);
        } else if (x === 6 || y === 6) {
          dark = ((x + y) % 2) === 0;
        } else {
          const charCode = payload ? payload.charCodeAt((x * 7 + y * 13) % payload.length) : 0;
          dark = ((((seed >>> ((x + y) % 24)) & 1) ^ (((charCode + x + y) % 3) === 0 ? 1 : 0) ^ (random() > 0.58 ? 1 : 0)) === 1);
        }
        matrix[y][x] = dark;
      }
    }
    return matrix;
  };

  const renderPaymentQrGrid = function (grid, payload) {
    if (!grid) return;
    const marker = String(hashString(payload || 'proma-payment'));
    if (grid.dataset.qrRendered === marker) return;
    const matrix = buildPaymentQrMatrix(payload || '');
    grid.innerHTML = '';
    matrix.forEach(function (row) {
      row.forEach(function (dark) {
        const cell = document.createElement('span');
        cell.className = dark ? 'is-dark' : 'is-light';
        grid.appendChild(cell);
      });
    });
    grid.dataset.qrRendered = marker;
  };

  const buildPaymentQrDownloadHtml = function (payload) {
    const matrix = buildPaymentQrMatrix(payload || '');
    const rows = matrix.map(function (row) {
      return '<div class="qr-row">' + row.map(function (dark) {
        return '<span class="' + (dark ? 'is-dark' : 'is-light') + '"></span>';
      }).join('') + '</div>';
    }).join('');
    const safePayload = String(payload || '').replace(/[&<>]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[char] || char);
    });
    return '<!doctype html>' +
      '<html lang="fa" dir="rtl">' +
      '<head>' +
      '<meta charset="utf-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>QR پرداخت</title>' +
      '<style>' +
      'body{margin:0;font-family:tahoma,Arial,sans-serif;background:#f4f7fb;color:#0f172a;display:grid;place-items:center;min-height:100vh;padding:24px;box-sizing:border-box}' +
      '.sheet{width:min(100%,520px);background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:24px;box-shadow:0 20px 50px rgba(15,23,42,.08);padding:24px;display:grid;gap:18px}' +
      '.sheet h1{margin:0;font-size:20px}' +
      '.sheet p{margin:0;line-height:1.8;color:#475569;word-break:break-word}' +
      '.qr{display:grid;grid-template-columns:repeat(29,minmax(0,1fr));gap:2px;padding:12px;border-radius:20px;background:#fff;box-shadow:inset 0 0 0 1px rgba(15,23,42,.06)}' +
      '.qr-row{display:contents}' +
      '.qr span{aspect-ratio:1;border-radius:2px;background:rgba(15,23,42,.08)}' +
      '.qr span.is-dark{background:rgba(15,23,42,.92)}' +
      '.meta{padding:14px 16px;border-radius:16px;background:#f8fafc;border:1px solid rgba(15,23,42,.06);font-size:14px;line-height:1.8;word-break:break-word}' +
      '</style>' +
      '</head>' +
      '<body><div class="sheet"><h1>QR پرداخت</h1><div class="qr">' + rows + '</div><p class="meta">' + safePayload + '</p></div></body></html>';
  };

  const hydratePaymentCard = function (card) {
    if (!card || card.dataset.paymentCardReady === '1') return;
    const payload = decodeBase64Utf8(card.getAttribute('data-payment-qr-payload-b64') || '');
    const grid = card.querySelector('[data-payment-qr-grid]');
    if (grid && payload) {
      renderPaymentQrGrid(grid, payload);
    }
    const download = card.querySelector('[data-payment-qr-download]');
    if (download) {
      download.addEventListener('click', function () {
        if (!payload) return;
        const name = card.getAttribute('data-payment-qr-download-name') || 'payment-qr.html';
        const blob = new Blob([buildPaymentQrDownloadHtml(payload)], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
          URL.revokeObjectURL(url);
        }, 0);
        showToast('QR پرداخت دانلود شد.', 'success');
      });
    }
    card.dataset.paymentCardReady = '1';
  };

  const initPaymentMethodShells = function () {
    document.querySelectorAll('[data-payment-method-shell]').forEach(function (shell) {
      if (shell.dataset.paymentMethodBound === '1') return;
      shell.dataset.paymentMethodBound = '1';
      const toggles = Array.from(shell.querySelectorAll('[data-payment-method-toggle]'));
      const panels = Array.from(shell.querySelectorAll('[data-payment-method-panel]'));
      if (!panels.length) return;

      const activate = function (key) {
        const target = shell.querySelector('[data-payment-method-panel="' + key + '"]');
        if (!target) return;
        toggles.forEach(function (button) {
          const active = button.getAttribute('data-payment-method-toggle') === key;
          button.classList.toggle('active', active);
          button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
          const active = panel === target;
          panel.classList.toggle('active', active);
          panel.hidden = !active;
          if (active) {
            panel.querySelectorAll('[data-payment-card]').forEach(hydratePaymentCard);
          }
        });
      };

      toggles.forEach(function (button) {
        button.addEventListener('click', function () {
          activate(button.getAttribute('data-payment-method-toggle'));
        });
      });

      const activeButton = toggles.find(function (button) {
        return button.classList.contains('active');
      }) || toggles[0];
      if (activeButton) {
        activate(activeButton.getAttribute('data-payment-method-toggle'));
      } else {
        panels.forEach(function (panel) {
          panel.hidden = false;
          panel.classList.add('active');
        });
      }
    });
  };

  const initJalaliDateInputs = function () {
    const modal = document.querySelector('[data-jalali-modal]');
    if (!modal) return;
    const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const weekDays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    const grid = modal.querySelector('[data-jalali-grid]');
    const title = modal.querySelector('[data-jalali-title]');
    const yearInput = modal.querySelector('[data-jalali-year]');
    const monthSelect = modal.querySelector('[data-jalali-month]');
    let activeInput = null;
    let view = todayJalali();

    if (monthSelect && !monthSelect.options.length) {
      monthNames.forEach(function (name, index) {
        const option = document.createElement('option');
        option.value = String(index + 1);
        option.textContent = name;
        monthSelect.appendChild(option);
      });
    }

    const render = function () {
      if (!grid || !yearInput || !monthSelect) return;
      const year = Number(view[0]);
      const month = Number(view[1]);
      const days = jalaliMonthLength(year, month);
      const firstGregorian = jalaliToGregorian(year, month, 1);
      const jsDay = new Date(firstGregorian[0], firstGregorian[1] - 1, firstGregorian[2]).getDay();
      const offset = (jsDay + 1) % 7;
      yearInput.value = toPersianDigits(year);
      monthSelect.value = String(month);
      if (title) title.textContent = monthNames[month - 1] + ' ' + toPersianDigits(year);
      grid.innerHTML = '';
      weekDays.forEach(function (dayName) {
        const head = document.createElement('span');
        head.className = 'jalali-weekday';
        head.textContent = dayName;
        grid.appendChild(head);
      });
      for (let i = 0; i < offset; i++) {
        const blank = document.createElement('span');
        blank.className = 'jalali-day muted';
        grid.appendChild(blank);
      }
      for (let day = 1; day <= days; day++) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'jalali-day';
        button.textContent = toPersianDigits(day);
        button.addEventListener('click', function () {
          if (activeInput) {
            activeInput.value = formatJalaliDate(year, month, day);
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          modal.classList.remove('open');
        });
        grid.appendChild(button);
      }
    };

    const openForInput = function (input) {
      activeInput = input;
      view = parseJalaliParts(input.value);
      render();
      modal.classList.add('open');
    };

    const bindInput = function (input) {
      if (!input || input.dataset.jalaliBound === '1' || input.type === 'hidden') return;
      input.dataset.jalaliBound = '1';
      input.setAttribute('autocomplete', 'off');
      input.classList.add('proma-date-input');
      const wrapper = document.createElement('span');
      wrapper.className = 'proma-date-input-wrap';
      input.insertAdjacentElement('beforebegin', wrapper);
      wrapper.appendChild(input);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'proma-date-trigger icon-only';
      button.setAttribute('title', 'انتخاب تاریخ');
      button.setAttribute('aria-label', 'انتخاب تاریخ');
      button.innerHTML = '<i data-feather="calendar"></i>';
      button.addEventListener('click', function () {
        openForInput(input);
      });
      wrapper.appendChild(button);
      if (window.feather && window.feather.replace) window.feather.replace();
    };

    document.querySelectorAll([
      'input[name="start_date"]',
      'input[name="first_due_date"]',
      'input[name="due_date"]',
      'input[name="payment_date"]',
      'input[name="event_date"]',
      'input[name="custom_reminder_date"]',
      'input[name="next_followup_date"]',
      'input[name="promise_payment_date"]',
      'input[name="action_date"]',
      'input[name="notice_date"]',
      'input[name="court_date"]',
      'input[name="hearing_date"]',
      'input[name="date_from"]',
      'input[name="date_to"]',
      'input[data-jalali-input]'
    ].join(',')).forEach(bindInput);

    modal.querySelectorAll('[data-jalali-prev]').forEach(function (button) {
      button.addEventListener('click', function () {
        view[1] -= 1;
        if (view[1] < 1) {
          view[1] = 12;
          view[0] -= 1;
        }
        render();
      });
    });
    modal.querySelectorAll('[data-jalali-next]').forEach(function (button) {
      button.addEventListener('click', function () {
        view[1] += 1;
        if (view[1] > 12) {
          view[1] = 1;
          view[0] += 1;
        }
        render();
      });
    });
    modal.querySelectorAll('[data-jalali-today]').forEach(function (button) {
      button.addEventListener('click', function () {
        view = todayJalali();
        render();
      });
    });
    if (yearInput) {
      yearInput.addEventListener('change', function () {
        const year = Number(toEnglishDigits(yearInput.value).replace(/[^\d]/g, '')) || view[0];
        view[0] = year;
        render();
      });
    }
    if (monthSelect) {
      monthSelect.addEventListener('change', function () {
        view[1] = Number(monthSelect.value) || view[1];
        render();
      });
    }
  };

  const initCalendarReminderFields = function () {
    document.querySelectorAll('[data-calendar-reminder-type]').forEach(function (select) {
      const form = select.closest('form');
      const custom = form ? form.querySelector('[data-calendar-custom-reminder]') : null;
      const toggle = function () {
        if (custom) custom.hidden = select.value !== 'custom';
      };
      select.addEventListener('change', toggle);
      toggle();
    });
  };

  const initSidebarCollapse = function () {
    const wrapper = document.getElementById('pageWrapper');
    if (!wrapper) return;
    const sidebar = document.querySelector('.sidebar-wrapper');
    const header = document.querySelector('.page-header');
    const syncState = function () {
      if (!sidebar || !header || window.innerWidth < 992) return;
      const collapsed = sidebar.classList.contains('close_icon');
      localStorage.setItem('proma-sidebar-icons', collapsed ? '1' : '0');
      document.querySelectorAll('.toggle-sidebar').forEach(function (button) {
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    };
    if (sidebar && header && window.innerWidth >= 992 && localStorage.getItem('proma-sidebar-icons') === '1') {
      sidebar.classList.add('close_icon');
      header.classList.add('close_icon');
    }
    document.querySelectorAll('.toggle-sidebar').forEach(function (button) {
      button.setAttribute('title', 'باز و بسته کردن منو');
      button.setAttribute('role', 'button');
      button.addEventListener('click', function () {
        if (window.innerWidth < 992) return;
        window.setTimeout(syncState, 20);
      });
    });
    syncState();
  };

  const initLoadingForms = function () {
    document.querySelectorAll('[data-loading-form]').forEach(function (form) {
      form.addEventListener('submit', function () {
        const button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.dataset.originalText = button.textContent;
        button.textContent = button.getAttribute('data-loading-text') || 'در حال پردازش...';
        button.disabled = true;
      });
    });
  };

  const initRequiredLabels = function () {
    document.querySelectorAll('label').forEach(function (label) {
      const required = label.querySelector('input[required], select[required], textarea[required]');
      if (required) label.classList.add('required-field');
    });
  };

  const initCheckAll = function () {
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
      if (master.dataset.checkAllBound === '1') return;
      master.dataset.checkAllBound = '1';
      const name = master.getAttribute('data-check-all');
      master.addEventListener('change', function () {
        document.querySelectorAll('[data-check-item="' + name + '"]').forEach(function (item) {
          item.checked = master.checked;
        });
      });
    });
  };

  const initAiConnectionTest = function () {
    document.querySelectorAll('[data-ai-test-url]').forEach(function (button) {
      const form = button.closest('form');
      const result = document.querySelector('[data-ai-test-result]');
      button.addEventListener('click', function () {
        if (!form || !result) return;
        const token = form.querySelector('[name="_csrf"]');
        const apiKey = form.querySelector('[name="openrouter_api_key"]');
        const model = form.querySelector('[name="openrouter_model"]');
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'در حال تست...';
        result.className = 'ai-test-result';
        result.textContent = 'در حال ارسال درخواست تست به OpenRouter...';
        fetch(button.getAttribute('data-ai-test-url'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            _csrf: token ? token.value : '',
            openrouter_api_key: apiKey ? apiKey.value : '',
            openrouter_model: model ? model.value : ''
          })
        }).then(function (response) {
          return response.json();
        }).then(function (json) {
          result.classList.add(json.ok ? 'success' : 'error');
          result.textContent = json.message + (json.details ? ' - ' + json.details : '');
          showToast(json.message, json.ok ? 'success' : 'error');
        }).catch(function () {
          result.classList.add('error');
          result.textContent = 'خطا در ارتباط با OpenRouter';
          showToast('خطا در ارتباط با OpenRouter', 'error');
        }).finally(function () {
          button.disabled = false;
          button.textContent = original;
        });
      });
    });
  };

  const initCharts = function () {
    document.querySelectorAll('[data-chart]').forEach(function (canvas) {
      if (!window.Chart) return;
      const requestedType = canvas.getAttribute('data-chart') || 'line';
      const type = requestedType === 'mini-line' ? 'line' : requestedType;
      const isMini = requestedType === 'mini-line';
      const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
      const data = JSON.parse(canvas.getAttribute('data-values') || '[]');
      const colors = JSON.parse(canvas.getAttribute('data-colors') || '[]');
      const title = canvas.getAttribute('data-title') || 'گزارش';
      const palette = colors.length ? colors : ['#7366ff', '#16c7f9', '#54ba4a', '#ffaa05', '#fc4438', '#8b8d98'];
      const isCircle = type === 'doughnut' || type === 'pie';
      new Chart(canvas, {
        type: type,
        data: {
          labels: labels,
          datasets: [{
            label: title,
            data: data,
            borderColor: isCircle ? '#fff' : '#7366ff',
            backgroundColor: isCircle || type === 'bar' ? palette : 'rgba(115, 102, 255, .12)',
            pointBackgroundColor: '#7366ff',
            pointBorderColor: '#fff',
            borderWidth: isMini ? 2 : (isCircle ? 2 : 3),
            borderRadius: type === 'bar' ? 8 : 0,
            fill: type === 'line',
            tension: .35,
            hoverOffset: isCircle ? 8 : 0,
            pointRadius: isMini ? 0 : 3,
            pointHoverRadius: isMini ? 3 : 5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: isMini ? false : isCircle,
              position: 'bottom',
              labels: { usePointStyle: true, boxWidth: 8, font: { family: 'YekanBakh' } }
            },
            tooltip: {
              rtl: true,
              bodyFont: { family: 'YekanBakh' },
              titleFont: { family: 'YekanBakh' },
              callbacks: {
                label: function (context) {
                  const value = context.parsed && typeof context.parsed === 'object' ? context.parsed.y : context.parsed;
                  return ' ' + context.dataset.label + ': ' + Number(value || 0).toLocaleString('fa-IR');
                }
              }
            }
          },
          cutout: type === 'doughnut' ? '68%' : undefined,
          scales: isCircle ? {} : (isMini ? {
            x: { display: false },
            y: { display: false, beginAtZero: true }
          } : {
            x: { grid: { display: false }, ticks: { font: { family: 'YekanBakh' } } },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(82, 82, 108, .08)' },
              ticks: {
                font: { family: 'YekanBakh' },
                callback: function (value) { return Number(value).toLocaleString('fa-IR'); }
              }
            }
          })
        }
      });
    });
  };

  const initChat = function () {
    const chatForm = document.querySelector('[data-chat-form]');
    if (!chatForm) return;

    const history = document.querySelector('[data-chat-history]');
    const receiver = chatForm.querySelector('[name="receiver_id"]');
    const channel = chatForm.querySelector('[name="channel_id"]');
    const input = chatForm.querySelector('[name="body"]');
    const token = chatForm.querySelector('[name="_csrf"]');
    const endpoint = chatForm.getAttribute('action');
    const fetchUrl = chatForm.getAttribute('data-fetch-url');
    const attachmentTemplate = chatForm.getAttribute('data-attachment-url-template') || '';
    const attachmentInput = chatForm.querySelector('[name="attachment"]');

    const createVerifiedBadge = function (title) {
      const badge = document.createElement('span');
      const icon = document.createElement('i');
      badge.className = 'proma-verified-badge';
      badge.title = title || 'حساب رسمی';
      icon.setAttribute('data-feather', 'check');
      badge.appendChild(icon);
      return badge;
    };

    const formatMessageTime = function (value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      const parts = raw.split(/\s+/);
      const dateParts = (parts[0] || '').split(/[-/]/).map(function (part) { return Number(part); });
      if (dateParts.length === 3 && dateParts[0] > 1600) {
        const jalali = gregorianToJalali(dateParts[0], dateParts[1], dateParts[2]);
        const timePart = parts[1] ? toPersianDigits(parts[1].slice(0, 5)) : '';
        return formatJalaliDate(jalali[0], jalali[1], jalali[2]) + (timePart ? ' ' + timePart : '');
      }
      return toPersianDigits(raw);
    };

    const addMessage = function (message) {
      const item = document.createElement('div');
      const isSystem = Number(message.is_system || 0) === 1;
      item.className = 'message' + (String(message.sender_id) === chatForm.getAttribute('data-user-id') && !isSystem ? ' mine' : '') + (isSystem ? ' system' : '');
      item.setAttribute('data-id', message.id);
      const body = document.createElement('div');
      const meta = document.createElement('small');
      const name = document.createElement('span');
      const unit = document.createElement('span');
      const time = document.createElement('span');
      body.textContent = message.body || '';
      meta.className = 'message-meta';
      name.className = 'message-meta-name';
      unit.className = 'message-meta-unit';
      time.className = 'message-meta-time';
      name.textContent = message.sender_name || '';
      unit.textContent = message.target_unit || '';
      time.textContent = formatMessageTime(message.created_at);
      meta.appendChild(name);
      if (isSystem) meta.appendChild(createVerifiedBadge('حساب رسمی'));
      if (message.target_unit && !isSystem && message.target_unit !== message.sender_name) meta.appendChild(unit);
      meta.appendChild(time);
      item.appendChild(body);
      item.appendChild(meta);
      if (message.attachment_id) {
        if (message.attachment_path) {
          const link = document.createElement('a');
          const image = document.createElement('img');
          link.className = 'chat-attachment';
          link.href = attachmentTemplate.replace('__ID__', message.attachment_id);
          link.target = '_blank';
          image.src = link.href;
          image.alt = 'پیوست تصویر';
          link.appendChild(image);
          item.insertBefore(link, item.querySelector('small'));
        } else {
          const badge = document.createElement('span');
          badge.className = 'badge muted';
          badge.textContent = 'فایل بررسی و حذف شد';
          item.insertBefore(badge, item.querySelector('small'));
        }
      }
      history.appendChild(item);
      history.querySelectorAll('.empty').forEach(function (empty) { empty.remove(); });
      history.scrollTop = history.scrollHeight;
      if (window.feather && window.feather.replace) window.feather.replace();
    };

    const poll = function () {
      if ((!receiver || !receiver.value) && (!channel || !channel.value)) return;
      const last = history.querySelector('.message:last-child');
      const after = last ? last.getAttribute('data-id') : 0;
      fetch(fetchUrl + '&after=' + encodeURIComponent(after)).then(function (response) {
        return response.json();
      }).then(function (json) {
        if (json.ok) json.messages.forEach(addMessage);
      }).catch(function () {});
    };

    chatForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const body = input.value.trim();
      const hasAttachment = attachmentInput && attachmentInput.files && attachmentInput.files.length > 0;
      if (!body && !hasAttachment) return;
      const formData = new FormData(chatForm);
      fetch(endpoint, {
        method: 'POST',
        body: formData
      }).then(function (response) {
        return response.json();
      }).then(function (json) {
        if (json.ok) {
          input.value = '';
          if (attachmentInput) attachmentInput.value = '';
          poll();
        } else if (json.message) {
          showToast(json.message, 'error');
        }
      }).catch(function () {});
    });

    setInterval(poll, 5000);
    history.scrollTop = history.scrollHeight;
  };

  const initNotificationCenter = function () {
    const center = document.querySelector('[data-notification-center]');
    if (!center || !window.fetch) return;
    const feedUrl = center.getAttribute('data-feed-url');
    const readUrl = center.getAttribute('data-read-url');
    const list = center.querySelector('[data-notification-list]');
    const badge = center.querySelector('[data-notification-badge]');
    const form = center.querySelector('form[action]');
    const userId = document.body.getAttribute('data-user-id') || 'guest';
    const storageKey = 'proma-notification-seen-' + userId;
    const soundEnabled = document.body.getAttribute('data-notification-sound') === '1';
    const volume = Math.max(0, Math.min(1, Number(document.body.getAttribute('data-notification-volume') || 0.45)));
    let latestId = Number(center.getAttribute('data-latest-id') || 0);
    if (!localStorage.getItem(storageKey)) {
      localStorage.setItem(storageKey, String(latestId));
    }

    const playNotificationSound = function () {
      if (!soundEnabled || !window.AudioContext && !window.webkitAudioContext) return;
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      try {
        const ctx = new AudioCtx();
        const gain = ctx.createGain();
        const oscillator = ctx.createOscillator();
        oscillator.type = 'sine';
        oscillator.frequency.value = 740;
        gain.gain.value = volume * 0.08;
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start();
        oscillator.frequency.exponentialRampToValueAtTime(520, ctx.currentTime + 0.16);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
        window.setTimeout(function () {
          oscillator.stop();
          ctx.close();
        }, 220);
      } catch (error) {}
    };

    const itemHtml = function (item) {
      const li = document.createElement('li');
      const tones = {
        payment: 'success',
        payment_receipt: 'warning',
        legal: 'danger',
        chat: 'info',
        calendar: 'primary',
        identity: 'warning'
      };
      li.className = 'proma-dropdown-notification-item proma-dropdown-notification-item--' + (tones[item.type] || 'primary');
      li.setAttribute('data-notification-item', item.id || '');
      const link = document.createElement('a');
      link.href = item.url || './index.php?route=dashboard';
      const time = document.createElement('span');
      time.className = 'proma-notification-time';
      time.textContent = item.relative_time || '';
      const strong = document.createElement('strong');
      strong.textContent = item.title || '';
      const small = document.createElement('small');
      small.textContent = item.body || '';
      link.appendChild(time);
      link.appendChild(strong);
      link.appendChild(small);
      li.appendChild(link);
      return li;
    };

    const render = function (feed) {
      if (!feed) return;
      const unread = Number(feed.unread_count || 0);
      if (badge) {
        badge.hidden = unread <= 0;
        badge.textContent = unread.toLocaleString('fa-IR');
      }
      if (list) {
        list.querySelectorAll('[data-notification-item], [data-notification-empty]').forEach(function (node) {
          node.remove();
        });
        const marker = list.querySelector('[data-notification-actions]') || list.querySelector('li:last-child');
        if (feed.items && feed.items.length) {
          feed.items.forEach(function (item) {
            list.insertBefore(itemHtml(item), marker || null);
          });
        } else {
          const empty = document.createElement('li');
          empty.className = 'proma-notification-empty';
          empty.setAttribute('data-notification-empty', '1');
          const p = document.createElement('p');
          p.textContent = 'اعلان تازه‌ای ندارید.';
          empty.appendChild(p);
          list.insertBefore(empty, marker || null);
        }
      }
      const incomingLatest = Number(feed.latest_id || 0);
      const seen = Number(localStorage.getItem(storageKey) || 0);
      if (incomingLatest > latestId && incomingLatest > seen) {
        playNotificationSound();
        localStorage.setItem(storageKey, String(incomingLatest));
      }
      latestId = Math.max(latestId, incomingLatest);
      center.setAttribute('data-latest-id', String(latestId));
    };

    const fetchFeed = function () {
      if (!feedUrl) return;
      fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (json) { if (json.ok) render(json.feed); })
        .catch(function () {});
    };

    center.addEventListener('mouseenter', function () {
      localStorage.setItem(storageKey, String(latestId));
    });
    center.addEventListener('click', function () {
      localStorage.setItem(storageKey, String(latestId));
    });
    if (form && readUrl) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const token = form.querySelector('[name="_csrf"]');
        fetch(readUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({ _csrf: token ? token.value : '' })
        }).then(function (response) {
          return response.json();
        }).then(function (json) {
          if (json.ok) render(json.feed);
        }).catch(function () {
          form.submit();
        });
      });
    }
    setInterval(fetchFeed, 15000);
  };

  const initCopyShortcodes = function () {
    document.querySelectorAll('[data-copy-shortcode]').forEach(function (button) {
      button.addEventListener('click', function () {
        const value = button.getAttribute('data-copy-shortcode') || button.textContent.trim();
        const message = button.getAttribute('data-copy-message') || 'کپی شد.';
        const done = function () {
          showToast(message, 'success');
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(value).then(done).catch(function () {});
        } else {
          const temp = document.createElement('textarea');
          temp.value = value;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          temp.remove();
          done();
        }
      });
    });
  };

  const setQuillHtml = function (quill, html) {
    if (!quill) return;
    const value = String(html || '');
    if (quill.clipboard && typeof quill.clipboard.dangerouslyPasteHTML === 'function') {
      quill.clipboard.dangerouslyPasteHTML(value);
      return;
    }
    if (typeof quill.pasteHTML === 'function') {
      quill.pasteHTML(value);
      return;
    }
    if (quill.clipboard && typeof quill.clipboard.convert === 'function' && typeof quill.setContents === 'function') {
      try {
        quill.setContents(quill.clipboard.convert({ html: value, text: '' }), 'silent');
        return;
      } catch (error) {
        quill.setContents(quill.clipboard.convert(value), 'silent');
        return;
      }
    }
    quill.root.innerHTML = value;
    if (typeof quill.update === 'function') quill.update('silent');
  };
  window.PromaQuillHtml = setQuillHtml;

  const initPromaRichEditors = function () {
    if (!window.Quill) return;
    document.querySelectorAll('textarea[data-rich-editor]').forEach(function (textarea) {
      if (textarea.dataset.richEditorReady === '1') return;
      textarea.dataset.richEditorReady = '1';

      const height = Number(textarea.getAttribute('data-rich-editor-height') || 360);
      const shell = document.createElement('div');
      shell.className = 'proma-rich-editor-shell';
      shell.setAttribute('dir', 'rtl');

      const editor = document.createElement('div');
      editor.className = 'proma-rich-editor';
      editor.style.minHeight = Math.max(220, height) + 'px';
      shell.appendChild(editor);
      textarea.parentNode.insertBefore(shell, textarea);
      textarea.classList.add('proma-rich-source');

      const quill = new window.Quill(editor, {
        theme: 'snow',
        placeholder: textarea.getAttribute('placeholder') || 'متن را وارد کنید...',
        modules: {
          toolbar: [
            [{ header: [1, 2, 3, 4, false] }, { size: ['small', false, 'large'] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ script: 'sub' }, { script: 'super' }],
            [{ color: [] }, { background: [] }, { align: [] }, { direction: 'rtl' }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ indent: '-1' }, { indent: '+1' }],
            ['blockquote'],
            ['link', 'clean']
          ]
        }
      });
      textarea._promaQuill = quill;

      const initial = decodeHtmlEntities(textarea.value || '');
      if (initial.trim() !== '') {
        if (/<[a-z][\s\S]*>/i.test(initial)) {
          setQuillHtml(quill, initial);
        } else {
          setQuillHtml(quill, initial.replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
          }).replace(/\r?\n/g, '<br>'));
        }
      }
      quill.root.setAttribute('dir', 'rtl');
      quill.root.classList.add('proma-quill-rtl');
      try {
        quill.format('direction', 'rtl');
        quill.format('align', 'right');
      } catch (error) {}

      const sync = function () {
        if (!textarea.classList.contains('proma-rich-source')) return;
        const html = quill.root.innerHTML.trim();
        textarea.value = html === '<p><br></p>' ? '' : html;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      };
      quill.on('text-change', sync);
      const form = textarea.closest('form');
      if (form) {
        form.addEventListener('submit', sync);
      }
    });
  };

  const initSettingResets = function () {
    document.querySelectorAll('[data-reset-setting]').forEach(function (button) {
      if (button.dataset.resetBound === '1') return;
      button.dataset.resetBound = '1';
      button.addEventListener('click', function () {
        const name = button.getAttribute('data-reset-setting') || '';
        const input = name ? document.querySelector('[name="' + name + '"]') : null;
        if (!input) return;
        input.value = button.getAttribute('data-reset-value') || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
      });
    });
  };

  const initCardLinks = function () {
    if (document.documentElement.dataset.cardNavigationBound === '1') return;
    document.documentElement.dataset.cardNavigationBound = '1';
    const interactiveSelector = 'a,button,input,select,textarea,label,[role="button"],[data-stop-card-navigation],[data-open-modal],[data-close-modal],.modal';
    const elementFromEvent = function (event) {
      if (event.target && event.target.nodeType === 1) return event.target;
      return event.target && event.target.parentElement ? event.target.parentElement : null;
    };
    const isInternalInteractive = function (target, card) {
      const control = target.closest(interactiveSelector);
      if (control && card.contains(control)) return true;
      const form = target.closest('form');
      return !!(form && card.contains(form));
    };
    document.addEventListener('click', function (event) {
      if (event.defaultPrevented) return;
      const target = elementFromEvent(event);
      if (!target || typeof target.closest !== 'function') return;
      const card = target.closest('[data-card-href]');
      if (!card || isInternalInteractive(target, card)) return;
      const href = card.getAttribute('data-card-href');
      if (href) window.location.assign(href);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      const target = elementFromEvent(event);
      if (!target || target !== target.closest('[data-card-href]')) return;
      if (isInternalInteractive(target, target)) return;
      event.preventDefault();
      const href = target.getAttribute('data-card-href');
      if (href) window.location.assign(href);
    });
  };

  document.addEventListener('error', function (event) {
    const image = event.target;
    if (!image || !image.matches || !image.matches('[data-avatar-image]')) return;
    image.parentElement.classList.add('avatar-image-fallback');
  }, true);

  const initTour = function () {
    const tour = document.querySelector('[data-tour]');
    if (!tour || !window.localStorage) return;
    const userId = document.body.getAttribute('data-user-id') || 'guest';
    const key = 'proma-tour-seen-' + userId;
    if (localStorage.getItem(key) === '1') return;

    const steps = [
      { title: 'داشبورد', body: 'نمای سریع وضعیت قراردادها، سررسیدها و پرداخت‌ها را اینجا می‌بینید.' },
      { title: 'منوی سامانه', body: 'از منوی کناری بین قراردادها، اقساط، چت و گزارش‌ها جابه‌جا شوید.' },
      { title: 'جستجو و فیلتر', body: 'در صفحه‌های اصلی از فیلترها برای پیدا کردن مشتری، قرارداد یا پرداخت استفاده کنید.' },
      { title: 'گفت‌وگو', body: 'برای ارتباط با واحدهای داخلی یا کاربران مجاز از بخش گفت‌وگو استفاده کنید.' }
    ];
    let index = 0;
    const step = tour.querySelector('[data-tour-step]');
    const title = tour.querySelector('[data-tour-title]');
    const body = tour.querySelector('[data-tour-body]');
    const next = tour.querySelector('[data-tour-next]');
    const skip = tour.querySelector('[data-tour-skip]');

    const render = function () {
      step.textContent = (index + 1).toLocaleString('fa-IR') + ' از ' + steps.length.toLocaleString('fa-IR');
      title.textContent = steps[index].title;
      body.textContent = steps[index].body;
      next.textContent = index === steps.length - 1 ? 'پایان' : 'بعدی';
    };
    const close = function () {
      localStorage.setItem(key, '1');
      tour.hidden = true;
    };

    next.addEventListener('click', function () {
      if (index >= steps.length - 1) {
        close();
        return;
      }
      index += 1;
      render();
    });
    skip.addEventListener('click', close);
    render();
    tour.hidden = false;
  };

  const initServiceWorker = function () {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
      navigator.serviceWorker.getRegistrations().then(function (registrations) {
        registrations.forEach(function (registration) {
          registration.unregister();
        });
      }).catch(function () {});
      if ('caches' in window) {
        caches.keys().then(function (keys) {
          keys.filter(function (key) {
            return key.indexOf('proma-pay-') === 0;
          }).forEach(function (key) {
            caches.delete(key);
          });
        }).catch(function () {});
      }
    });
  };

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }
      form.dataset.submitting = '1';
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (control) {
        var label = control.getAttribute('data-submit-label');
        if (label && control.tagName === 'BUTTON') control.textContent = label;
        control.disabled = true;
        control.setAttribute('aria-busy', 'true');
      });
    });
  });
    initAuthTabs();
    initMoneyInputs();
    initModals();
    initFilters();
    initAjaxFilters();
    initSettingsTabs();
    initCustomerLiveSearch();
    initGuarantorLiveSearch();
    initUserLiveSearch();
    initContractPickers();
    initRepeaters();
    initContractForms();
    initContractNewCustomerPopup();
    initPaymentMethodShells();
    initPaymentPreviews();
    initJalaliDateInputs();
    initCalendarReminderFields();
    initLoadingForms();
    initRequiredLabels();
    initCheckAll();
    initAiConnectionTest();
    initSidebarCollapse();
    initNotificationCenter();
    initCharts();
    initChat();
    initCardLinks();
    initContactActions();
    initCopyShortcodes();
    initSettingResets();
    initPromaRichEditors();
    initTour();
    initServiceWorker();
  });
})();
