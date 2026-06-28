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
    document.querySelectorAll('[data-open-modal]').forEach(function (button) {
      button.addEventListener('click', function () {
        const modal = document.getElementById(button.getAttribute('data-open-modal'));
        if (modal) modal.classList.add('open');
      });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
      button.addEventListener('click', function () {
        const modal = button.closest('.modal');
        if (modal) modal.classList.remove('open');
      });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) modal.classList.remove('open');
      });
    });
  };

  const initFilters = function () {
    document.querySelectorAll('[data-select-filter]').forEach(function (input) {
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

  const initContractForms = function () {
    document.querySelectorAll('[data-contract-form]').forEach(function (form) {
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
      const toggleNewCustomer = form.querySelector('[data-toggle-new-customer]');
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

      const showError = function (message) {
        if (!error) return;
        error.hidden = !message;
        error.textContent = message || '';
      };

      const syncCustomerChip = function () {
        if (!customerSelect || !customerChip) return;
        customerChip.innerHTML = '';
        const option = customerSelect.selectedOptions[0];
        if (!option || !option.value) return;
        const chip = document.createElement('span');
        chip.className = 'proma-chip';
        chip.textContent = option.textContent;
        customerChip.appendChild(chip);
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

      if (toggleNewCustomer && newCustomerFields) {
        toggleNewCustomer.addEventListener('click', function () {
          newCustomerFields.hidden = !newCustomerFields.hidden;
          if (!newCustomerFields.hidden && customerSelect) {
            customerSelect.value = '';
            syncCustomerChip();
            syncGuarantorChips();
          }
        });
      }

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
          if (newCustomerFields && customerSelect.value) newCustomerFields.hidden = true;
          syncCustomerChip();
          syncGuarantorChips();
        });
      }
      if (guarantorSelect) {
        guarantorSelect.addEventListener('change', syncGuarantorChips);
      }

      form.addEventListener('submit', function (event) {
        const principalValue = parseMoney(principal ? principal.value : 0);
        const downValue = parseMoney(downPayment ? downPayment.value : 0);
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
        if (!hasCustomer && (!newName || !newName.value.trim())) {
          event.preventDefault();
          showError('مشتری موجود را انتخاب کنید یا اطلاعات مشتری تازه را وارد کنید.');
        }
      });

      syncCustomerChip();
      syncGuarantorChips();
      updatePreview();
    });
  };

  const initPaymentPreviews = function () {
    document.querySelectorAll('[data-payment-preview]').forEach(function (form) {
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
            setText('[data-payment-penalty]', json.preview.formatted.calculated_penalty);
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

  const initSidebarCollapse = function () {
    const wrapper = document.getElementById('pageWrapper');
    if (!wrapper) return;
    if (localStorage.getItem('proma-sidebar-icons') === '1') {
      wrapper.classList.add('proma-sidebar-icons');
    }
    document.querySelectorAll('.toggle-sidebar').forEach(function (button) {
      button.setAttribute('title', 'باز و بسته کردن منو');
      button.addEventListener('click', function () {
        if (window.innerWidth < 992) return;
        window.setTimeout(function () {
          wrapper.classList.toggle('proma-sidebar-icons');
          localStorage.setItem('proma-sidebar-icons', wrapper.classList.contains('proma-sidebar-icons') ? '1' : '0');
        }, 0);
      });
    });
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
              labels: { usePointStyle: true, boxWidth: 8, font: { family: 'Vazirmatn' } }
            },
            tooltip: {
              rtl: true,
              bodyFont: { family: 'Vazirmatn' },
              titleFont: { family: 'Vazirmatn' },
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
            x: { grid: { display: false }, ticks: { font: { family: 'Vazirmatn' } } },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(82, 82, 108, .08)' },
              ticks: {
                font: { family: 'Vazirmatn' },
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
    const input = chatForm.querySelector('[name="body"]');
    const token = chatForm.querySelector('[name="_csrf"]');
    const endpoint = chatForm.getAttribute('action');
    const fetchUrl = chatForm.getAttribute('data-fetch-url');

    const addMessage = function (message) {
      const item = document.createElement('div');
      item.className = 'message' + (String(message.sender_id) === chatForm.getAttribute('data-user-id') ? ' mine' : '');
      item.setAttribute('data-id', message.id);
      item.innerHTML = '<div></div><small></small>';
      item.querySelector('div').textContent = message.body;
      item.querySelector('small').textContent = message.sender_name || '';
      history.appendChild(item);
      history.querySelectorAll('.empty').forEach(function (empty) { empty.remove(); });
      history.scrollTop = history.scrollHeight;
    };

    const poll = function () {
      if (!receiver.value) return;
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
      if (!body) return;
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _csrf: token.value, receiver_id: receiver.value, body: body })
      }).then(function (response) {
        return response.json();
      }).then(function (json) {
        if (json.ok) {
          input.value = '';
          poll();
        }
      }).catch(function () {});
    });

    setInterval(poll, 5000);
    history.scrollTop = history.scrollHeight;
  };

  const initTour = function () {
    if (document.body.getAttribute('data-tour-needed') !== '1') return;
    const tour = document.querySelector('[data-tour]');
    if (!tour) return;
    const box = tour.querySelector('.proma-tour-box');
    const title = tour.querySelector('[data-tour-title]');
    const text = tour.querySelector('[data-tour-text]');
    const next = tour.querySelector('[data-tour-next]');
    const skip = tour.querySelector('[data-tour-skip]');
    const steps = [
      { selector: '.sidebar-wrapper', title: 'منوی اصلی', text: 'از اینجا به داشبورد، قراردادها، اقساط و چت دسترسی دارید.' },
      { selector: '.page-title', title: 'مسیر صفحه', text: 'نام صفحه و مسیر فعلی همیشه بالای صفحه دیده می‌شود.' },
      { selector: '.nav-menus', title: 'اعلان‌ها و پیام‌ها', text: 'اعلان‌ها، پیام‌های تازه و پروفایل شما اینجا قرار دارد.' },
      { selector: '.page-body .container-fluid:last-child', title: 'فضای کاری', text: 'فرم‌ها، جدول‌ها و گزارش‌های هر بخش در این قسمت نمایش داده می‌شود.' }
    ];
    let index = 0;

    const complete = function () {
      tour.hidden = true;
      document.querySelectorAll('.proma-tour-focus').forEach(function (item) { item.classList.remove('proma-tour-focus'); });
      fetch(document.body.getAttribute('data-tour-complete-url'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _csrf: document.body.getAttribute('data-tour-token') || '' })
      }).catch(function () {});
    };

    const show = function () {
      document.querySelectorAll('.proma-tour-focus').forEach(function (item) { item.classList.remove('proma-tour-focus'); });
      const step = steps[index];
      const target = document.querySelector(step.selector);
      if (!target) {
        index += 1;
        if (index >= steps.length) complete();
        else show();
        return;
      }
      target.classList.add('proma-tour-focus');
      title.textContent = step.title;
      text.textContent = step.text;
      next.textContent = index === steps.length - 1 ? 'پایان' : 'بعدی';
      tour.hidden = false;
      const rect = target.getBoundingClientRect();
      const top = Math.min(window.innerHeight - box.offsetHeight - 24, Math.max(24, rect.top + 12));
      box.style.top = top + 'px';
      box.style.left = window.innerWidth < 768 ? '16px' : '32px';
    };

    next.addEventListener('click', function () {
      index += 1;
      if (index >= steps.length) complete();
      else show();
    });
    skip.addEventListener('click', complete);
    window.setTimeout(show, 700);
  };

  const initServiceWorker = function () {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('service-worker.js').catch(function () {});
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    initAuthTabs();
    initMoneyInputs();
    initModals();
    initFilters();
    initContractForms();
    initPaymentPreviews();
    initLoadingForms();
    initAiConnectionTest();
    initSidebarCollapse();
    initCharts();
    initChat();
    initTour();
    initServiceWorker();
  });
})();
