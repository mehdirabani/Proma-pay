(function () {
  'use strict';

  const toast = function (message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type || 'success');
      return;
    }
    window.alert(message);
  };

  const copyText = function (value) {
    const fallback = function () {
      const temp = document.createElement('textarea');
      temp.value = value;
      temp.setAttribute('readonly', 'readonly');
      temp.style.position = 'fixed';
      temp.style.opacity = '0';
      document.body.appendChild(temp);
      temp.select();
      let copied = false;
      try { copied = document.execCommand('copy'); } catch (error) {}
      if (!copied) {
        temp.style.opacity = '1';
        temp.style.inset = '20px';
        temp.removeAttribute('readonly');
        temp.focus();
        temp.select();
        toast('کپی خودکار انجام نشد؛ متن انتخاب شد و می‌توانید آن را به‌صورت دستی کپی کنید.', 'warning');
        return;
      }
      temp.remove();
      toast('متن قالب فعلی با موفقیت کپی شد.', 'success');
    };
    if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(function () {
        toast('متن قالب فعلی با موفقیت کپی شد.', 'success');
      }).catch(fallback);
    } else {
      fallback();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    const editor = document.querySelector('[data-contract-template-editor]');
    const form = document.querySelector('[data-contract-template-form]');
    let dirty = false;

    const currentValue = function () {
      if (!editor) return '';
      if (editor._promaQuill) {
        const html = editor._promaQuill.root.innerHTML.trim();
        return html === '<p><br></p>' ? '' : html;
      }
      return editor.value || '';
    };

    const setValue = function (value) {
      if (!editor) return;
      editor.value = value;
      if (editor._promaQuill) {
        if (/<[a-z][\s\S]*>/i.test(value)) {
          if (typeof window.PromaQuillHtml === 'function') {
            window.PromaQuillHtml(editor._promaQuill, value);
          } else if (typeof editor._promaQuill.pasteHTML === 'function') {
            editor._promaQuill.pasteHTML(value);
          }
        } else {
          editor._promaQuill.setText(value);
        }
      }
      dirty = true;
      updateStatus();
    };

    const updateStatus = function () {
      if (!editor) return;
      const plain = currentValue().replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      const words = plain === '' ? 0 : plain.split(' ').length;
      const counter = document.querySelector('[data-template-count]');
      if (counter) counter.textContent = words.toLocaleString('fa-IR') + ' واژه، ' + plain.length.toLocaleString('fa-IR') + ' نویسه';
      const indicator = document.querySelector('[data-unsaved-indicator]');
      if (indicator) indicator.hidden = !dirty;
      const format = form ? form.querySelector('[name="body_format"]') : null;
      if (format && editor && editor._promaQuill && editor.classList.contains('proma-rich-source') && dirty) {
        format.value = 'structured_html_v1';
      }
    };

    document.querySelectorAll('[data-contract-copy-source]').forEach(function (button) {
      button.addEventListener('click', function () {
        const source = document.getElementById(button.getAttribute('data-contract-copy-source'));
        copyText(source ? source.value : '');
      });
    });
    document.querySelectorAll('[data-contract-copy-textarea]').forEach(function (button) {
      button.addEventListener('click', function () {
        const source = document.getElementById(button.getAttribute('data-contract-copy-textarea'));
        copyText(source ? source.value : '');
      });
    });
    document.querySelectorAll('[data-load-contract-source]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (dirty && !window.confirm('تغییرات ذخیره‌نشده دارید. با بارگذاری قالب فعلی، متن ویرایشگر جایگزین می‌شود.')) return;
        const source = document.getElementById(button.getAttribute('data-load-contract-source'));
        if (source) setValue(source.value);
      });
    });
    document.querySelectorAll('[data-template-mode]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!editor) return;
        const sourceMode = button.getAttribute('data-template-mode') === 'source';
        const shell = editor.previousElementSibling && editor.previousElementSibling.classList.contains('proma-rich-editor-shell') ? editor.previousElementSibling : null;
        if (!sourceMode && editor._promaQuill && !editor.classList.contains('proma-rich-source')) {
          if (typeof window.PromaQuillHtml === 'function') {
            window.PromaQuillHtml(editor._promaQuill, editor.value);
          } else if (typeof editor._promaQuill.pasteHTML === 'function') {
            editor._promaQuill.pasteHTML(editor.value);
          }
        }
        if (shell) shell.hidden = sourceMode;
        editor.classList.toggle('proma-rich-source', !sourceMode);
        editor.style.display = sourceMode ? 'block' : 'none';
        document.querySelectorAll('[data-template-mode]').forEach(function (item) { item.classList.toggle('active', item === button); });
      });
    });
    document.querySelectorAll('[data-insert-contract-variable]').forEach(function (button) {
      button.addEventListener('click', function () {
        const code = button.getAttribute('data-insert-contract-variable') || '';
        if (!editor) {
          copyText(code);
          return;
        }
        if (editor._promaQuill) {
          const range = editor._promaQuill.getSelection(true);
          editor._promaQuill.insertText(range ? range.index : editor._promaQuill.getLength() - 1, code);
        } else {
          const start = editor.selectionStart || editor.value.length;
          editor.value = editor.value.slice(0, start) + code + editor.value.slice(start);
        }
        dirty = true;
        updateStatus();
      });
    });
    const importantButton = document.querySelector('[data-insert-important-clause]');
    if (importantButton && editor) importantButton.addEventListener('click', function () {
      const text = '**متن بند مهم**';
      if (editor._promaQuill) {
        const range = editor._promaQuill.getSelection(true);
        const index = range ? range.index : editor._promaQuill.getLength() - 1;
        editor._promaQuill.insertText(index, text);
        editor._promaQuill.setSelection(index + 2, text.length - 4);
      } else {
        const start = editor.selectionStart || editor.value.length;
        const end = editor.selectionEnd || start;
        editor.value = editor.value.slice(0, start) + text + editor.value.slice(end);
        editor.focus();
        editor.setSelectionRange(start + 2, start + text.length - 2);
      }
      dirty = true;
      updateStatus();
    });
    const variableSelect = document.querySelector('[data-contract-variable-select]');
    if (variableSelect) variableSelect.addEventListener('change', function () {
      const code = variableSelect.value;
      if (!code || !editor) return;
      if (editor._promaQuill) {
        const range = editor._promaQuill.getSelection(true);
        editor._promaQuill.insertText(range ? range.index : editor._promaQuill.getLength() - 1, code);
      } else {
        const start = editor.selectionStart || editor.value.length;
        editor.value = editor.value.slice(0, start) + code + editor.value.slice(start);
      }
      variableSelect.value = '';
      dirty = true;
      updateStatus();
    });
    const findButton = document.querySelector('[data-template-find]');
    if (findButton && editor) {
      findButton.addEventListener('click', function () {
        const find = window.prompt('عبارت مورد نظر برای یافتن:');
        if (!find) return;
        const replacement = window.prompt('عبارت جایگزین (برای فقط یافتن، انصراف را بزنید):');
        const value = currentValue();
        if (value.indexOf(find) === -1) {
          toast('عبارت مورد نظر پیدا نشد.', 'warning');
          return;
        }
        if (replacement !== null) setValue(value.split(find).join(replacement));
      });
    }
    if (editor) {
      editor.addEventListener('input', function () { dirty = true; updateStatus(); });
      setTimeout(updateStatus, 100);
    }
    if (form) form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });

    const previewFrame = document.querySelector('[data-contract-preview-frame]');
    const printInputs = document.querySelectorAll('[data-print-profile-input]');
    const updatePreview = function () {
      if (!previewFrame || !previewFrame.contentDocument) return;
      const root = previewFrame.contentDocument.documentElement;
      printInputs.forEach(function (input) {
        if (!input.name || input.type === 'checkbox' || ['preset', 'color_mode'].indexOf(input.name) !== -1) return;
        const unit = input.getAttribute('data-print-unit') || '';
        root.style.setProperty('--contract-' + input.name.replace(/_/g, '-'), input.value + unit);
      });
      if (typeof previewFrame.contentWindow.updateContractDiagnostics === 'function') {
        previewFrame.contentWindow.updateContractDiagnostics();
      }
    };
    if (previewFrame) previewFrame.addEventListener('load', updatePreview);
    printInputs.forEach(function (input) { input.addEventListener('input', updatePreview); input.addEventListener('change', updatePreview); });
    document.querySelectorAll('[data-preview-zoom]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!previewFrame) return;
        previewFrame.style.transform = 'scale(' + button.getAttribute('data-preview-zoom') + ')';
        document.querySelectorAll('[data-preview-zoom]').forEach(function (item) { item.classList.toggle('active', item === button); });
      });
    });
    const fit = document.querySelector('[data-preview-fit]');
    if (fit && previewFrame) fit.addEventListener('click', function () {
      const shell = previewFrame.parentElement;
      const scale = Math.min(1, (shell.clientWidth - 24) / 794);
      previewFrame.style.transform = 'scale(' + scale + ')';
    });
  });
})();
