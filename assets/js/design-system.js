(function () {
  'use strict';

  const root = document.querySelector('[data-design-system-page]');
  const catalogElement = document.getElementById('ppds-catalog-data');
  if (!root || !catalogElement) return;

  let catalog;
  try { catalog = JSON.parse(catalogElement.textContent || '{}'); }
  catch (error) { return; }

  const allItems = Array.isArray(catalog.all) ? catalog.all : [];
  const itemById = new Map(allItems.map((item) => [item.id, item]));
  const groupById = new Map();
  (catalog.groups || []).forEach((group) => groupById.set(group.id, group));
  (catalog.categories || []).forEach((category) => (category.items || []).forEach((group) => {
    if (group && group.id) groupById.set(group.id, group);
  }));

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
  const escapeHtml = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[character]));
  const safeText = (value, fallback = '') => String(value == null ? fallback : value);
  const defaultState = (item) => ({
    label: item && item.family === 'buttons' ? 'ایجاد قرارداد جدید' : (item ? item.name : 'نمونه'),
    value: item && item.family === 'forms' ? '' : '۱۲٬۵۰۰٬۰۰۰',
    variant: item && item.family === 'buttons' && ['primary', 'secondary', 'outline', 'ghost', 'danger', 'success', 'warning', 'link'].includes(item.variant) ? item.variant : 'primary',
    size: 'medium',
    disabled: Boolean(item && item.variant === 'disabled'),
    loading: item ? item.variant === 'loading' : false,
    fullWidth: false,
    rounded: false,
    icon: item && item.family === 'buttons' ? 'plus' : 'search',
    iconEnabled: Boolean(item && item.variant === 'icon'),
    controlStatus: item && item.family === 'forms' ? 'default' : 'default',
    required: false,
    currency: 'تومان',
    amount: '۱۲٬۵۰۰٬۰۰۰',
    fontSize: 18,
    fontWeight: 600,
    iconSet: 'feather',
    iconSearch: '',
    selectedDate: localDateString(new Date()),
    calendarViewDate: localDateString(new Date()),
    selectedTab: 0,
    page: 1,
    viewMode: 'table',
    filterText: '',
    sampleTone: 'success',
    result: ''
  });

  let current = itemById.get('button-primary') || allItems[0] || null;
  let playState = defaultState(current);
  let currentCode = '';
  let modalTrigger = null;
  let focusableBeforeModal = null;
  let toastTimer = 0;
  let searchActiveIndex = -1;

  const elements = {
    title: $('[data-doc-title]'), overview: $('[data-doc-overview]'), usage: $('[data-doc-usage]'),
    kicker: $('[data-doc-kicker]'), breadcrumb: $('[data-breadcrumb-current]'), props: $('[data-props]'),
    events: $('[data-events]'), states: $('[data-states]'), a11y: $('[data-doc-a11y]'),
    responsive: $('[data-doc-responsive]'), do: $('[data-doc-do]'), dont: $('[data-doc-dont]'),
    related: $('[data-related]'), quickProps: $('[data-quick-props]'), preview: $('[data-preview]'),
    controls: $('[data-controls]'), code: $('[data-code]'), languageSelect: $('[data-language-select]'),
    languagePurpose: $('[data-language-purpose]'), search: $('[data-component-search]'),
    searchResults: $('[data-search-results]'), recent: $('[data-recent-list]'), toastRegion: $('[data-toast-region]'),
    sidebarFilter: $('[data-sidebar-filter]'), sidebar: $('[data-sidebar]'), rightPanel: $('[data-right-panel]'),
    backdrop: $('[data-drawer-backdrop]'), modalBackdrop: $('[data-demo-modal]'),
    modalTitle: $('#ppds-modal-title'), modalBody: $('[data-modal-body]'), codeCard: $('.ppds-code-card')
  };

  const colorTokens = [
    ['--pp-primary-50', 'Brand 50'], ['--pp-primary-100', 'Brand 100'], ['--pp-primary-200', 'Brand 200'],
    ['--pp-primary-500', 'Brand 500'], ['--pp-primary-600', 'Brand 600'], ['--pp-primary-700', 'Brand 700'],
    ['--pp-navy', 'Navy'], ['--pp-success', 'Success'], ['--pp-success-ink', 'Success Ink'],
    ['--pp-success-soft', 'Success Soft'], ['--pp-warning', 'Warning'], ['--pp-warning-ink', 'Warning Ink'],
    ['--pp-warning-soft', 'Warning Soft'], ['--pp-danger', 'Danger'], ['--pp-danger-ink', 'Danger Ink'],
    ['--pp-danger-soft', 'Danger Soft'], ['--pp-info', 'Info'], ['--pp-info-ink', 'Info Ink'],
    ['--pp-info-soft', 'Info Soft'], ['--pp-ink', 'Text'], ['--pp-muted', 'Muted'], ['--pp-line', 'Border'],
    ['--pp-surface', 'Surface'], ['--pp-surface-subtle', 'Surface Subtle'], ['--pp-canvas', 'Canvas']
  ];
  const spacingTokens = [1, 2, 3, 4, 5, 6, 8, 10, 12].map((step) => [`--pp-space-${step}`, `Space ${step}`]);
  const radiusTokens = [['--pp-radius-sm', 'Small'], ['--pp-radius', 'Default'], ['--pp-radius-lg', 'Large']];
  const shadowTokens = [['--pp-shadow-sm', 'Subtle'], ['--pp-shadow', 'Default']];
  radiusTokens.push(['--proma-radius', 'Legacy'], ['--control-radius', 'Control'], ['--card-radius', 'Card']);
  const typographyRows = [
    ['Display', 32, 700], ['Heading 1', 26, 700], ['Heading 2', 22, 700], ['Heading 3', 19, 600],
    ['Heading 4', 17, 600], ['Heading 5', 15, 600], ['Heading 6', 13, 600], ['Body', 14, 400],
    ['Caption', 11, 400], ['Label', 12, 600], ['Button text', 12, 600], ['Financial numbers', 22, 700],
    ['Code', 12, 400]
  ];

  function localDateString(date) {
    const value = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return value.toISOString().slice(0, 10);
  }

  function getToken(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  function displayNumber(value) {
    const parsed = Number(String(value).replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? new Intl.NumberFormat('fa-IR').format(parsed) : escapeHtml(value);
  }

  function labelFor(item) { return item ? (item.name + ' · ' + item.english) : ''; }

  function categoryFor(item) {
    if (!item) return { label: 'مستندات', id: 'docs' };
    if (item.group === 'templates') return { label: 'الگوهای صفحه', id: 'templates' };
    if (['colors', 'typography', 'spacing', 'radius', 'shadows', 'grid-breakpoints', 'icons'].includes(item.group)) {
      const node = (catalog.categories || []).find((category) => category.id === 'foundations');
      return { label: node ? node.label : 'بنیادهای طراحی', id: 'foundations' };
    }
    const group = groupById.get(item.group);
    return { label: group ? group.label : 'کامپوننت‌ها', id: 'components' };
  }

  function setCurrent(itemOrId) {
    const item = typeof itemOrId === 'string' ? itemById.get(itemOrId) : itemOrId;
    if (!item) return;
    current = item;
    playState = defaultState(current);
    renderCurrent();
    pushRecent(current.id);
    closeDrawers();
    if (window.location.hash === '#code-examples') $('#code-examples')?.scrollIntoView({ behavior: 'smooth' });
  }

  function renderCurrent() {
    if (!current) return;
    const category = categoryFor(current);
    if (elements.title) elements.title.textContent = current.name;
    if (elements.overview) elements.overview.textContent = current.overview;
    if (elements.usage) elements.usage.textContent = current.usage;
    if (elements.kicker) elements.kicker.textContent = `${category.id.toUpperCase()} / ${current.english.toUpperCase()}`;
    if (elements.breadcrumb) elements.breadcrumb.textContent = current.name;
    if (elements.a11y) elements.a11y.textContent = current.accessibility;
    if (elements.responsive) elements.responsive.textContent = current.responsive;
    if (elements.do) elements.do.textContent = current.do;
    if (elements.dont) elements.dont.textContent = current.dont;
    renderProps();
    renderEventsAndStates();
    renderRelated();
    renderQuickProps();
    renderControls();
    updatePreviewAndCode();
    updateActiveNav();
    featherReplace();
  }

  function renderProps() {
    if (!elements.props) return;
    elements.props.innerHTML = (current.props || []).map((prop) => `<tr><td>${escapeHtml(prop.name)}</td><td>${escapeHtml(prop.type)}</td><td>${escapeHtml(prop.default)}</td><td>${escapeHtml(prop.description)}</td></tr>`).join('');
  }

  function renderEventsAndStates() {
    if (elements.events) elements.events.innerHTML = (current.events || []).map((event) => `<li><code>${escapeHtml(event.name)}</code> — ${escapeHtml(event.description)}</li>`).join('');
    if (elements.states) elements.states.innerHTML = (current.states || []).map((state) => `<span class="ppds-state-chip">${escapeHtml(state)}</span>`).join('');
  }

  function resolveRelated(id) {
    if (itemById.has(id)) return itemById.get(id);
    const group = groupById.get(id);
    return group && group.items && group.items[0] ? group.items[0] : null;
  }

  function renderRelated() {
    if (!elements.related) return;
    const related = (current.related || []).map(resolveRelated).filter((item) => item && item.id !== current.id).slice(0, 4);
    elements.related.innerHTML = related.map((item) => `<button class="ppds-related-card" type="button" data-related-item="${escapeHtml(item.id)}"><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.english)}</small></span><i data-feather="arrow-left" aria-hidden="true"></i></button>`).join('') || '<p class="ppds-muted-small">مورد مرتبطی ثبت نشده است.</p>';
    featherReplace();
  }

  function renderQuickProps() {
    if (!elements.quickProps) return;
    elements.quickProps.innerHTML = (current.props || []).slice(0, 4).map((prop) => `<div class="ppds-quick-prop"><code>${escapeHtml(prop.name)}</code><span>${escapeHtml(prop.type)}</span></div>`).join('');
  }

  function controlText(id, label, value, type = 'text', wide = false) {
    return `<label class="ppds-control-field${wide ? ' ppds-control-field--wide' : ''}"><span>${escapeHtml(label)}</span><input type="${type}" data-control="${id}" value="${escapeHtml(value)}"></label>`;
  }

  function controlSelect(id, label, value, options) {
    return `<label class="ppds-control-field"><span>${escapeHtml(label)}</span><select data-control="${id}">${options.map((option) => `<option value="${escapeHtml(option[0])}"${value === option[0] ? ' selected' : ''}>${escapeHtml(option[1])}</option>`).join('')}</select></label>`;
  }

  function controlCheck(id, label, checked) {
    return `<label class="ppds-control-check"><input type="checkbox" data-control="${id}"${checked ? ' checked' : ''}><span>${escapeHtml(label)}</span></label>`;
  }

  function renderControls() {
    if (!elements.controls || !current) return;
    const family = current.family;
    let html = '';
    if (family === 'buttons') {
      html += controlText('label', 'متن دکمه', playState.label, 'text', true);
      html += controlSelect('variant', 'گونه', playState.variant, [['primary', 'اصلی'], ['secondary', 'ثانویه'], ['outline', 'خطی'], ['ghost', 'بدون زمینه'], ['danger', 'خطر'], ['success', 'موفقیت'], ['warning', 'هشدار'], ['link', 'پیوند']]);
      html += controlSelect('size', 'اندازه', playState.size, [['small', 'کوچک'], ['medium', 'معمولی'], ['large', 'بزرگ']]);
      html += controlCheck('iconEnabled', 'نمایش آیکون', playState.iconEnabled);
      if (playState.iconEnabled || current.variant === 'icon') html += controlText('icon', 'نام Feather', playState.icon, 'text');
      html += controlCheck('loading', 'در حال بارگذاری', playState.loading);
      html += controlCheck('disabled', 'غیرفعال', playState.disabled);
      html += controlCheck('fullWidth', 'تمام‌عرض', playState.fullWidth);
      html += controlCheck('rounded', 'گوشه گرد', playState.rounded);
    } else if (family === 'foundation-type') {
      html += `<label class="ppds-control-field ppds-control-field--wide"><span>اندازه نمونه · ${playState.fontSize}px</span><input type="range" min="12" max="48" step="1" value="${Number(playState.fontSize)}" data-control="fontSize"></label>`;
      html += controlSelect('fontWeight', 'وزن قلم', String(playState.fontWeight), [['400', 'Regular · 400'], ['600', 'SemiBold · 600'], ['700', 'Bold · 700']]);
      html += controlText('label', 'متن نمونه', playState.label, 'text', true);
    } else if (family === 'forms') {
      html += controlText('value', 'مقدار نمونه', playState.value, 'text', true);
      html += controlSelect('controlStatus', 'وضعیت', playState.controlStatus, [['default', 'پیش‌فرض'], ['focus', 'تمرکز'], ['hover', 'شناور'], ['error', 'خطا'], ['success', 'موفق'], ['disabled', 'غیرفعال'], ['readonly', 'فقط‌خواندنی']]);
      html += controlCheck('required', 'اجباری', playState.required);
      html += '<p class="ppds-control-hint ppds-control-field--wide">مقدارهای ورودی فقط در همین پیش‌نمایش می‌مانند.</p>';
    } else if (family === 'icons') {
      html += controlSelect('iconSet', 'خانواده آیکون', playState.iconSet, [['feather', `Feather (${(catalog.icons.feather || []).length})`], ['fontawesome', `Font Awesome (${(catalog.icons.fontawesome || []).length})`], ['themify', `Themify (${(catalog.icons.themify || []).length})`], ['proma', `Proma Pay (${(catalog.icons.proma || []).length})`]]);
      html += controlText('iconSearch', 'جست‌وجوی آیکون', playState.iconSearch, 'search', true);
    } else if (['foundation-colors', 'foundation-spacing', 'foundation-radius', 'foundation-shadows', 'foundation-grid'].includes(family)) {
      html += '<p class="ppds-control-hint ppds-control-field--wide">مقدارها به‌صورت مستقیم از CSS tokenهای همین برنامه خوانده می‌شوند.</p>';
    } else if (family === 'date-time') {
      html += controlText('selectedDate', 'تاریخ انتخابی', playState.selectedDate, 'date', true);
      html += '<p class="ppds-control-hint ppds-control-field--wide">تاریخ شمسی با Intl API مرورگر نمایش داده می‌شود.</p>';
    } else if (['financial', 'installments', 'payments', 'accounting'].includes(family)) {
      html += controlText('amount', 'مبلغ نمونه', playState.amount, 'text', true);
      html += controlSelect('currency', 'واحد پول', playState.currency, [['تومان', 'تومان'], ['ریال', 'ریال']]);
      html += controlSelect('sampleTone', 'وضعیت', playState.sampleTone, [['success', 'موفق'], ['warning', 'در انتظار'], ['danger', 'نیازمند پیگیری'], ['info', 'اطلاعاتی']]);
      html += '<p class="ppds-control-hint ppds-control-field--wide">هیچ تراکنش، پرداخت یا تغییر حسابی انجام نمی‌شود.</p>';
    } else if (family === 'templates') {
      html += controlSelect('viewMode', 'نمای نمایش', playState.viewMode, [['table', 'جدول'], ['cards', 'کارت‌ها'], ['summary', 'خلاصه']]);
      html += controlText('filterText', 'فیلتر محلی', playState.filterText, 'search', true);
    } else {
      html += controlSelect('controlStatus', 'وضعیت نمونه', playState.controlStatus, [['default', 'پیش‌فرض'], ['active', 'فعال'], ['disabled', 'غیرفعال'], ['loading', 'بارگذاری'], ['empty', 'خالی']]);
      html += controlText('filterText', 'عبارت نمونه', playState.filterText, 'text', true);
    }
    elements.controls.innerHTML = html;
  }

  function updatePreviewAndCode() {
    if (!current) return;
    if (elements.preview) {
      elements.preview.innerHTML = renderPreview(current);
      featherReplace();
    }
    updateCode();
  }

  function previewFrame(title, body, caption = 'پیش‌نمایش محلی · داده ساختگی') {
    return `<div class="ppds-preview-surface"><div class="ppds-preview-caption"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(caption)}</span></div><div class="ppds-preview-content">${body}</div></div>`;
  }

  function renderPreview(item) {
    const family = item.family;
    if (family === 'buttons') return previewFrame('دکمه / ' + item.english, renderButtons(item));
    if (family === 'forms') return previewFrame(item.name, renderForm(item));
    if (family === 'date-time') return previewFrame(item.name, renderDateTime(item));
    if (family === 'overlays') return previewFrame(item.name, renderOverlay(item));
    if (family === 'navigation') return previewFrame(item.name, renderNavigation(item));
    if (family === 'data-display') return previewFrame(item.name, renderDataDisplay(item));
    if (family === 'feedback') return previewFrame(item.name, renderFeedback(item));
    if (family === 'advanced') return previewFrame(item.name, renderAdvanced(item));
    if (family === 'financial') return previewFrame(item.name, renderFinancial(item));
    if (family === 'installments') return previewFrame(item.name, renderInstallments(item));
    if (family === 'contracts') return previewFrame(item.name, renderContracts(item));
    if (family === 'payments') return previewFrame(item.name, renderPayments(item));
    if (family === 'accounting') return previewFrame(item.name, renderAccounting(item));
    if (family === 'collection-legal') return previewFrame(item.name, renderCollectionLegal(item));
    if (family === 'templates') return previewFrame(item.name, renderTemplate(item));
    if (family === 'foundation-colors') return previewFrame('Color Tokens', renderColors());
    if (family === 'foundation-type') return previewFrame('Typography', renderTypography());
    if (family === 'foundation-spacing') return previewFrame('Spacing Scale', renderSpacing());
    if (family === 'foundation-radius') return previewFrame('Border Radius', renderRadius());
    if (family === 'foundation-shadows') return previewFrame('Elevation', renderShadows());
    if (family === 'foundation-grid') return previewFrame('Grid & Breakpoints', renderBreakpoints());
    if (family === 'icons') return previewFrame('Icon Library', renderIconGallery());
    return previewFrame(item.name, `<p>${escapeHtml(item.overview)}</p>`);
  }

  function renderButtons(item) {
    const variant = item.variant === 'loading' ? 'primary' : (item.variant === 'disabled' ? 'secondary' : item.variant);
    const currentVariant = playState.variant || variant;
    const isIcon = playState.iconEnabled;
    const disabled = playState.disabled;
    const loading = playState.loading;
    const classes = `ppds-demo-button ppds-demo-button--${escapeHtml(currentVariant)} ppds-demo-button--${escapeHtml(playState.size)}${playState.fullWidth ? ' is-full' : ''}${playState.rounded ? ' is-rounded' : ''}${isIcon ? ' is-icon' : ''}${loading ? ' is-loading' : ''}`;
    const icon = isIcon ? renderFeather(safeText(playState.icon, 'plus')) : '';
    const label = isIcon ? `<span class="sr-only">${escapeHtml(playState.label)}</span>` : escapeHtml(playState.label);
    if (item.variant === 'group') return `<div class="ppds-demo-button-group"><button class="ppds-demo-button ppds-demo-button--${escapeHtml(currentVariant)} ppds-demo-button--${escapeHtml(playState.size)}" type="button"${disabled ? ' disabled' : ''} data-demo-action>ذخیره</button><button class="ppds-demo-button ppds-demo-button--secondary ppds-demo-button--${escapeHtml(playState.size)}" type="button"${disabled ? ' disabled' : ''} data-demo-action>پیش‌نمایش</button><button class="ppds-demo-button ppds-demo-button--ghost ppds-demo-button--${escapeHtml(playState.size)}" type="button"${disabled ? ' disabled' : ''} data-demo-action>لغو</button></div>`;
    if (item.variant === 'split') return `<div class="ppds-demo-split"><button class="ppds-demo-button ppds-demo-button--${escapeHtml(currentVariant)} ppds-demo-button--${escapeHtml(playState.size)}${loading ? ' is-loading' : ''}" type="button"${disabled ? ' disabled' : ''} data-demo-action>${escapeHtml(playState.label)}</button><button class="ppds-demo-button ppds-demo-button--${escapeHtml(currentVariant)}" type="button" aria-label="گزینه‌های بیشتر"${disabled ? ' disabled' : ''} data-demo-dropdown><i data-feather="chevron-down" aria-hidden="true"></i></button></div>`;
    return `<button class="${classes}" type="button"${disabled ? ' disabled aria-disabled="true"' : ''}${loading ? ' aria-busy="true"' : ''} data-demo-action>${icon}${label}</button>`;
  }

  function renderForm(item) {
    const type = item.variant;
    const status = playState.controlStatus;
    const disabled = status === 'disabled';
    const readonly = status === 'readonly';
    const invalid = status === 'error';
    const stateAttr = status === 'success' ? ' data-state="success"' : '';
    const title = `نمونه ${item.name}`;
    const label = escapeHtml(item.name);
    const required = playState.required ? ' required' : '';
    const inputValue = escapeHtml(playState.value || '');
    let control = '';
    if (type === 'checkbox' || type === 'radio') {
      control = `<div class="ppds-form-options"><label><input type="${type}" name="ppds-choice"${type === 'checkbox' ? ' checked' : ''}${disabled ? ' disabled' : ''}> گزینه اول</label><label><input type="${type}" name="ppds-choice"${disabled ? ' disabled' : ''}> گزینه دوم</label></div>`;
    } else if (type === 'switch') {
      control = `<label class="ppds-switch"><input type="checkbox" checked${disabled ? ' disabled' : ''}><span class="ppds-switch-track"></span><span>اعلان پرداخت فعال است</span></label>`;
    } else if (type === 'range') {
      control = `<input type="range" min="0" max="100" value="64" aria-label="${label}"${disabled ? ' disabled' : ''}>`;
    } else if (type === 'otp') {
      control = '<div class="ppds-otp" dir="ltr"><input inputmode="numeric" maxlength="1" aria-label="رقم اول" value="1"><input inputmode="numeric" maxlength="1" aria-label="رقم دوم" value="4"><input inputmode="numeric" maxlength="1" aria-label="رقم سوم" value="0"><input inputmode="numeric" maxlength="1" aria-label="رقم چهارم" value="5"><input inputmode="numeric" maxlength="1" aria-label="رقم پنجم" value="2"><input inputmode="numeric" maxlength="1" aria-label="رقم ششم" value="8"></div>';
    } else if (type === 'file' || type === 'image') {
      control = `<label class="ppds-file-drop"><input type="file" class="sr-only" accept="${type === 'image' ? 'image/*' : '.pdf,.jpg,.png'}"${disabled ? ' disabled' : ''}><i data-feather="${type === 'image' ? 'image' : 'upload-cloud'}" aria-hidden="true"></i><span>${type === 'image' ? 'تصویر را انتخاب کنید' : 'فایل را انتخاب کنید'}</span><small>فقط انتخاب محلی؛ فایلی بارگذاری نمی‌شود.</small></label>`;
    } else if (type === 'rich-text') {
      control = `<div class="ppds-rich-toolbar" aria-label="ابزارهای متن"><button type="button" aria-label="پررنگ"><strong>B</strong></button><button type="button" aria-label="فهرست"><i data-feather="list" aria-hidden="true"></i></button><button type="button" aria-label="پیوند"><i data-feather="link" aria-hidden="true"></i></button></div><textarea class="ppds-rich-editor" aria-label="متن قرارداد نمونه"${disabled ? ' disabled' : ''}>شرایط نمونه قرارداد در این قسمت نمایش داده می‌شود.</textarea>`;
    } else if (type === 'textarea') {
      control = `<textarea aria-label="${label}" placeholder="${label}"${required}${disabled ? ' disabled' : ''}${readonly ? ' readonly' : ''}${invalid ? ' aria-invalid="true"' : ''}${stateAttr}>${inputValue}</textarea>`;
    } else if (type === 'searchable-select') {
      const options = ['قسط ماهانه', 'پرداخت کارت‌به‌کارت', 'پرداخت درگاه'].filter((option) => option.includes(playState.filterText || ''));
      control = `<div class="ppds-search-select"><input type="search" aria-label="${label}" placeholder="جست‌وجوی روش پرداخت" value="${escapeHtml(playState.filterText)}" data-search-select${disabled ? ' disabled' : ''}${invalid ? ' aria-invalid="true"' : ''}><div role="listbox" aria-label="روش‌های پرداخت" data-search-select-options>${options.map((option) => `<button type="button" role="option" aria-selected="${playState.value === option}" data-search-option="${escapeHtml(option)}">${escapeHtml(option)}</button>`).join('')}</div><small class="ppds-form-help">انتخاب فعلی: ${escapeHtml(playState.value || '—')}</small></div>`;
    } else if (type === 'select' || type === 'multi-select') {
      control = `<select aria-label="${label}"${type === 'multi-select' ? ' multiple size="3"' : ''}${disabled ? ' disabled' : ''}${invalid ? ' aria-invalid="true"' : ''}${stateAttr}><option value="">انتخاب کنید</option><option>قسط ماهانه</option><option>پرداخت کارت‌به‌کارت</option><option>پرداخت درگاه</option></select>`;
    } else {
      const nativeType = ({ password: 'password', email: 'email', tel: 'tel', 'national-id': 'text', search: 'search', number: 'number', money: 'text', percentage: 'text' })[type] || 'text';
      const dir = ['tel', 'national-id'].includes(type) ? ' dir="ltr"' : '';
      const placeholder = type === 'money' ? '۱۲٬۵۰۰٬۰۰۰ تومان' : (type === 'percentage' ? '٪ ۱۲٫۵' : label);
      control = `<input type="${nativeType}" aria-label="${label}" placeholder="${escapeHtml(placeholder)}" value="${inputValue}"${dir}${required}${disabled ? ' disabled' : ''}${readonly ? ' readonly' : ''}${invalid ? ' aria-invalid="true"' : ''}${stateAttr}>`;
    }
    let hint = '<p class="ppds-form-help">این مقدار در نمونه مرورگر باقی می‌ماند.</p>';
    if (invalid) hint = '<p class="ppds-form-error">مقدار واردشده معتبر نیست. کد خطا در نمونه نمایشی است.</p>';
    if (status === 'success') hint = '<p class="ppds-form-success">مقدار نمونه معتبر است.</p>';
    return `<form class="ppds-form-demo" data-state="${escapeHtml(status)}"><label>${label}${playState.required ? ' <span aria-hidden="true">*</span>' : ''}</label>${control}${hint}<button class="ppds-demo-button ppds-demo-button--primary" type="button" data-demo-action>بررسی نمونه</button></form>`;
  }

  const jalaliDateFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'UTC' });
  const jalaliMonthFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: 'long', year: 'numeric', timeZone: 'UTC' });
  const persianWeekdays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  function jalaliParts(date) {
    const parts = jalaliDateFormatter.formatToParts(date);
    return Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, Number(part.value)]));
  }

  function parseLocalDate(value) {
    const parts = String(value || '').split('-').map(Number);
    if (parts.length !== 3 || !parts[0]) return new Date();
    return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12));
  }

  function startOfJalaliMonth(date) {
    const currentDate = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), 12));
    for (let step = 0; step < 32; step += 1) {
      if (jalaliParts(currentDate).day === 1) return currentDate;
      currentDate.setUTCDate(currentDate.getUTCDate() - 1);
    }
    return currentDate;
  }

  function renderCalendar(item) {
    const selected = parseLocalDate(playState.selectedDate);
    const visible = parseLocalDate(playState.calendarViewDate || playState.selectedDate);
    const monthStart = startOfJalaliMonth(visible);
    const startOffset = (monthStart.getUTCDay() + 1) % 7;
    const cells = [];
    const today = new Date();
    const todayKey = localDateString(new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate(), 12)));
    for (let index = 0; index < 42; index += 1) {
      const date = new Date(monthStart.getTime());
      date.setUTCDate(date.getUTCDate() + index - startOffset);
      const dateKey = localDateString(date);
      const parts = jalaliParts(date);
      const currentParts = jalaliParts(monthStart);
      const selectedClass = dateKey === playState.selectedDate ? ' is-selected' : '';
      const todayClass = dateKey === todayKey ? ' is-today' : '';
      const otherMonth = parts.year !== currentParts.year || parts.month !== currentParts.month;
      const otherClass = otherMonth ? ' is-other-month' : '';
      cells.push(`<button type="button" class="${selectedClass}${todayClass}${otherClass}" data-calendar-date="${dateKey}" aria-pressed="${dateKey === playState.selectedDate}" aria-label="${escapeHtml(jalaliDateFormatter.format(date))}">${escapeHtml(parts.day)}</button>`);
    }
    const monthLabel = jalaliMonthFormatter.format(monthStart);
    if (item.variant === 'relative') return `<div class="ppds-financial-card"><div class="ppds-financial-head"><span><i data-feather="clock" aria-hidden="true"></i> زمان نسبی</span><span class="ppds-status-pill" data-tone="info">به‌روزرسانی‌شده</span></div><strong>امروز، ساعت ۱۰:۳۰</strong><small class="ppds-form-help">معادل ${escapeHtml(jalaliDateFormatter.format(selected))}</small></div>`;
    if (item.variant === 'deadline') return `<div class="ppds-financial-card"><div class="ppds-financial-head"><span><i data-feather="alert-circle" aria-hidden="true"></i> سررسید نزدیک</span><span class="ppds-status-pill" data-tone="warning">۳ روز باقی‌مانده</span></div><strong>${escapeHtml(jalaliDateFormatter.format(selected))}</strong><small class="ppds-form-help">موعد نمونه با وضعیت متنی مشخص شده است.</small></div>`;
    if (item.variant === 'time' || item.variant === 'datetime') return `<div class="ppds-form-demo"><label>${item.name}</label><input type="${item.variant === 'time' ? 'time' : 'datetime-local'}" aria-label="${escapeHtml(item.name)}" data-date-input><p class="ppds-form-help">نمایش مرورگر بومی؛ زمان فقط نمونه است.</p></div>`;
    if (item.variant === 'range') return `<div class="ppds-demo-interactive-row"><input type="date" aria-label="از تاریخ" value="${escapeHtml(playState.selectedDate)}" data-date-input><span>تا</span><input type="date" aria-label="تا تاریخ" value="${escapeHtml(playState.selectedDate)}" data-date-input></div>`;
    return `<div class="ppds-calendar" data-calendar><div class="ppds-calendar-head"><strong>${escapeHtml(monthLabel)}</strong><div class="ppds-calendar-nav"><button type="button" aria-label="ماه بعد" data-calendar-shift="1"><i data-feather="chevron-right" aria-hidden="true"></i></button><button type="button" aria-label="ماه قبل" data-calendar-shift="-1"><i data-feather="chevron-left" aria-hidden="true"></i></button></div></div><div class="ppds-calendar-grid" role="grid" aria-label="${escapeHtml(monthLabel)}">${persianWeekdays.map((day) => `<span role="columnheader">${day}</span>`).join('')}${cells.join('')}</div><p class="ppds-calendar-selection">تاریخ انتخاب‌شده: <strong>${escapeHtml(jalaliDateFormatter.format(selected))}</strong></p></div>`;
  }

  function renderDateTime(item) {
    return renderCalendar(item);
  }

  function renderOverlay(item) {
    const label = item.variant === 'tooltip' ? 'راهنمای کوتاه برای کنترل انتخاب‌شده' : 'باز کردن پیش‌نمایش';
    if (item.variant === 'tooltip') return `<button class="ppds-demo-button ppds-demo-button--secondary" type="button" title="راهنمای کوتاه برای مشاهده وضعیت کنونی" data-demo-action>${label}</button>`;
    const menu = ['dropdown', 'context'].includes(item.variant) ? '<details><summary class="ppds-secondary-button">گزینه‌ها</summary><div class="ppds-detail-card"><button type="button" data-demo-action>ویرایش نمونه</button><button type="button" data-demo-action>کپی شناسه</button><button type="button" data-demo-action>بستن منو</button></div></details>' : '';
    const title = item.variant === 'danger' ? 'تأیید اقدام پرخطر نمونه' : (item.variant === 'confirm' ? 'تأیید تغییر نمونه' : item.name);
    return `<div class="ppds-demo-interactive-row"><button class="ppds-demo-button ${item.variant === 'danger' ? 'ppds-demo-button--danger' : 'ppds-demo-button--primary'}" type="button" data-open-demo data-modal-title="${escapeHtml(title)}" data-modal-variant="${escapeHtml(item.variant)}">${label}</button>${menu}<span class="ppds-form-help">Escape، کلیک بیرون و بازگشت focus فعال‌اند.</span></div>`;
  }

  function renderNavigation(item) {
    const labels = ['نمای کلی', 'پرداخت‌ها', 'اسناد'];
    if (item.variant === 'breadcrumb') return '<nav aria-label="مسیر راهنما" class="ppds-demo-interactive-row"><a class="ppds-inline-link" href="#" data-demo-nav>داشبورد</a><span>/</span><a class="ppds-inline-link" href="#" data-demo-nav>قراردادها</a><span>/</span><strong>PR-1405-001204</strong></nav>';
    if (item.variant === 'stepper') return '<div class="ppds-stepper" aria-label="گام دوم از سه"><span class="is-active">۱</span><i></i><span class="is-active">۲</span><i></i><span>۳</span></div>';
    if (item.variant === 'pagination') return `<div class="ppds-demo-interactive-row"><button class="ppds-secondary-button" type="button" data-page-step="-1" aria-label="صفحه قبل">‹</button><span>صفحه <strong data-page-number>${playState.page}</strong> از ۱۲</span><button class="ppds-secondary-button" type="button" data-page-step="1" aria-label="صفحه بعد">›</button></div>`;
    if (item.variant === 'header') return '<div class="ppds-financial-card"><div class="ppds-template-header"><strong>پروماپی</strong><div class="ppds-demo-interactive-row"><button type="button" class="ppds-icon-button" aria-label="جست‌وجو" data-demo-action><i data-feather="search"></i></button><button type="button" class="ppds-icon-button" aria-label="اعلان‌ها" data-demo-action><i data-feather="bell"></i></button></div></div></div>';
    if (item.variant === 'sidebar' || item.variant === 'collapsed' || item.variant === 'mobile') return `<nav class="ppds-demo-interactive-row" aria-label="منوی نمونه">${labels.map((label, index) => `<button type="button" class="ppds-demo-button ${index === playState.selectedTab ? 'ppds-demo-button--primary' : 'ppds-demo-button--secondary'}" data-demo-tab="${index}">${label}</button>`).join('')}</nav><p class="ppds-form-help">بخش فعال: ${labels[playState.selectedTab] || labels[0]}</p>`;
    if (item.variant === 'bottom') return `<nav class="ppds-demo-interactive-row" aria-label="ناوبری پایین">${labels.map((label, index) => `<button type="button" class="ppds-demo-button ${index === playState.selectedTab ? 'ppds-demo-button--primary' : 'ppds-demo-button--secondary'}" data-demo-tab="${index}"><i data-feather="${['home', 'credit-card', 'file-text'][index]}" aria-hidden="true"></i>${label}</button>`).join('')}</nav>`;
    return `<div class="ppds-demo-interactive-row" role="tablist" aria-label="تب‌های نمونه">${labels.map((label, index) => `<button type="button" role="tab" aria-selected="${index === playState.selectedTab}" class="ppds-demo-button ${index === playState.selectedTab ? 'ppds-demo-button--primary' : 'ppds-demo-button--secondary'}" data-demo-tab="${index}">${label}</button>`).join('')}</div><div class="ppds-usage-card" role="tabpanel">محتوای ${labels[playState.selectedTab] || labels[0]} به‌صورت نمونه</div>`;
  }

  function renderDataDisplay(item) {
    const rows = [
      ['نگار احمدی', 'PR-001204', '۱۲٬۵۰۰٬۰۰۰', 'در انتظار'],
      ['علی رضایی', 'PR-001197', '۸٬۲۰۰٬۰۰۰', 'پرداخت‌شده'],
      ['سارا محمدی', 'PR-001185', '۵٬۷۰۰٬۰۰۰', 'سررسید گذشته']
    ];
    if (item.variant === 'accordion') return '<details class="ppds-detail-card"><summary>جزئیات قرارداد نمونه</summary><p>این بخش با باز و بسته شدن جزئیات را نشان می‌دهد.</p></details>';
    if (item.variant === 'empty') return '<div class="ppds-detail-card"><i data-feather="inbox" aria-hidden="true"></i><h4>هنوز داده‌ای وجود ندارد</h4><p>پس از افزودن رکورد، نتیجه در این قسمت دیده می‌شود.</p><button class="ppds-secondary-button" type="button" data-demo-action>افزودن نمونه</button></div>';
    if (item.variant === 'skeleton') return '<div class="ppds-template-lines" aria-label="در حال بارگذاری"><i></i><i></i><i></i></div>';
    if (item.variant === 'progress' || item.variant === 'circular') return `<div class="ppds-financial-card"><div class="ppds-financial-head"><span>پیشرفت اقساط</span><strong>۶۴٪</strong></div><progress value="64" max="100" aria-label="پیشرفت اقساط" style="width:100%;accent-color:var(--ppds-primary)"></progress><small>۸ قسط از ۱۲ قسط پرداخت شده است.</small></div>`;
    if (item.variant === 'badge' || item.variant === 'status') return '<div class="ppds-demo-interactive-row"><span class="ppds-status-pill" data-tone="success">پرداخت‌شده</span><span class="ppds-status-pill" data-tone="warning">در انتظار</span><span class="ppds-status-pill" data-tone="danger">سررسید گذشته</span></div>';
    if (item.variant === 'divider') return '<div style="width:100%"><span class="ppds-form-help">اطلاعات قرارداد</span><hr style="border:0;border-block-start:1px solid var(--ppds-line);margin:8px 0"><span>شماره: PR-1405-001204</span></div>';
    if (item.variant === 'avatar' || item.variant === 'avatars') return '<div class="ppds-demo-interactive-row"><span class="ppds-brand-mark" aria-label="نگار احمدی">ن</span><span class="ppds-brand-mark" aria-label="علی رضایی" style="background:var(--ppds-success)">ع</span><span class="ppds-brand-mark" aria-label="۳ نفر دیگر" style="background:var(--ppds-warning)">+۳</span></div>';
    if (item.variant === 'timeline') return '<ol class="ppds-detail-card"><li>قرارداد نمونه ایجاد شد <small>۱۴۰۵/۰۸/۱۲</small></li><li>پرداخت قسط ثبت شد <small>۱۴۰۵/۰۸/۲۹</small></li><li>پیگیری بعدی برنامه‌ریزی شد <small>۱۴۰۵/۰۹/۰۵</small></li></ol>';
    if (item.variant === 'list') return `<ul class="ppds-detail-card">${rows.map((row) => `<li>${escapeHtml(row[0])} <small>${escapeHtml(row[3])}</small></li>`).join('')}</ul>`;
    if (item.variant === 'metric') return '<div class="ppds-financial-card"><small>مجموع پرداخت ماه</small><strong class="ppds-financial-amount">۱۲٬۵۰۰٬۰۰۰ <small>تومان</small></strong><span class="ppds-status-pill" data-tone="success">۸٪ افزایش نسبت به ماه قبل</span></div>';
    if (item.variant === 'financial') return '<div class="ppds-demo-interactive-row"><strong dir="ltr">۱۲٬۵۰۰٬۰۰۰</strong><span>تومان</span><span class="ppds-status-pill" data-tone="success">موجودی</span></div>';
    const table = `<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>مشتری</th><th>شناسه</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${escapeHtml(row[0])}</td><td dir="ltr">${escapeHtml(row[1])}</td><td data-numeric>${escapeHtml(row[2])}</td><td>${escapeHtml(row[3])}</td></tr>`).join('')}</tbody></table></div>`;
    if (item.variant === 'grid') return `${table}<button class="ppds-secondary-button" type="button" data-sort-table>مرتب‌سازی جدول نمونه <i data-feather="arrow-down-up"></i></button>`;
    return table;
  }

  function renderFeedback(item) {
    const tone = item.variant === 'error' ? 'danger' : (item.variant === 'warning' ? 'warning' : (item.variant === 'success' ? 'success' : playState.sampleTone));
    if (item.variant === 'loading') return '<div class="ppds-demo-toast"><span class="ppds-live-dot" aria-hidden="true"></span><p>در حال آماده‌سازی نمونه…<small>این وضعیت به عملیات سامانه متصل نیست.</small></p></div>';
    if (item.variant === 'offline') return '<div class="ppds-demo-toast" data-tone="warning"><i data-feather="wifi-off" aria-hidden="true"></i><p>اتصال نمونه قطع است.<small>دوباره تلاش کنید.</small></p><button type="button" class="ppds-secondary-button" data-demo-action>تلاش مجدد</button></div>';
    if (item.variant === 'permission') return '<div class="ppds-demo-toast" data-tone="danger"><i data-feather="lock" aria-hidden="true"></i><p>به این بخش دسترسی ندارید.<small>برای راهنمایی با مدیر سامانه تماس بگیرید.</small></p></div>';
    const text = item.variant === 'empty-results' ? 'نتیجه‌ای برای این جست‌وجو پیدا نشد.' : (item.variant === 'error' ? 'ذخیره تغییر نمونه انجام نشد.' : (item.variant === 'warning' ? 'مهلت پرداخت نمونه نزدیک است.' : (item.variant === 'success' ? 'تغییر نمونه با موفقیت انجام شد.' : 'تغییرات نمونه ذخیره شد.')));
    return `<div class="ppds-demo-toast" data-tone="${tone}"><i data-feather="${tone === 'danger' ? 'alert-circle' : tone === 'warning' ? 'alert-triangle' : tone === 'info' ? 'info' : 'check-circle'}" aria-hidden="true"></i><p>${text}<small>این پیام فقط برای نمایش سیستم بازخورد است.</small></p></div><button class="ppds-secondary-button" type="button" data-demo-toast="${tone}">نمایش پیام</button>`;
  }

  function renderAdvanced(item) {
    if (item.variant === 'kanban' || item.variant === 'drag-drop') return '<div class="ppds-kanban" data-kanban><div class="ppds-kanban-column" data-drop-column="todo"><strong>برای بررسی</strong><div class="ppds-kanban-card" draggable="true" data-drag-card="1">قرارداد PR-001204</div><div class="ppds-kanban-card" draggable="true" data-drag-card="2">رسید پرداخت نمونه</div></div><div class="ppds-kanban-column" data-drop-column="active"><strong>در حال پیگیری</strong><div class="ppds-kanban-card" draggable="true" data-drag-card="3">قسط شماره ۶</div></div><div class="ppds-kanban-column" data-drop-column="done"><strong>تکمیل‌شده</strong></div></div>';
    if (item.variant === 'command' || item.variant === 'search-overlay' || item.variant === 'global-search') return '<div class="ppds-form-demo"><label for="ppds-command-search">جست‌وجو در الگوی نمونه</label><input id="ppds-command-search" type="search" placeholder="پرداخت، قرارداد یا مشتری"><div class="ppds-detail-card"><button type="button" data-command-option data-demo-action>باز کردن قرارداد نمونه</button><button type="button" data-command-option data-demo-action>نمایش پرداخت‌های اخیر</button><button type="button" data-command-option data-demo-action>ساخت قسط نمونه</button></div></div>';
    if (item.variant === 'multi-step') {
      const step = Math.max(0, Math.min(2, Number(playState.selectedTab) || 0));
      const labels = ['اطلاعات مشتری', 'جزئیات قرارداد', 'مرور نمونه'];
      const content = [
        '<label>نام مشتری نمونه<input type="text" placeholder="نام و نام خانوادگی"></label>',
        '<label>مبلغ نمونه<input type="text" value="۱۲۵٬۰۰۰٬۰۰۰ تومان"></label>',
        '<div class="ppds-detail-card">اطلاعات نمونه آماده مرور است.</div>'
      ];
      return `<div class="ppds-form-demo"><div class="ppds-stepper" aria-label="گام ${step + 1} از ۳">${[0, 1, 2].map((index) => `<span class="${index <= step ? 'is-active' : ''}">${index + 1}</span>${index < 2 ? '<i></i>' : ''}`).join('')}</div><p>${labels[step]}</p>${content[step]}<div class="ppds-demo-interactive-row"><button class="ppds-secondary-button" type="button" data-step-prev${step === 0 ? ' disabled' : ''}>مرحله قبل</button><button class="ppds-primary-button" type="button" data-step-next${step === 2 ? ' disabled' : ''}>مرحله بعد</button></div></div>`;
    }
    if (item.variant === 'filter') return '<div class="ppds-form-demo"><label>فیلتر وضعیت</label><div class="ppds-demo-interactive-row"><select aria-label="وضعیت"><option>همه</option><option>در انتظار</option><option>پرداخت‌شده</option><option>معوق</option></select><select aria-label="بازه"><option>این ماه</option><option>ماه قبل</option></select><button type="button" class="ppds-secondary-button" data-demo-filter>اعمال</button></div><div class="ppds-state-list"><span class="ppds-state-chip">وضعیت: همه</span><span class="ppds-state-chip">بازه: این ماه</span></div></div>';
    if (item.variant === 'bulk') return '<div class="ppds-form-demo"><div class="ppds-form-options"><label><input type="checkbox" data-select-all> انتخاب همه</label><label><input type="checkbox" class="ppds-bulk-row"> قرارداد PR-001204</label><label><input type="checkbox" class="ppds-bulk-row"> قرارداد PR-001197</label><label><input type="checkbox" class="ppds-bulk-row"> قرارداد PR-001185</label></div><button class="ppds-secondary-button" type="button" data-bulk-action>اقدام گروهی روی ۰ مورد</button></div>';
    if (item.variant === 'export') return '<div class="ppds-demo-interactive-row"><button class="ppds-secondary-button" type="button" data-export="csv"><i data-feather="file-text"></i> دریافت CSV نمونه</button><button class="ppds-secondary-button" type="button" data-export="json"><i data-feather="code"></i> دریافت JSON نمونه</button></div>';
    if (item.variant === 'notifications') return '<div class="ppds-detail-card"><h4>اعلان‌ها <span class="ppds-status-pill" data-tone="info">۲ تازه</span></h4><ul><li>پرداخت نمونه دریافت شد <small>۵ دقیقه پیش</small></li><li>سررسید قسط نزدیک است <small>امروز</small></li></ul></div>';
    if (item.variant === 'activity') return '<ol class="ppds-detail-card"><li>نگار احمدی · رسید نمونه بررسی شد</li><li>علی رضایی · یادآور زمان‌بندی شد</li><li>کارشناس نمونه · قرارداد را مشاهده کرد</li></ol>';
    if (item.variant === 'file-manager') return '<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>نام فایل</th><th>نوع</th><th>عمل</th></tr></thead><tbody><tr><td>قرارداد-نمونه.pdf</td><td>PDF</td><td><button type="button" data-demo-action>پیش‌نمایش</button></td></tr><tr><td>رسید-نمونه.png</td><td>PNG</td><td><button type="button" data-demo-action>پیش‌نمایش</button></td></tr></tbody></table><label class="ppds-file-drop"><input type="file" class="sr-only"><i data-feather="upload-cloud"></i> انتخاب فایل محلی</label></div>';
    if (item.variant === 'rich-editor') return '<div class="ppds-form-demo"><div class="ppds-rich-toolbar"><button type="button"><strong>B</strong></button><button type="button"><i data-feather="italic"></i></button><button type="button"><i data-feather="list"></i></button></div><div class="ppds-rich-editor" contenteditable="true" role="textbox" aria-label="متن ویرایش‌شده نمونه">متن نمونه برای ویرایش محلی</div></div>';
    return '<div class="ppds-detail-card">آیتم نمونه را بکشید و داخل ستون دیگری رها کنید.</div>';
  }

  function renderFinancial(item) {
    const amount = escapeHtml(playState.amount);
    const positive = ['profit', 'balance', 'summary'].includes(item.variant);
    const negative = item.variant === 'loss' || item.variant === 'debt';
    const label = item.variant === 'profit' ? 'سود خالص نمونه' : item.variant === 'loss' ? 'زیان نمونه' : item.variant === 'debt' ? 'مانده بدهی نمونه' : item.name;
    if (item.variant === 'currency-input') return `<div class="ppds-form-demo"><label>مبلغ ${escapeHtml(playState.currency)}<input type="text" inputmode="decimal" value="${amount}" data-financial-input aria-label="مبلغ نمونه"></label><p class="ppds-form-help" data-financial-output>پیش‌نمایش ${displayNumber(playState.amount)} ${escapeHtml(playState.currency)}</p></div>`;
    return `<article class="ppds-financial-card"><div class="ppds-financial-head"><span><i data-feather="${negative ? 'trending-down' : 'wallet'}" aria-hidden="true"></i> ${escapeHtml(label)}</span><span class="ppds-status-pill" data-tone="${escapeHtml(playState.sampleTone)}">${playState.sampleTone === 'success' ? 'تسویه نمونه' : playState.sampleTone === 'warning' ? 'در انتظار' : playState.sampleTone === 'danger' ? 'پیگیری' : 'گزارشی'}</span></div><div class="ppds-financial-amount${positive ? ' ppds-financial-positive' : ''}${negative ? ' ppds-financial-negative' : ''}" dir="ltr">${amount}<small>${escapeHtml(playState.currency)}</small></div><div class="ppds-financial-meta"><span>شماره قرارداد: PR-1405-001204</span><span>تاریخ: ۱۴۰۵/۰۹/۰۵</span></div></article>`;
  }

  function renderInstallments(item) {
    const status = item.variant === 'overdue' ? 'danger' : (item.variant === 'due' ? 'warning' : playState.sampleTone);
    if (item.variant === 'table') return `<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody><tr><td>۶ از ۱۲</td><td>۱۴۰۵/۰۹/۱۵</td><td data-numeric>${escapeHtml(playState.amount)} ${escapeHtml(playState.currency)}</td><td><span class="ppds-status-pill" data-tone="${status}">${status === 'danger' ? 'معوق' : status === 'warning' ? 'نزدیک سررسید' : 'در انتظار'}</span></td></tr><tr><td>۷ از ۱۲</td><td>۱۴۰۵/۱۰/۱۵</td><td data-numeric>۸٬۵۰۰٬۰۰۰ تومان</td><td>برنامه‌ریزی‌شده</td></tr></tbody></table></div>`;
    if (item.variant === 'timeline') return '<ol class="ppds-detail-card"><li>قسط ۴ · پرداخت‌شده <small>۱۴۰۵/۰۷/۱۵</small></li><li>قسط ۵ · پرداخت‌شده <small>۱۴۰۵/۰۸/۱۵</small></li><li>قسط ۶ · سررسید ۱۴۰۵/۰۹/۱۵</li></ol>';
    if (item.variant === 'progress') return '<div class="ppds-financial-card"><div class="ppds-financial-head"><span>اقساط پرداخت‌شده</span><strong>۶ از ۱۲</strong></div><progress max="12" value="6" style="width:100%;accent-color:var(--ppds-primary)" aria-label="شش قسط از دوازده"></progress><small>۵۰٪ برنامه پرداخت نمونه</small></div>';
    return `<article class="ppds-financial-card"><div class="ppds-financial-head"><span>قسط شماره ۶ از ۱۲</span><span class="ppds-status-pill" data-tone="${status}">${status === 'danger' ? 'معوق' : status === 'warning' ? 'نزدیک سررسید' : 'در انتظار'}</span></div><strong class="ppds-financial-amount" dir="ltr">${escapeHtml(playState.amount)}<small>${escapeHtml(playState.currency)}</small></strong><div class="ppds-financial-meta"><span>سررسید: ۱۴۰۵/۰۹/۱۵</span><span>مشتری نمونه: نگار احمدی</span></div>${item.variant === 'partial' ? '<label class="ppds-control-field">پرداخت بخشی<input type="number" min="0" value="2500000"></label>' : ''}<button class="ppds-secondary-button" type="button" data-demo-action>مشاهده جزئیات نمونه</button></article>`;
  }

  function renderContracts(item) {
    if (item.variant === 'timeline') return '<ol class="ppds-detail-card"><li>پیش‌نویس · ۱۴۰۵/۰۸/۰۱</li><li>امضا‌شده · ۱۴۰۵/۰۸/۰۵</li><li>فعال · ۱۴۰۵/۰۸/۰۶</li></ol>';
    if (item.variant === 'variables') return '<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>متغیر</th><th>مقدار نمونه</th></tr></thead><tbody><tr><td dir="ltr">{{customer_name}}</td><td>نگار احمدی</td></tr><tr><td dir="ltr">{{contract_number}}</td><td>PR-1405-001204</td></tr><tr><td dir="ltr">{{installment_amount}}</td><td>۸٬۵۰۰٬۰۰۰ تومان</td></tr></tbody></table></div>';
    if (item.variant === 'signature') return '<div class="ppds-detail-card"><div class="ppds-demo-interactive-row"><i data-feather="edit-3"></i><strong>امضای نمونه</strong><span class="ppds-status-pill" data-tone="success">ثبت‌شده</span></div><p>امضای آزمایشی برای پیش‌نمایش UI است.</p></div>';
    if (item.variant === 'document' || item.variant === 'preview') return '<div class="ppds-detail-card"><div class="ppds-financial-head"><span>سند شماره PR-1405-001204</span><span>نسخه ۳</span></div><hr><p>قرارداد فروش اقساطی ـ متن نمونه فقط جهت نمایش چیدمان سند.</p><button class="ppds-secondary-button" type="button" data-demo-action>باز کردن پیش‌نمایش</button></div>';
    return `<article class="ppds-financial-card"><div class="ppds-financial-head"><span><i data-feather="file-text"></i> PR-1405-001204</span><span class="ppds-status-pill" data-tone="success">فعال</span></div><strong>قرارداد فروش اقساطی</strong><div class="ppds-financial-meta"><span>مشتری: نگار احمدی</span><span>مبلغ: ۱۲۵٬۰۰۰٬۰۰۰ تومان</span><span>۱۲ قسط</span></div><button class="ppds-secondary-button" type="button" data-demo-action>مشاهده قرارداد نمونه</button></article>`;
  }

  function renderPayments(item) {
    if (item.variant === 'form') return `<div class="ppds-form-demo"><label>شناسه قرارداد نمونه<select><option>PR-1405-001204 · نگار احمدی</option></select></label><label>مبلغ پرداختی<input inputmode="decimal" value="${escapeHtml(playState.amount)}"></label><button class="ppds-demo-button ppds-demo-button--primary" type="button" data-open-demo data-modal-title="مرور پرداخت نمونه">مرور پیش‌نمایش</button><small>این فرم به درگاه یا سرور ارسال نمی‌شود.</small></div>`;
    if (item.variant === 'history') return '<ol class="ppds-detail-card"><li>پرداخت شماره PAY-009812 <span class="ppds-status-pill" data-tone="success">موفق</span> <small>۱۴۰۵/۰۸/۲۹</small></li><li>پرداخت شماره PAY-009744 <span class="ppds-status-pill" data-tone="success">موفق</span> <small>۱۴۰۵/۰۷/۲۹</small></li></ol>';
    if (item.variant === 'receipt') return '<article class="ppds-financial-card"><div class="ppds-financial-head"><span>رسید پرداخت نمونه</span><span class="ppds-status-pill" data-tone="success">تأییدشده</span></div><strong>PAY-1405-009812</strong><div class="ppds-financial-meta"><span>پرداخت‌کننده: نگار احمدی</span><span>درگاه آزمایشی</span></div><button class="ppds-secondary-button" type="button" data-demo-action>چاپ نمونه</button></article>';
    if (item.variant === 'allocation' || item.variant === 'settlement') return '<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>بخش</th><th>مبلغ نمونه</th><th>وضعیت</th></tr></thead><tbody><tr><td>قسط ۶</td><td data-numeric>۸٬۵۰۰٬۰۰۰ تومان</td><td>تخصیص‌یافته</td></tr><tr><td>هزینه دیرکرد</td><td data-numeric>۰ تومان</td><td>بدون تغییر</td></tr></tbody></table></div>';
    return `<article class="ppds-financial-card"><div class="ppds-financial-head"><span>تراکنش PAY-1405-009812</span><span class="ppds-status-pill" data-tone="${escapeHtml(playState.sampleTone)}">${playState.sampleTone === 'success' ? 'موفق' : playState.sampleTone === 'warning' ? 'در انتظار' : 'نیازمند پیگیری'}</span></div><strong class="ppds-financial-amount" dir="ltr">${escapeHtml(playState.amount)}<small>${escapeHtml(playState.currency)}</small></strong><div class="ppds-financial-meta"><span>تاریخ: ۱۴۰۵/۰۸/۲۹</span><span>روش: درگاه نمونه</span></div><button class="ppds-secondary-button" type="button" data-demo-action>کپی شناسه تراکنش</button></article>`;
  }

  function renderAccounting(item) {
    if (item.variant === 'selector') return '<label class="ppds-control-field">انتخاب حساب<select aria-label="انتخاب حساب نمونه"><option>۱۱۰۱ · صندوق شعبه مرکزی</option><option>۱۱۰۲ · بانک نمونه</option><option>۲۱۰۱ · حساب پرداختنی</option></select></label>';
    if (item.variant === 'report') return '<div class="ppds-template-metrics"><div class="ppds-template-metric"><small>درآمد دوره</small><strong>۱۲۵ م.ت</strong></div><div class="ppds-template-metric"><small>هزینه دوره</small><strong>۳۴ م.ت</strong></div><div class="ppds-template-metric"><small>مانده</small><strong>۹۱ م.ت</strong></div></div><div class="ppds-template-lines"><i></i><i></i><i></i></div>';
    return `<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>تاریخ</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th></tr></thead><tbody><tr><td>۱۴۰۵/۰۸/۲۹</td><td>تسویه پرداخت نمونه</td><td data-numeric>${escapeHtml(playState.amount)}</td><td data-numeric>۰</td></tr><tr><td>۱۴۰۵/۰۸/۲۹</td><td>ثبت دریافت</td><td data-numeric>۰</td><td data-numeric>${escapeHtml(playState.amount)}</td></tr></tbody></table></div>`;
  }

  function renderCollectionLegal(item) {
    if (item.variant === 'timeline' || item.variant === 'activity') return '<ol class="ppds-detail-card"><li>یادآور تماس تنظیم شد <small>۱۴۰۵/۰۹/۰۵</small></li><li>پیگیری تلفنی انجام شد <small>۱۴۰۵/۰۹/۰۲</small></li><li>پرونده برای بررسی ارسال شد <small>۱۴۰۵/۰۸/۲۹</small></li></ol>';
    if (item.variant === 'document') return '<div class="ppds-detail-card"><div class="ppds-demo-interactive-row"><i data-feather="file"></i><strong>اخطاریه-نمونه.pdf</strong><span class="ppds-status-pill" data-tone="info">PDF</span></div><button type="button" class="ppds-secondary-button" data-demo-action>مشاهده سند نمونه</button></div>';
    return `<article class="ppds-financial-card"><div class="ppds-financial-head"><span><i data-feather="briefcase"></i> پرونده نمونه LC-1405-0031</span><span class="ppds-status-pill" data-tone="${escapeHtml(playState.sampleTone)}">${playState.sampleTone === 'success' ? 'در حال پیگیری' : playState.sampleTone === 'warning' ? 'نزدیک سررسید' : 'نیازمند اقدام'}</span></div><strong>نگار احمدی · قرارداد PR-1405-001204</strong><div class="ppds-financial-meta"><span>مسئول: کارشناس نمونه</span><span>موعد: ۱۴۰۵/۰۹/۱۵</span><span>مانده نمونه: ${escapeHtml(playState.amount)} تومان</span></div><button class="ppds-secondary-button" type="button" data-open-demo data-modal-title="یادآور نمونه">مشاهده پیگیری</button></article>`;
  }

  function renderTemplate(item) {
    const rows = ['نگار احمدی', 'علی رضایی', 'سارا محمدی'].filter((name) => name.includes(playState.filterText));
    const metricSet = item.variant === 'finance' || item.variant === 'ledger' ? [['دریافت ماه', '۱۲۵ م.ت'], ['مانده مطالبات', '۴۲ م.ت'], ['تراکنش‌ها', '۱٬۲۴۰']] : [['قرارداد فعال', '۲۴۸'], ['اقساط امروز', '۳۶'], ['پیگیری باز', '۱۲']];
    const content = playState.viewMode === 'summary'
      ? '<div class="ppds-template-lines"><i></i><i></i><i></i></div>'
      : playState.viewMode === 'cards'
        ? `<div class="ppds-template-metrics">${rows.map((name, index) => `<div class="ppds-template-metric"><small>مشتری نمونه</small><strong>${escapeHtml(name)}</strong><span class="ppds-form-help">PR-1405-00${1204 - index}</span></div>`).join('')}</div>`
        : `<div class="ppds-sample-table-wrap"><table class="ppds-sample-table"><thead><tr><th>عنوان</th><th>وضعیت</th><th>شناسه</th></tr></thead><tbody>${rows.map((name, index) => `<tr><td>${escapeHtml(name)}</td><td>${index === 1 ? 'پرداخت‌شده' : 'در انتظار'}</td><td dir="ltr">PR-1405-00${1204 - index}</td></tr>`).join('')}</tbody></table></div>`;
    return `<div class="ppds-template-demo"><header class="ppds-template-header"><div><strong>${escapeHtml(item.name)}</strong><br><small>صفحه نمایشی · بدون داده سامانه</small></div><button class="ppds-secondary-button" type="button" data-demo-action><i data-feather="download"></i> خروجی</button></header><div class="ppds-template-metrics">${metricSet.map((metric) => `<div class="ppds-template-metric"><small>${escapeHtml(metric[0])}</small><strong>${escapeHtml(metric[1])}</strong></div>`).join('')}</div>${content}</div>`;
  }

  function renderColors() {
    return `<div class="ppds-token-grid">${colorTokens.map(([token, label]) => {
      const value = getToken(token) || '—';
      const hex = toHexColor(value);
      const rgb = toRgbColor(value);
      return `<article class="ppds-token-card"><div class="ppds-token-swatch" style="--token-color:${escapeHtml(value)}"></div><div class="ppds-token-meta"><button class="ppds-copy-token" type="button" data-copy-value="${escapeHtml(token)}" data-copy-label="نام token"><strong>${escapeHtml(token)}</strong><i data-feather="copy" aria-hidden="true"></i></button><small>${escapeHtml(label)}</small><div class="ppds-color-values"><button type="button" data-copy-value="${escapeHtml(hex)}" data-copy-label="HEX">HEX <span>${escapeHtml(hex)}</span></button><button type="button" data-copy-value="${escapeHtml(rgb)}" data-copy-label="RGB">RGB <span>${escapeHtml(rgb)}</span></button></div></div></article>`;
    }).join('')}</div>`;
  }

  function toHexColor(value) {
    const input = String(value || '').trim();
    const short = input.match(/^#([0-9a-f]{3})$/i);
    if (short) return '#' + short[1].split('').map((character) => character + character).join('').toUpperCase();
    const full = input.match(/^#([0-9a-f]{6})$/i);
    if (full) return '#' + full[1].toUpperCase();
    const rgb = input.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (!rgb) return input;
    return '#' + rgb.slice(1, 4).map((channel) => Number(channel).toString(16).padStart(2, '0')).join('').toUpperCase();
  }

  function toRgbColor(value) {
    const input = String(value || '').trim();
    const hex = toHexColor(input);
    const match = hex.match(/^#([0-9a-f]{6})$/i);
    if (match) {
      const rgb = match[1].match(/.{2}/g).map((channel) => parseInt(channel, 16));
      return `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`;
    }
    const rgb = input.match(/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+(?:\s*,\s*[\d.]+)?\s*\)$/i);
    return rgb ? input : '—';
  }

  function renderTypography() {
    const custom = `<article class="ppds-type-row"><small>Custom preview</small><span style="font-size:${Number(playState.fontSize)}px;font-weight:${Number(playState.fontWeight)}">${escapeHtml(playState.label || 'نمونه متن فارسی پروماپی')}</span></article>`;
    const rows = typographyRows.map(([label, size, weight]) => `<article class="ppds-type-row"><small>${escapeHtml(label)} · ${size}px / ${weight}</small><span style="font-size:${size}px;font-weight:${weight}${label === 'Financial numbers' ? ';font-variant-numeric:tabular-nums' : ''}">${label === 'Financial numbers' ? '۱۲٬۵۰۰٬۰۰۰ تومان' : 'نمونه متن فارسی پروماپی'}</span></article>`).join('');
    return `<div class="ppds-typography-sample">${custom}${rows}</div>`;
  }

  function renderSpacing() {
    return `<div class="ppds-scale-grid">${spacingTokens.map(([token, label]) => {
      const value = getToken(token) || '0px';
      return `<div class="ppds-scale-token"><span>${escapeHtml(label)}</span><i class="ppds-spacing-bar" style="--space-size:${escapeHtml(value)}"></i><small>${escapeHtml(token)} · ${escapeHtml(value)}</small></div>`;
    }).join('')}</div>`;
  }

  function renderRadius() {
    return `<div class="ppds-scale-grid">${radiusTokens.map(([token, label]) => {
      const value = getToken(token) || '0px';
      return `<div class="ppds-scale-token"><i class="ppds-radius-box" style="border-radius:${escapeHtml(value)}"></i><span>${escapeHtml(label)}</span><small>${escapeHtml(token)} · ${escapeHtml(value)}</small></div>`;
    }).join('')}</div>`;
  }

  function renderShadows() {
    return `<div class="ppds-scale-grid">${shadowTokens.map(([token, label]) => {
      const value = getToken(token) || 'none';
      return `<div class="ppds-scale-token"><i class="ppds-shadow-box" style="box-shadow:${escapeHtml(value)}"></i><span>${escapeHtml(label)}</span><small>${escapeHtml(token)}</small></div>`;
    }).join('')}</div>`;
  }

  function renderBreakpoints() {
    const points = [[360, 'Small mobile'], [375, 'Mobile'], [390, 'Mobile'], [430, 'Large mobile'], [768, 'Tablet'], [820, 'Tablet'], [1024, 'Laptop'], [1280, 'Desktop'], [1440, 'Wide'], [1920, 'Large']];
    return `<div class="ppds-breakpoint-sample">${points.map(([size, label]) => `<div class="ppds-breakpoint-row"><span>${escapeHtml(label)}</span><div class="ppds-breakpoint-track"><i style="--bp-width:${Math.max(8, Math.min(100, size / 20))}%"></i></div><strong dir="ltr">${size}px</strong></div>`).join('')}</div>`;
  }

  function iconMarkup(set, name) {
    if (set === 'fontawesome') return `<i class="fa fa-${escapeHtml(name)}" aria-hidden="true"></i>`;
    if (set === 'themify') return `<i class="ti ti-${escapeHtml(name)}" aria-hidden="true"></i>`;
    if (set === 'proma') return catalog.promaSvg && catalog.promaSvg[name] ? catalog.promaSvg[name] : renderFeather(name);
    return renderFeather(name);
  }

  function renderFeather(name) {
    const requestedName = /^[a-z0-9-]+$/i.test(String(name || '')) ? String(name) : 'circle';
    const safeName = window.feather?.icons?.[requestedName] ? requestedName : 'circle';
    return `<i data-feather="${safeName}" aria-hidden="true"></i>`;
  }

  function renderIconGallery() {
    const icons = catalog.icons[playState.iconSet] || [];
    const query = String(playState.iconSearch || '').toLowerCase().trim();
    const filtered = query ? icons.filter((icon) => icon.toLowerCase().includes(query)) : icons;
    return `<div class="ppds-icon-gallery"><div class="ppds-icon-tools"><span class="ppds-form-help">جست‌وجو و انتخاب مجموعه در پنل تنظیم‌ها انجام می‌شود · ${filtered.length} آیکون</span></div><div class="ppds-icon-grid" aria-label="نتایج آیکون">${filtered.map((icon) => `<button class="ppds-icon-item" type="button" data-icon-name="${escapeHtml(icon)}" title="کپی ${escapeHtml(icon)}">${iconMarkup(playState.iconSet, icon)}<small>${escapeHtml(icon)}</small></button>`).join('')}</div></div>`;
  }

  function highlightCode(source, language) {
    const matcher = /(<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|\/\/[^\n]*|<\/?[A-Za-z][^>]*>|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`(?:\\.|[^`\\])*`|\b(?:const|let|var|function|return|export|import|from|type|interface|extends|default|if|else|new|async|await|echo|class|public|private|readonly|true|false|null|Props)\b|\b\d+(?:\.\d+)?\b|[a-zA-Z-]+(?=\s*:))/g;
    const lines = String(source).split('\n');
    return lines.map((line, index) => {
      let html = '';
      let cursor = 0;
      let match;
      matcher.lastIndex = 0;
      while ((match = matcher.exec(line))) {
        html += escapeHtml(line.slice(cursor, match.index));
        const token = match[0];
        let className = '';
        if (/^(<!--|\/\*|\/\/)/.test(token)) className = 'ppds-syntax-comment';
        else if (/^<\//.test(token) || /^</.test(token)) className = 'ppds-syntax-tag';
        else if (/^["'`]/.test(token)) className = 'ppds-syntax-string';
        else if (/^\d/.test(token)) className = 'ppds-syntax-number';
        else if (/^[a-zA-Z-]+$/.test(token) && /^(css|json)$/.test(language)) className = 'ppds-syntax-property';
        else className = 'ppds-syntax-keyword';
        html += `<span class="${className}">${escapeHtml(token)}</span>`;
        cursor = match.index + token.length;
      }
      html += escapeHtml(line.slice(cursor));
      return `<span class="ppds-code-line" data-line="${index + 1}">${html || ' '}</span>`;
    }).join('');
  }

  function snippetsFor(item) {
    const label = String(playState.label || item.name);
    const variant = String(playState.variant || item.variant || 'primary');
    const safeLabel = escapeHtml(label);
    const cssClass = `ppds-demo-button ppds-demo-button--${variant}`;
    let html;
    if (item.family === 'buttons') {
      const modifiers = `${playState.fullWidth ? ' ppds-demo-button--full' : ''}${playState.rounded ? ' ppds-demo-button--rounded' : ''}${playState.size === 'small' ? ' ppds-demo-button--small' : playState.size === 'large' ? ' ppds-demo-button--large' : ''}`;
      const iconMarkup = playState.iconEnabled ? `<i data-feather="${escapeHtml(playState.icon)}" aria-hidden="true"></i>` : '';
      html = `<button type="button" class="${cssClass}${modifiers}" data-example-action${playState.disabled ? ' disabled aria-disabled="true"' : ''}${playState.loading ? ' aria-busy="true"' : ''}>${playState.loading ? '<span class="ppds-spinner" aria-hidden="true"></span>' : ''}${iconMarkup}${safeLabel}</button>`;
    } else if (item.family === 'forms') {
      const inputType = ({ password: 'password', email: 'email', tel: 'tel', number: 'number', search: 'search' })[item.variant] || 'text';
      html = `<label for="${escapeHtml(item.id)}">${escapeHtml(item.name)}</label>\n<input id="${escapeHtml(item.id)}" name="${escapeHtml(item.id)}" type="${inputType}" value="${escapeHtml(playState.value)}"${playState.required ? ' required' : ''}${playState.disabled ? ' disabled' : ''}${playState.controlStatus === 'readonly' ? ' readonly' : ''}${playState.controlStatus === 'error' ? ' aria-invalid="true"' : ''}>`;
    } else if (['financial', 'installments', 'payments', 'accounting'].includes(item.family)) {
      html = `<output class="ppds-financial-amount" aria-label="${escapeHtml(item.name)}">${escapeHtml(playState.amount)} <span>${escapeHtml(playState.currency)}</span></output>`;
    } else if (item.family === 'templates') {
      html = `<main class="ppds-page-pattern" aria-labelledby="page-title"><h1 id="page-title">${escapeHtml(item.name)}</h1><p>نمونه داده نمایشی</p><button type="button" class="ppds-demo-button" data-example-action>مشاهده نمونه</button></main>`;
    } else {
      html = `<section class="ppds-example" aria-labelledby="example-title"><h2 id="example-title">${escapeHtml(item.name)}</h2><button type="button" class="ppds-demo-button ppds-demo-button--primary" data-example-action>نمایش نمونه</button></section>`;
    }
    const css = `.ppds-demo-button {\n  display: inline-flex;\n  align-items: center;\n  gap: 8px;\n  min-height: 44px;\n  padding: 8px 14px;\n  border-radius: var(--pp-radius-sm);\n  font-family: "Yekan Bakh", sans-serif;\n}\n\n.ppds-demo-button--${variant} {\n  color: #fff;\n  background: var(--pp-primary-600);\n}`;
    const javascript = `const demo = document.querySelector('[data-example-action]');\n\ndemo?.addEventListener('click', (event) => {\n  event.currentTarget.setAttribute('aria-pressed', 'true');\n  console.info('Interactive example activated');\n});`;
    const php = item.family === 'forms'
      ? `<?php\n$value = (string) ($value ?? '');\n?>\n<label for="${item.id}">${item.name}</label>\n<input id="${item.id}" name="${item.id}" type="${item.variant === 'email' ? 'email' : 'text'}" value="<?= e($value) ?>"${playState.required ? ' required' : ''}>`
      : item.family === 'financial' || item.family === 'installments' || item.family === 'payments' || item.family === 'accounting'
        ? `<?php\n// The server provides the authoritative financial amount.\n$amount = $amount ?? '${label.replace(/'/g, "\\'")}';\n?>\n<output class="ppds-financial-amount"><?= e($amount) ?> <span>${playState.currency}</span></output>`
        : `<?php\n$label = (string) ($label ?? '${label.replace(/'/g, "\\'")}');\n?>\n<button type="button" class="ppds-demo-button ppds-demo-button--${variant}"><?= e($label) ?></button>`;
    const typescript = `export type Variant = 'primary' | 'secondary' | 'outline' | 'danger';\n\nexport interface PromaExampleProps {\n  label: string;\n  variant?: Variant;\n  disabled?: boolean;\n  onActivate?: () => void;\n}\n\nexport function getExampleClass(variant: Variant = '${variant}'): string {\n  return \`ppds-demo-button ppds-demo-button--\${variant}\`;\n}`;
    const tsx = `import type { ButtonHTMLAttributes } from 'react';\n\ntype Props = ButtonHTMLAttributes<HTMLButtonElement> & {\n  variant?: 'primary' | 'secondary' | 'outline';\n};\n\nexport function PromaButton({ variant = '${variant}', children = '${label}', ...props }: Props) {\n  return (\n    <button className={\`ppds-demo-button ppds-demo-button--\${variant}\`} {...props}>\n      {children}\n    </button>\n  );\n}`;
    const json = JSON.stringify({ component: item.id, family: item.family, props: { variant, label, disabled: Boolean(playState.disabled), loading: Boolean(playState.loading), icon: playState.iconEnabled ? playState.icon : null, fullWidth: Boolean(playState.fullWidth), rounded: Boolean(playState.rounded), value: playState.value, amount: playState.amount, currency: playState.currency, selectedDate: playState.selectedDate }, source: 'local-example-only' }, null, 2);
    return { html, css, javascript, php, typescript, tsx, json };
  }

  function updateCode() {
    const language = elements.languageSelect ? elements.languageSelect.value : 'html';
    const source = snippetsFor(current)[language] || '';
    currentCode = source;
    if (elements.code) elements.code.innerHTML = highlightCode(source, language);
    if (elements.languagePurpose) {
      const languageRecord = (catalog.languages || []).find((item) => item.id === language);
      elements.languagePurpose.textContent = languageRecord ? languageRecord.purpose : '';
    }
  }

  function updateActiveNav() {
    $$('.ppds-nav-item').forEach((button) => {
      const active = button.dataset.navItem === current.id;
      button.setAttribute('aria-current', active ? 'page' : 'false');
      if (active) button.scrollIntoView({ block: 'nearest' });
    });
    const target = $(`[data-nav-group-wrap="${CSS.escape(current.group)}"]`);
    if (target) target.classList.remove('is-collapsed');
    const rootId = categoryFor(current).id;
    const rootNav = $(`[data-nav-root="${rootId}"]`);
    if (rootNav) rootNav.closest('.ppds-nav-section')?.classList.remove('is-collapsed');
  }

  function pushRecent(id) {
    try {
      const key = 'proma-design-system-recent-v1';
      const prior = JSON.parse(localStorage.getItem(key) || '[]');
      const next = [id, ...prior.filter((value) => value !== id)].slice(0, 6);
      localStorage.setItem(key, JSON.stringify(next));
    } catch (error) { /* private browsing can disable local storage */ }
    renderRecent();
  }

  function renderRecent() {
    if (!elements.recent) return;
    let ids = [];
    try { ids = JSON.parse(localStorage.getItem('proma-design-system-recent-v1') || '[]'); }
    catch (error) { ids = []; }
    const items = ids.map((id) => itemById.get(id)).filter(Boolean);
    elements.recent.innerHTML = items.length
      ? `<div class="ppds-recent-items">${items.map((item) => `<button type="button" class="ppds-recent-item" data-nav-item="${escapeHtml(item.id)}"><span>${escapeHtml(item.name)}</span><small>${escapeHtml(item.english)}</small></button>`).join('')}</div>`
      : '<p class="ppds-muted-small">کامپوننتی انتخاب نشده است.</p>';
  }

  function showToast(message, tone = 'success') {
    if (!elements.toastRegion) return;
    const toast = document.createElement('div');
    toast.className = 'ppds-toast';
    toast.dataset.tone = tone;
    const icon = tone === 'danger' ? 'alert-circle' : tone === 'warning' ? 'alert-triangle' : 'check-circle';
    toast.innerHTML = `${renderFeather(icon)}<span>${escapeHtml(message)}</span>`;
    elements.toastRegion.appendChild(toast);
    featherReplace();
    window.setTimeout(() => toast.remove(), 3600);
  }

  async function copyText(text, label = 'نمونه') {
    try {
      if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
      else {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Clipboard copy was not available');
      }
      showToast(`${label} کپی شد.`);
      return true;
    } catch (error) {
      showToast('دسترسی به کلیپ‌بورد ممکن نشد؛ متن را انتخاب و کپی کنید.', 'warning');
      return false;
    }
  }

  function findMatches(query) {
    const needle = String(query || '').trim().toLocaleLowerCase('fa');
    if (!needle) return [];
    return allItems.map((item) => {
      const group = groupById.get(item.group);
      const haystack = [item.name, item.english, item.id, ...(item.aliases || []), group && group.label, group && group.english].filter(Boolean).join(' ').toLocaleLowerCase('fa');
      const index = haystack.indexOf(needle);
      return { item, index };
    }).filter((match) => match.index >= 0).sort((a, b) => a.index - b.index || a.item.name.localeCompare(b.item.name, 'fa')).slice(0, 12).map((match) => match.item);
  }

  function renderSearchResults(query) {
    const results = findMatches(query);
    searchActiveIndex = results.length ? 0 : -1;
    if (!elements.searchResults) return results;
    elements.searchResults.hidden = !query;
    elements.search?.setAttribute('aria-expanded', String(Boolean(query)));
    elements.searchResults.innerHTML = results.length
      ? results.map((item, index) => `<button class="ppds-search-result" type="button" role="option" id="ppds-search-option-${index}" aria-selected="${index === searchActiveIndex}" data-nav-item="${escapeHtml(item.id)}"><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.english)}</small></span><em>${escapeHtml(categoryFor(item).label)}</em></button>`).join('')
      : '<p class="ppds-search-empty">کامپوننتی پیدا نشد.</p>';
    return results;
  }

  function closeSearch() {
    if (elements.searchResults) elements.searchResults.hidden = true;
    elements.search?.setAttribute('aria-expanded', 'false');
    searchActiveIndex = -1;
  }

  function applyControl(id, value, type) {
    if (!current) return;
    playState[id] = type === 'checkbox' ? Boolean(value) : value;
    if (id === 'fontSize') playState.fontSize = Math.max(12, Math.min(48, Number(value) || 18));
    if (id === 'fontWeight') playState.fontWeight = Number(value) || 600;
    updatePreviewAndCode();
  }

  function openModal(trigger) {
    if (!elements.modalBackdrop) return;
    modalTrigger = trigger || document.activeElement;
    focusableBeforeModal = $$('.ppds-modal button, .ppds-modal input, .ppds-modal select, .ppds-modal textarea');
    const variant = trigger?.dataset.modalVariant || 'modal';
    if (elements.modalTitle) elements.modalTitle.textContent = trigger?.dataset.modalTitle || 'پیش‌نمایش تعاملی';
    if (elements.modalBody) elements.modalBody.innerHTML = variant === 'danger'
      ? '<p>این اقدام فقط برای نمایش dialog است و هیچ عملیات واقعی انجام نمی‌دهد.</p><p class="ppds-form-error">تأیید نمونه باعث تغییر داده نمی‌شود.</p>'
      : '<p>این پنجره فقط بخشی از نمونه تعاملی است. هیچ اطلاعاتی ثبت یا ارسال نمی‌شود.</p>';
    elements.modalBackdrop.hidden = false;
    root.classList.add('ppds-scroll-locked');
    window.setTimeout(() => $('[data-modal-close]', elements.modalBackdrop)?.focus(), 0);
  }

  function closeModal() {
    if (!elements.modalBackdrop || elements.modalBackdrop.hidden) return;
    elements.modalBackdrop.hidden = true;
    root.classList.remove('ppds-scroll-locked');
    if (modalTrigger && typeof modalTrigger.focus === 'function') modalTrigger.focus();
    modalTrigger = null;
  }

  function openDrawers(which) {
    const mobile = window.matchMedia('(max-width: 820px)').matches;
    if (!mobile) return;
    elements.sidebar?.classList.toggle('is-open', which === 'sidebar');
    elements.rightPanel?.classList.toggle('is-open', which === 'right');
    if (elements.backdrop) elements.backdrop.hidden = !which;
    root.classList.toggle('ppds-scroll-locked', Boolean(which));
    $$('[data-open-sidebar]').forEach((button) => button.setAttribute('aria-expanded', String(which === 'sidebar')));
    $$('[data-open-right-panel]').forEach((button) => button.setAttribute('aria-expanded', String(which === 'right')));
  }

  function closeDrawers() {
    elements.sidebar?.classList.remove('is-open');
    elements.rightPanel?.classList.remove('is-open');
    if (elements.backdrop) elements.backdrop.hidden = true;
    root.classList.remove('ppds-scroll-locked');
    $$('[data-open-sidebar], [data-open-right-panel]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
  }

  function setTheme(dark) {
    const value = dark ? 'dark' : 'light';
    document.documentElement.dataset.theme = value;
    document.body.dataset.theme = value;
    root.classList.toggle('dark-only', dark);
    $$('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(dark));
      button.setAttribute('aria-label', dark ? 'تغییر به حالت روشن' : 'تغییر به حالت تاریک');
      button.innerHTML = renderFeather(dark ? 'sun' : 'moon');
    });
    try { localStorage.setItem('proma-design-system-theme', value); } catch (error) { /* ignore */ }
    featherReplace();
  }

  function changeCalendarMonth(offset) {
    const visible = parseLocalDate(playState.calendarViewDate || playState.selectedDate);
    visible.setUTCDate(visible.getUTCDate() + (Number(offset) < 0 ? -35 : 35));
    playState.calendarViewDate = localDateString(startOfJalaliMonth(visible));
    updatePreviewAndCode();
  }

  function featherReplace() {
    if (window.feather && typeof window.feather.replace === 'function') window.feather.replace({ 'stroke-width': 1.8 });
  }

  function downloadText(filename, text, mime = 'text/plain;charset=utf-8') {
    const blob = new Blob([text], { type: mime });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function setSidebarFilter(value) {
    const query = String(value || '').toLocaleLowerCase('fa').trim();
    $$('.ppds-nav-section').forEach((section) => {
      const children = $$('.ppds-nav-item', section);
      let count = 0;
      children.forEach((button) => {
        const visible = !query || button.textContent.toLocaleLowerCase('fa').includes(query) || (itemById.get(button.dataset.navItem)?.id || '').includes(query);
        button.closest('.ppds-nav-leaf, .ppds-nav-group--flat')?.classList.toggle('ppds-filter-hidden', !visible);
        if (visible) count += 1;
      });
      $$('.ppds-nav-group', section).forEach((group) => {
        const visibleItems = $$('.ppds-nav-leaf:not(.ppds-filter-hidden)', group).length;
        group.classList.toggle('ppds-filter-hidden', Boolean(query) && visibleItems === 0);
        if (query && visibleItems) group.classList.remove('is-collapsed');
      });
      section.classList.toggle('ppds-filter-hidden', Boolean(query) && count === 0);
      if (query && count) section.classList.remove('is-collapsed');
    });
  }

  function updateBulkCount() {
    const selected = $$('.ppds-bulk-row:checked').length;
    const button = $('[data-bulk-action]', elements.preview);
    if (button) button.textContent = `اقدام گروهی روی ${new Intl.NumberFormat('fa-IR').format(selected)} مورد`;
    const selectAll = $('[data-select-all]', elements.preview);
    const all = $$('.ppds-bulk-row', elements.preview);
    if (selectAll && all.length) selectAll.checked = selected === all.length;
  }

  document.addEventListener('click', (event) => {
    const navItem = event.target.closest('[data-nav-item]');
    if (navItem) {
      setCurrent(navItem.dataset.navItem);
      closeSearch();
      return;
    }
    const related = event.target.closest('[data-related-item]');
    if (related) { setCurrent(related.dataset.relatedItem); return; }
    const groupButton = event.target.closest('[data-nav-group]');
    if (groupButton) {
      const group = groupButton.closest('.ppds-nav-group');
      const expanded = groupButton.getAttribute('aria-expanded') === 'true';
      groupButton.setAttribute('aria-expanded', String(!expanded));
      group?.classList.toggle('is-collapsed', expanded);
      return;
    }
    const rootButton = event.target.closest('[data-nav-root]');
    if (rootButton) {
      const section = rootButton.closest('.ppds-nav-section');
      const expanded = rootButton.getAttribute('aria-expanded') === 'true';
      rootButton.setAttribute('aria-expanded', String(!expanded));
      section?.classList.toggle('is-collapsed', expanded);
      return;
    }
    if (event.target.closest('[data-open-sidebar]')) { openDrawers('sidebar'); return; }
    if (event.target.closest('[data-open-right-panel]')) { openDrawers('right'); return; }
    if (event.target.closest('[data-close-drawers]') || event.target.matches('[data-drawer-backdrop]')) { closeDrawers(); return; }
    if (event.target.closest('[data-theme-toggle]')) { setTheme(document.documentElement.dataset.theme !== 'dark'); return; }
    if (event.target.closest('[data-explore-components]')) { $('#ppds-documentation')?.scrollIntoView({ behavior: 'smooth' }); setCurrent('button-primary'); return; }
    if (event.target.closest('[data-explore-playground]')) { $('#live-preview')?.scrollIntoView({ behavior: 'smooth' }); $('[data-controls] input, [data-controls] select')?.focus({ preventScroll: true }); return; }
    if (event.target.closest('[data-explore-code]')) { $('#code-examples')?.scrollIntoView({ behavior: 'smooth' }); elements.languageSelect?.focus({ preventScroll: true }); return; }
    if (event.target.closest('[data-reset-playground]')) { playState = defaultState(current); renderControls(); updatePreviewAndCode(); showToast('تنظیم‌های نمونه بازنشانی شد.'); return; }
    if (event.target.closest('[data-copy-code]')) { copyText(currentCode, 'کد'); return; }
    const copyValue = event.target.closest('[data-copy-value]');
    if (copyValue) { copyText(copyValue.dataset.copyValue || '', copyValue.dataset.copyLabel || 'Token'); return; }
    if (event.target.closest('[data-download-code]')) { downloadText(`${current.id}.${elements.languageSelect.value === 'tsx' ? 'tsx' : elements.languageSelect.value}`, currentCode); showToast('فایل نمونه آماده دریافت است.'); return; }
    if (event.target.closest('[data-expand-code]')) {
      const expanded = elements.codeCard.classList.toggle('is-expanded');
      root.classList.toggle('ppds-code-expanded', expanded);
      event.target.closest('[data-expand-code]').setAttribute('aria-expanded', String(expanded));
      event.target.closest('[data-expand-code]').innerHTML = `${renderFeather(expanded ? 'minimize-2' : 'maximize-2')}<span>${expanded ? 'بستن' : 'گسترش'}</span>`;
      featherReplace();
      return;
    }
    const openDemo = event.target.closest('[data-open-demo]');
    if (openDemo) { openModal(openDemo); return; }
    if (event.target.closest('[data-modal-close]') || event.target.matches('[data-demo-modal]')) { closeModal(); return; }
    if (event.target.closest('[data-modal-confirm]')) { closeModal(); showToast('اقدام نمایشی تأیید شد؛ داده‌ای تغییر نکرد.'); return; }
    const dateButton = event.target.closest('[data-calendar-date]');
    if (dateButton) { playState.selectedDate = dateButton.dataset.calendarDate; playState.calendarViewDate = playState.selectedDate; updatePreviewAndCode(); return; }
    const calendarShift = event.target.closest('[data-calendar-shift]');
    if (calendarShift) { changeCalendarMonth(calendarShift.dataset.calendarShift); return; }
    const dateInput = event.target.closest('[data-date-input]');
    if (dateInput) { playState.selectedDate = dateInput.value; updatePreviewAndCode(); return; }
    const searchOption = event.target.closest('[data-search-option]');
    if (searchOption) { playState.value = searchOption.dataset.searchOption; playState.filterText = ''; updatePreviewAndCode(); showToast(`مقدار «${playState.value}» در نمونه انتخاب شد.`); return; }
    const demoTab = event.target.closest('[data-demo-tab]');
    if (demoTab) { playState.selectedTab = Number(demoTab.dataset.demoTab) || 0; updatePreviewAndCode(); return; }
    const pageStep = event.target.closest('[data-page-step]');
    if (pageStep) { playState.page = Math.max(1, Math.min(12, playState.page + Number(pageStep.dataset.pageStep))); updatePreviewAndCode(); return; }
    const toastButton = event.target.closest('[data-demo-toast]');
    if (toastButton) { showToast('پیام نمونه در سیستم نمایش داده شد.', toastButton.dataset.demoToast); return; }
    if (event.target.closest('[data-bulk-action]')) { const count = $$('.ppds-bulk-row:checked', elements.preview).length; showToast(`اقدام نمونه روی ${new Intl.NumberFormat('fa-IR').format(count)} مورد انجام شد.`); return; }
    if (event.target.closest('[data-demo-dropdown]')) { showToast('گزینه‌های بیشتر نمونه باز شدند.'); return; }
    if (event.target.closest('[data-format-command]')) { showToast('قالب متن در پیش‌نمایش تغییر کرد.'); return; }
    if (event.target.closest('[data-demo-action]')) { showToast('رویداد نمونه اجرا شد؛ داده‌ای ارسال نشد.'); return; }
    const iconButton = event.target.closest('[data-icon-name]');
    if (iconButton) {
      const iconName = iconButton.dataset.iconName;
      const code = playState.iconSet === 'fontawesome' ? `<i class="fa fa-${iconName}" aria-hidden="true"></i>` : playState.iconSet === 'themify' ? `<i class="ti ti-${iconName}" aria-hidden="true"></i>` : playState.iconSet === 'proma' ? `proma_icon('${iconName}')` : `<i data-feather="${iconName}" aria-hidden="true"></i>`;
      copyText(code, 'کد آیکون');
      return;
    }
    if (event.target.closest('[data-sort-table]')) { showToast('ردیف‌های جدول نمونه مرتب شدند.'); return; }
    if (event.target.closest('[data-demo-filter]')) { showToast('فیلتر محلی اعمال شد.'); return; }
    if (event.target.closest('[data-clear-recent]')) { try { localStorage.removeItem('proma-design-system-recent-v1'); } catch (error) { /* ignore */ } renderRecent(); return; }
    const nextStep = event.target.closest('[data-step-next]');
    if (nextStep) { playState.selectedTab = Math.min(2, playState.selectedTab + 1); updatePreviewAndCode(); showToast('گام نمونه تغییر کرد.'); return; }
    const previousStep = event.target.closest('[data-step-prev]');
    if (previousStep) { playState.selectedTab = Math.max(0, playState.selectedTab - 1); updatePreviewAndCode(); showToast('گام نمونه تغییر کرد.'); return; }
    const exportButton = event.target.closest('[data-export]');
    if (exportButton) {
      const content = exportButton.dataset.export === 'json' ? JSON.stringify({ sample: true, customers: ['نگار احمدی', 'علی رضایی'] }, null, 2) : 'name,status\nنگار احمدی,در انتظار\nعلی رضایی,پرداخت‌شده';
      downloadText(`proma-sample.${exportButton.dataset.export}`, content, 'text/plain;charset=utf-8');
      showToast('فایل نمونه محلی آماده دریافت است.');
      return;
    }
  });

  document.addEventListener('input', (event) => {
    const control = event.target.closest('[data-control]');
    if (control) { applyControl(control.dataset.control, control.type === 'checkbox' ? control.checked : control.value, control.type); return; }
    if (event.target.matches('[data-icon-search]')) { playState.iconSearch = event.target.value; updatePreviewAndCode(); return; }
    if (event.target.matches('[data-search-select]')) {
      playState.filterText = event.target.value;
      const query = playState.filterText.toLocaleLowerCase('fa');
      const options = ['قسط ماهانه', 'پرداخت کارت‌به‌کارت', 'پرداخت درگاه'].filter((option) => option.toLocaleLowerCase('fa').includes(query));
      const list = $('[data-search-select-options]', elements.preview);
      if (list) list.innerHTML = options.map((option) => `<button type="button" role="option" aria-selected="${playState.value === option}" data-search-option="${escapeHtml(option)}">${escapeHtml(option)}</button>`).join('');
      return;
    }
    if (event.target.matches('[data-financial-input]')) {
      playState.amount = event.target.value;
      const output = $('[data-financial-output]', elements.preview);
      if (output) output.textContent = `پیش‌نمایش ${displayNumber(playState.amount)} ${playState.currency}`;
      updateCode();
      return;
    }
    if (event.target.matches('[data-component-search]')) { renderSearchResults(event.target.value); return; }
    if (event.target.matches('[data-sidebar-filter]')) { setSidebarFilter(event.target.value); return; }
    if (event.target.matches('#ppds-command-search')) {
      playState.filterText = event.target.value;
      const query = playState.filterText.toLocaleLowerCase('fa');
      $$('[data-command-option]', elements.preview).forEach((option) => { option.hidden = Boolean(query) && !option.textContent.toLocaleLowerCase('fa').includes(query); });
      return;
    }
    if (event.target.matches('[data-select-all]')) { $$('.ppds-bulk-row', elements.preview).forEach((checkbox) => { checkbox.checked = event.target.checked; }); updateBulkCount(); return; }
    if (event.target.matches('.ppds-bulk-row')) { updateBulkCount(); return; }
    if (event.target.matches('[data-template-filter]')) { playState.filterText = event.target.value; updatePreviewAndCode(); }
  });

  document.addEventListener('change', (event) => {
    const control = event.target.closest('[data-control]');
    if (control && control.tagName === 'SELECT') {
      applyControl(control.dataset.control, control.value, control.type);
      if (control.dataset.control === 'iconSet') { playState.iconSearch = ''; renderControls(); updatePreviewAndCode(); }
      return;
    }
    if (event.target.matches('[data-icon-set]')) { playState.iconSet = event.target.value; updatePreviewAndCode(); return; }
    if (event.target.matches('[data-date-input]')) { playState.selectedDate = event.target.value; updatePreviewAndCode(); }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault(); elements.search?.focus(); elements.search?.select(); return;
    }
    if (event.key === 'Escape') {
      if (elements.modalBackdrop && !elements.modalBackdrop.hidden) { closeModal(); return; }
      closeDrawers(); closeSearch(); return;
    }
    if (event.target.matches('[data-component-search]') && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
      const options = $$('.ppds-search-result', elements.searchResults);
      if (!options.length) return;
      event.preventDefault();
      searchActiveIndex = (searchActiveIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
      options.forEach((option, index) => option.setAttribute('aria-selected', String(index === searchActiveIndex)));
      return;
    }
    if (event.target.matches('[data-component-search]') && event.key === 'Enter') {
      const option = $$('.ppds-search-result', elements.searchResults)[searchActiveIndex];
      if (option) { event.preventDefault(); setCurrent(option.dataset.navItem); closeSearch(); }
    }
    if (elements.modalBackdrop && !elements.modalBackdrop.hidden && event.key === 'Tab') {
      const focusable = $$('.ppds-modal button:not([disabled]), .ppds-modal input:not([disabled]), .ppds-modal select:not([disabled]), .ppds-modal textarea:not([disabled])', elements.modalBackdrop);
      if (!focusable.length) { event.preventDefault(); return; }
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    const otp = event.target.closest('.ppds-otp input');
    if (otp && event.key === 'Backspace' && !otp.value) otp.previousElementSibling?.focus();
  });

  document.addEventListener('focusin', (event) => {
    if (!elements.modalBackdrop || elements.modalBackdrop.hidden || event.target.closest('.ppds-modal')) return;
    $('[data-modal-close]', elements.modalBackdrop)?.focus();
  });

  document.addEventListener('dragstart', (event) => {
    const card = event.target.closest('[data-drag-card]');
    if (!card) return;
    event.dataTransfer?.setData('text/plain', card.dataset.dragCard);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
  });
  document.addEventListener('dragover', (event) => {
    if (event.target.closest('[data-drop-column]')) event.preventDefault();
  });
  document.addEventListener('drop', (event) => {
    const column = event.target.closest('[data-drop-column]');
    if (!column) return;
    event.preventDefault();
    const id = event.dataTransfer?.getData('text/plain');
    const card = id ? $(`[data-drag-card="${CSS.escape(id)}"]`) : null;
    if (card) { column.appendChild(card); showToast('کارت نمونه جابه‌جا شد.'); }
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-search-wrap]') && event.target !== elements.search) closeSearch();
  });

  window.addEventListener('resize', () => { if (!window.matchMedia('(max-width: 820px)').matches) closeDrawers(); });

  if (elements.languageSelect) {
    elements.languageSelect.value = 'html';
    elements.languageSelect.addEventListener('change', updateCode);
  }
  try {
    const savedTheme = localStorage.getItem('proma-design-system-theme');
    setTheme(savedTheme === 'dark');
  } catch (error) { setTheme(false); }
  renderRecent();
  renderCurrent();
  featherReplace();
}());
