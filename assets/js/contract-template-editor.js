(function () {
  'use strict';

  const SOURCE_MODE = 'source';
  const VISUAL_MODE = 'visual';

  const escapeHtml = function (value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character];
    });
  };

  const sourceToHtml = function (source) {
    const lines = String(source || '').replace(/\r\n?/g, '\n').split('\n');
    return lines.map(function (line) {
      return '<p>' + (line === '' ? '<br>' : escapeHtml(line)) + '</p>';
    }).join('');
  };

  const hasHtml = function (value) {
    return /<\/?[a-z][^>]*>/i.test(String(value || ''));
  };

  const closeModal = function (modal) {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.toggle('proma-modal-open', document.querySelectorAll('.modal.open').length > 0);
    document.documentElement.classList.toggle('proma-modal-open', document.querySelectorAll('.modal.open').length > 0);
    modal.dispatchEvent(new CustomEvent('proma:modal-closed'));
  };

  const openModal = function (modal, opener) {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('proma-modal-open');
    document.documentElement.classList.add('proma-modal-open');
    modal.dispatchEvent(new CustomEvent('proma:modal-opened'));
    window.requestAnimationFrame(function () {
      const target = modal.querySelector('[data-modal-autofocus], input:not([type="hidden"]):not([disabled]), button:not([disabled])');
      if (target) target.focus({ preventScroll: true });
    });
    if (opener) modal._promaTemplateOpener = opener;
  };

  const copyText = function (value, report) {
    const success = function () { report('متن قالب کپی شد.', 'success'); };
    const fallback = function () {
      const temp = document.createElement('textarea');
      temp.value = String(value || '');
      temp.setAttribute('readonly', 'readonly');
      temp.className = 'proma-template-copy-buffer';
      document.body.appendChild(temp);
      temp.select();
      let copied = false;
      try { copied = document.execCommand('copy'); } catch (error) {}
      if (copied) {
        temp.remove();
        success();
        return;
      }
      temp.removeAttribute('readonly');
      temp.focus();
      temp.select();
      report('کپی خودکار انجام نشد؛ متن برای کپی دستی انتخاب شده است.', 'warning');
    };
    if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      navigator.clipboard.writeText(String(value || '')).then(success).catch(fallback);
      return;
    }
    fallback();
  };

  document.addEventListener('DOMContentLoaded', function () {
    const workspace = document.querySelector('[data-contract-template-workspace]');
    const editor = document.querySelector('[data-contract-template-editor]');
    const form = document.querySelector('[data-contract-template-form]');
    if (!workspace || !editor || !form) return;

    const formatField = form.querySelector('[name="body_format"]');
    const sourceButton = workspace.querySelector('[data-template-mode="source"]');
    const visualButton = workspace.querySelector('[data-template-mode="simple"]');
    const counter = workspace.querySelector('[data-template-count]');
    const unsaved = form.querySelector('[data-unsaved-indicator]');
    const statusBox = workspace.querySelector('[data-template-editor-status]');
    const recoveryBox = workspace.querySelector('[data-template-editor-recovery]');
    const retryAssetsButton = workspace.querySelector('[data-template-retry-assets]');
    const findModal = document.getElementById('template-find-replace');
    const replaceModal = document.getElementById('template-replace-source');
    const conversionModal = document.getElementById('template-convert-visual');
    const conversionSourceField = form.querySelector('[name="editor_conversion_source"]');
    const recoveryKey = 'proma-template-recovery-v2:' + window.location.pathname + window.location.search;
    const originalSource = editor.value;
    const originalFormat = formatField ? formatField.value : 'plain_text_v1';
    let mode = SOURCE_MODE;
    let quill = null;
    let visualShell = null;
    let dirty = false;
    let pendingSource = null;
    let recoveryTimer = null;
    let assetLoadInFlight = false;

    const setLifecycleState = function (state) {
      workspace.dataset.templateEditorLifecycle = state;
    };

    const report = function (message, type) {
      if (statusBox) {
        statusBox.textContent = message;
        statusBox.dataset.state = type || 'info';
      }
      if (typeof window.showToast === 'function' && type && type !== 'info') {
        window.showToast(message, type);
      }
    };

    const updateCounter = function () {
      const value = currentValue();
      const plain = value.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      const words = plain === '' ? 0 : plain.split(' ').length;
      if (counter) {
        counter.textContent = words.toLocaleString('fa-IR') + ' واژه، ' + plain.length.toLocaleString('fa-IR') + ' نویسه';
      }
      if (unsaved) unsaved.hidden = !dirty;
    };

    const currentValue = function () {
      if (mode === VISUAL_MODE && quill) {
        const html = String(quill.root.innerHTML || '').trim();
        return html === '<p><br></p>' ? '' : html;
      }
      return editor.value || '';
    };

    const clearRecovery = function () {
      try { window.localStorage.removeItem(recoveryKey); } catch (error) {}
    };

    const persistRecovery = function () {
      window.clearTimeout(recoveryTimer);
      recoveryTimer = window.setTimeout(function () {
        const source = currentValue();
        if (source.length > 500000) return;
        try {
          window.localStorage.setItem(recoveryKey, JSON.stringify({
            source: source,
            format: formatField ? formatField.value : originalFormat,
            savedAt: Date.now()
          }));
        } catch (error) {}
      }, 350);
    };

    const markDirty = function () {
      dirty = true;
      workspace.dataset.templateEditorDirty = 'true';
      persistRecovery();
      updateCounter();
    };

    const setQuillHtml = function (value) {
      if (!quill) return;
      const html = String(value || '');
      if (typeof window.PromaQuillHtml === 'function') {
        window.PromaQuillHtml(quill, html);
        return;
      }
      if (quill.clipboard && typeof quill.clipboard.convert === 'function' && typeof quill.setContents === 'function') {
        quill.setContents(quill.clipboard.convert(html), 'silent');
        return;
      }
      quill.root.innerHTML = html;
      if (typeof quill.update === 'function') quill.update('silent');
    };

    const syncVisualToSource = function (changeFormat) {
      if (!quill) return;
      editor.value = currentValue();
      if (changeFormat && formatField) formatField.value = 'structured_html_v1';
    };

    const showMode = function (nextMode) {
      mode = nextMode;
      const visual = mode === VISUAL_MODE && !!quill;
      workspace.dataset.templateEditorState = visual ? VISUAL_MODE : SOURCE_MODE;
      setLifecycleState(visual ? 'visual_ready' : 'source_ready');
      editor.hidden = visual;
      if (visualShell) visualShell.hidden = !visual;
      if (sourceButton) {
        sourceButton.classList.toggle('active', !visual);
        sourceButton.setAttribute('aria-selected', visual ? 'false' : 'true');
      }
      if (visualButton) {
        visualButton.classList.toggle('active', visual);
        visualButton.setAttribute('aria-selected', visual ? 'true' : 'false');
      }
      updateCounter();
    };

    const setSource = function (source, format, changed) {
      editor.value = String(source || '');
      if (formatField && format) formatField.value = format;
      if (quill) {
        setQuillHtml(hasHtml(editor.value) ? editor.value : sourceToHtml(editor.value));
      }
      if (changed !== false) markDirty();
      updateCounter();
    };

    const initializeVisualEditor = function () {
      if (quill) return true;
      if (!window.Quill) return false;
      try {
        visualShell = document.createElement('div');
        visualShell.className = 'proma-rich-editor-shell proma-template-visual-editor';
        visualShell.id = 'editor-template-visual';
        visualShell.setAttribute('dir', 'rtl');
        visualShell.hidden = true;
        const target = document.createElement('div');
        target.className = 'proma-rich-editor';
        visualShell.appendChild(target);
        editor.parentNode.insertBefore(visualShell, editor);
        quill = new window.Quill(target, {
          theme: 'snow',
          placeholder: editor.getAttribute('placeholder') || 'متن قرارداد را وارد کنید...',
          modules: {
            toolbar: [
              [{ header: [1, 2, 3, 4, false] }, { size: ['small', false, 'large'] }],
              ['bold', 'italic', 'underline', 'strike'],
              [{ color: [] }, { background: [] }, { align: [] }, { direction: 'rtl' }],
              [{ list: 'ordered' }, { list: 'bullet' }],
              [{ indent: '-1' }, { indent: '+1' }],
              ['blockquote', 'link', 'clean']
            ]
          }
        });
        setQuillHtml(hasHtml(editor.value) ? editor.value : sourceToHtml(editor.value));
        quill.root.setAttribute('dir', 'rtl');
        quill.root.classList.add('proma-quill-rtl');
        try { quill.format('direction', 'rtl'); quill.format('align', 'right'); } catch (error) {}
        quill.on('text-change', function (delta, oldDelta, source) {
          if (mode !== VISUAL_MODE || source === 'silent') return;
          syncVisualToSource(true);
          markDirty();
        });
        if (visualButton) {
          visualButton.disabled = false;
          visualButton.setAttribute('aria-disabled', 'false');
          visualButton.removeAttribute('title');
        }
        if (retryAssetsButton) retryAssetsButton.hidden = true;
        report('ویرایش ساده آماده است. با انتخاب آن، قالب به HTML ساختاریافته امن تبدیل می‌شود.', 'success');
        return true;
      } catch (error) {
        if (visualShell) visualShell.remove();
        visualShell = null;
        quill = null;
        setLifecycleState('asset_error');
        if (retryAssetsButton) retryAssetsButton.hidden = false;
        report('ویرایش ساده آماده نشد؛ متن اصلی بدون تغییر در حالت کد قالب محفوظ است. دوباره تلاش کنید.', 'warning');
        return false;
      }
    };

    const loadVisualAssets = function (forceRetry) {
      if (initializeVisualEditor()) return;
      const source = workspace.getAttribute('data-quill-src') || '';
      const existing = document.querySelector('script[data-template-quill-retry]');
      if (forceRetry && existing) existing.remove();
      if (!source || assetLoadInFlight || (!forceRetry && document.querySelector('script[data-template-quill-retry]'))) {
        setLifecycleState('degraded_source_only');
        if (retryAssetsButton) retryAssetsButton.hidden = false;
        report('ابزار ویرایش ساده در دسترس نیست. حالت کد قالب بدون خطر قابل استفاده است.', 'warning');
        return;
      }
      assetLoadInFlight = true;
      const script = document.createElement('script');
      script.src = source;
      script.defer = true;
      script.setAttribute('data-template-quill-retry', '1');
      script.addEventListener('load', function () {
        assetLoadInFlight = false;
        if (!initializeVisualEditor()) {
          setLifecycleState('degraded_source_only');
          if (retryAssetsButton) retryAssetsButton.hidden = false;
          report('بارگذاری ابزار ویرایش کامل نشد. لطفاً دوباره تلاش کنید.', 'warning');
        }
      });
      script.addEventListener('error', function () {
        assetLoadInFlight = false;
        script.remove();
        setLifecycleState('asset_error');
        if (retryAssetsButton) retryAssetsButton.hidden = false;
        const requestId = workspace.getAttribute('data-request-id') || '';
        report('فایل ابزار ویرایش بارگذاری نشد. اتصال یا فایل‌های نصب را بررسی کنید.' + (requestId ? ' شناسه پیگیری: ' + requestId : ''), 'warning');
      });
      document.head.appendChild(script);
    };

    const insertText = function (value) {
      if (!value) return;
      if (mode === VISUAL_MODE && quill) {
        const range = quill.getSelection(true);
        const index = range ? range.index : Math.max(0, quill.getLength() - 1);
        quill.insertText(index, value, 'user');
        quill.setSelection(index + value.length, 0, 'silent');
        return;
      }
      const start = Number.isInteger(editor.selectionStart) ? editor.selectionStart : editor.value.length;
      const end = Number.isInteger(editor.selectionEnd) ? editor.selectionEnd : start;
      editor.value = editor.value.slice(0, start) + value + editor.value.slice(end);
      editor.focus();
      editor.setSelectionRange(start + value.length, start + value.length);
      markDirty();
    };

    document.querySelectorAll('[data-contract-copy-source], [data-contract-copy-textarea]').forEach(function (button) {
      button.addEventListener('click', function () {
        const id = button.getAttribute('data-contract-copy-source') || button.getAttribute('data-contract-copy-textarea');
        const source = id ? document.getElementById(id) : null;
        copyText(source ? source.value : currentValue(), report);
      });
    });

    document.querySelectorAll('[data-load-contract-source]').forEach(function (button) {
      button.addEventListener('click', function () {
        const source = document.getElementById(button.getAttribute('data-load-contract-source'));
        if (!source) return;
        pendingSource = source.value;
        if (dirty) {
          openModal(replaceModal, button);
          return;
        }
        setSource(pendingSource, hasHtml(pendingSource) ? 'structured_html_v1' : 'plain_text_v1');
        pendingSource = null;
      });
    });

    const applyPending = document.querySelector('[data-template-apply-pending-source]');
    if (applyPending) applyPending.addEventListener('click', function () {
      if (pendingSource !== null) {
        setSource(pendingSource, hasHtml(pendingSource) ? 'structured_html_v1' : 'plain_text_v1');
      }
      pendingSource = null;
      closeModal(replaceModal);
    });

    const activateVisualMode = function () {
      if (!initializeVisualEditor()) {
        loadVisualAssets();
        return;
      }
      // The source textarea remains authoritative while source mode is active.
      // Rehydrate Quill immediately before revealing it so edits cannot be
      // replaced by the stale visual document created during boot.
      if (mode === SOURCE_MODE) {
        setQuillHtml(hasHtml(editor.value) ? editor.value : sourceToHtml(editor.value));
      }
      showMode(VISUAL_MODE);
      syncVisualToSource(true);
      if (conversionSourceField) conversionSourceField.value = 'visual_editor';
      markDirty();
      report('ویرایش ساده فعال شد؛ قالب اکنون به HTML ساختاریافته امن تبدیل شده است.', 'info');
    };

    [sourceButton, visualButton].filter(Boolean).forEach(function (button) {
      button.addEventListener('click', function () {
        if (button.disabled) return;
        if (button.getAttribute('data-template-mode') === 'simple') {
          if (!initializeVisualEditor()) {
            loadVisualAssets();
            return;
          }
          if (formatField && formatField.value === 'plain_text_v1') {
            openModal(conversionModal, button);
            return;
          }
          activateVisualMode();
          return;
        }
        if (mode === VISUAL_MODE) syncVisualToSource(true);
        showMode(SOURCE_MODE);
        report('کد قالب فعال است؛ متن دقیق و قابل‌حفاظت نمایش داده می‌شود.', 'info');
      });
    });

    const confirmVisualConversion = document.querySelector('[data-template-confirm-visual-conversion]');
    if (confirmVisualConversion) confirmVisualConversion.addEventListener('click', function () {
      closeModal(conversionModal);
      activateVisualMode();
    });
    if (retryAssetsButton) retryAssetsButton.addEventListener('click', function () {
      retryAssetsButton.hidden = true;
      loadVisualAssets(true);
    });

    document.querySelectorAll('[data-insert-contract-variable]').forEach(function (button) {
      button.addEventListener('click', function () { insertText(button.getAttribute('data-insert-contract-variable') || ''); });
    });
    const variableSelect = workspace.querySelector('[data-contract-variable-select]');
    if (variableSelect) variableSelect.addEventListener('change', function () {
      insertText(variableSelect.value || '');
      variableSelect.value = '';
    });
    const important = workspace.querySelector('[data-insert-important-clause]');
    if (important) important.addEventListener('click', function () { insertText('**متن بند مهم**'); });

    const findForm = findModal ? findModal.querySelector('[data-template-find-form]') : null;
    if (findForm) findForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const find = String((findForm.elements.find || {}).value || '');
      const replace = String((findForm.elements.replace || {}).value || '');
      const value = currentValue();
      if (!find) return;
      if (value.indexOf(find) === -1) {
        report('عبارت مورد نظر پیدا نشد.', 'warning');
        return;
      }
      setSource(value.split(find).join(replace), formatField ? formatField.value : originalFormat);
      if (mode === VISUAL_MODE) setQuillHtml(editor.value);
      closeModal(findModal);
      report('جایگزینی انجام شد. برای ثبت نهایی، پیش‌نویس را ذخیره کنید.', 'success');
    });

    editor.addEventListener('input', function () {
      if (mode === SOURCE_MODE) markDirty();
    });
    form.addEventListener('submit', function () {
      if (mode === VISUAL_MODE) syncVisualToSource(true);
      setLifecycleState('saving');
      dirty = false;
      delete workspace.dataset.templateEditorDirty;
      clearRecovery();
      updateCounter();
    });

    const restore = workspace.querySelector('[data-template-restore-recovery]');
    const dismiss = workspace.querySelector('[data-template-dismiss-recovery]');
    let savedRecovery = null;
    try {
      const parsed = JSON.parse(window.localStorage.getItem(recoveryKey) || 'null');
      if (parsed && typeof parsed.source === 'string' && parsed.source !== originalSource) savedRecovery = parsed;
    } catch (error) {}
    if (savedRecovery && recoveryBox) recoveryBox.hidden = false;
    if (restore) restore.addEventListener('click', function () {
      if (!savedRecovery) return;
      setSource(savedRecovery.source, savedRecovery.format || originalFormat);
      if (recoveryBox) recoveryBox.hidden = true;
      report('نسخه ذخیره‌نشده محلی بازیابی شد.', 'success');
    });
    if (dismiss) dismiss.addEventListener('click', function () {
      clearRecovery();
      savedRecovery = null;
      if (recoveryBox) recoveryBox.hidden = true;
    });

    setLifecycleState('booting');
    showMode(SOURCE_MODE);
    updateCounter();
    if (!initializeVisualEditor()) loadVisualAssets();
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
  });
})();
