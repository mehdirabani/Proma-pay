(function () {
  const toEnglishDigits = function (value) {
    return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
      return '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(digit) % 10;
    });
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

  const initSidebarCollapse = function () {
    const wrapper = document.getElementById('pageWrapper');
    if (!wrapper) return;
    if (localStorage.getItem('proma-sidebar-icons') === '1') {
      wrapper.classList.add('proma-sidebar-icons');
    }
    document.querySelectorAll('.toggle-sidebar').forEach(function (button) {
      button.addEventListener('click', function () {
        if (window.innerWidth < 992) return;
        window.setTimeout(function () {
          wrapper.classList.toggle('proma-sidebar-icons');
          localStorage.setItem('proma-sidebar-icons', wrapper.classList.contains('proma-sidebar-icons') ? '1' : '0');
        }, 0);
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
    initSidebarCollapse();
    initCharts();
    initChat();
    initServiceWorker();
  });
})();
