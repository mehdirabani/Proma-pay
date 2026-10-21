<?php
$logoPath = trim((string) ($settings['contract_logo_path'] ?? $settings['logo_path'] ?? $settings['logo_icon_path'] ?? ''));
$logoText = trim((string) ($settings['company_name'] ?? $settings['system_name'] ?? 'پروما'));
?>
<!doctype html>
<html lang="fa" dir="rtl" style="<?= e(ContractPrintProfile::styleVariables($profile)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>پیش‌نمایش قرارداد</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/contract-print.css')) ?>">
  <style><?= ContractPrintProfile::pageRule($profile) ?></style>
</head>
<body class="contract-preview-document <?= $profile['color_mode'] === 'monochrome' ? 'contract-monochrome' : '' ?>">
  <aside class="contract-preview-diagnostics" aria-live="polite">
    <strong>تحلیل فضای قرارداد</strong>
    <dl>
      <div><dt>تعداد صفحات پیش‌بینی‌شده</dt><dd data-diagnostic="pages">...</dd></div>
      <div><dt>ارتفاع سربرگ</dt><dd data-diagnostic="header">...</dd></div>
      <div><dt>ارتفاع متن</dt><dd data-diagnostic="body">...</dd></div>
      <div><dt>ارتفاع جدول‌ها</dt><dd data-diagnostic="tables">...</dd></div>
      <div><dt>ارتفاع امضا</dt><dd data-diagnostic="signature">...</dd></div>
      <div><dt>حاشیه‌ها</dt><dd data-diagnostic="margins">...</dd></div>
      <div><dt>فونت متن</dt><dd data-diagnostic="font">...</dd></div>
      <div><dt>فاصله خطوط</dt><dd data-diagnostic="line-height">...</dd></div>
    </dl>
    <ul class="contract-preview-warnings" data-diagnostic-warnings hidden></ul>
  </aside>
  <main class="contract-print-page contract-print-body">
    <header class="proma-contract-letterhead">
      <section class="proma-letterhead-number"><small>شماره قرارداد</small><strong dir="ltr">PR-1405-1001</strong></section>
      <section class="proma-letterhead-logo"><?php if ($logoPath): ?><img src="<?= e(asset_url($logoPath)) ?>" alt="لوگوی <?= e($logoText) ?>"><?php else: ?><span class="proma-letterhead-logo-fallback"><?= e($logoText) ?></span><?php endif; ?></section>
      <section class="proma-letterhead-meta"><span>تاریخ قرارداد: ۱۴۰۵/۰۴/۲۲</span><?php if (!empty($profile['show_customer_header'])): ?><span>نام مشتری: مشتری نمونه</span><?php endif; ?></section>
      <?php if (!empty($profile['show_contract_title'])): ?><section class="proma-letterhead-title"><h1><?= e($settings['contract_document_title'] ?? 'قرارداد اجاره به شرط تملیک / امانت‌داری') ?></h1></section><?php endif; ?>
    </header>
    <?= $body ?>
    <?php if (!empty($profile['show_footer'])): ?><footer class="contract-print-footer"><?= e($settings['company_name'] ?? $logoText) ?> - نسخه پیش‌نمایش</footer><?php endif; ?>
  </main>
  <script>
  window.updateContractDiagnostics = function () {
    var page = document.querySelector('.contract-print-page');
    var body = page && page.querySelector('.contract-document-body');
    if (!page || !body) return;
    var rootStyle = window.getComputedStyle(document.documentElement);
    var cssNumber = function (name, fallback) { var value = parseFloat(rootStyle.getPropertyValue(name)); return Number.isFinite(value) ? value : fallback; };
    var marginTop = cssNumber('--contract-margin-top', <?= json_encode((float) $profile['margin_top']) ?>);
    var marginRight = cssNumber('--contract-margin-right', <?= json_encode((float) $profile['margin_right']) ?>);
    var marginBottom = cssNumber('--contract-margin-bottom', <?= json_encode((float) $profile['margin_bottom']) ?>);
    var marginLeft = cssNumber('--contract-margin-left', <?= json_encode((float) $profile['margin_left']) ?>);
    var probe = document.createElement('div');
    probe.style.cssText = 'position:absolute;visibility:hidden;width:100mm;height:1px';
    document.body.appendChild(probe);
    var pxPerMm = probe.getBoundingClientRect().width / 100;
    probe.remove();
    var usable = (297 - marginTop - marginBottom) * pxPerMm;
    var headerHeight = page.querySelector('.proma-contract-letterhead').getBoundingClientRect().height;
    var bodyHeight = body.getBoundingClientRect().height;
    var tableHeight = Array.prototype.reduce.call(body.querySelectorAll('table'), function (sum, table) { return sum + table.getBoundingClientRect().height; }, 0);
    var signatureHeight = Array.prototype.reduce.call(body.querySelectorAll('.contract-signature-grid'), function (sum, item) { return sum + item.getBoundingClientRect().height; }, 0);
    var footer = page.querySelector('.contract-print-footer');
    var contentHeight = headerHeight + bodyHeight + (footer ? footer.getBoundingClientRect().height : 0);
    var pages = Math.max(1, Math.ceil(contentHeight / usable));
    var set = function (key, value) { var target = document.querySelector('[data-diagnostic="' + key + '"]'); if (target) target.textContent = value; };
    set('pages', String(pages));
    set('header', Math.round(headerHeight) + 'px');
    set('body', Math.round(bodyHeight) + 'px');
    set('tables', Math.round(tableHeight) + 'px');
    set('signature', Math.round(signatureHeight) + 'px');
    set('margins', [marginTop, marginRight, marginBottom, marginLeft].join('/') + ' mm');
    set('font', cssNumber('--contract-body-font-size', 6) + 'px');
    set('line-height', rootStyle.getPropertyValue('--contract-body-line-height').trim() || '1.22');
    var warnings = [];
    if (pages > 1 && tableHeight > usable * .2) warnings.push('جدول اقساط باعث ورود قرارداد به صفحه دوم شده است.');
    if (cssNumber('--contract-signature-box-height', 15) > 18) warnings.push('فضای امضا بیش از حد بزرگ است.');
    if (marginTop > 5) warnings.push('حاشیه بالا زیاد است.');
    if (cssNumber('--contract-paragraph-spacing', 1) > 1) warnings.push('فاصله پاراگراف‌ها زیاد است.');
    var warningList = document.querySelector('[data-diagnostic-warnings]');
    warningList.innerHTML = '';
    page.querySelectorAll('.contract-preview-page-marker,.contract-preview-page-break').forEach(function (item) { item.remove(); });
    warnings.forEach(function (message) { var li = document.createElement('li'); li.textContent = message; warningList.appendChild(li); });
    warningList.hidden = warnings.length === 0;
    for (var index = 1; index <= pages; index += 1) {
      var marker = document.createElement('span');
      marker.className = 'contract-preview-page-marker';
      marker.style.top = ((index - 1) * usable + marginTop * pxPerMm) + 'px';
      marker.textContent = 'Page ' + index;
      page.appendChild(marker);
      if (index < pages) {
        var line = document.createElement('span');
        line.className = 'contract-preview-page-break';
        line.style.top = (index * usable + marginTop * pxPerMm) + 'px';
        page.appendChild(line);
      }
    }
  };
  window.updateContractDiagnostics();
  </script>
</body>
</html>
