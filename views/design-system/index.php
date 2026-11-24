<?php
$catalog = $designSystemCatalog;
$jsonCatalog = json_encode($catalog, $designSystemJsonFlags);
$categoryItemCount = static function (array $items): int {
    return array_sum(array_map(static function ($node) {
        return isset($node['items']) ? count($node['items']) : 1;
    }, $items));
};
$renderItems = static function (array $items): void {
    foreach ($items as $item): ?>
      <li class="ppds-nav-leaf">
        <button class="ppds-nav-item" type="button" data-nav-item="<?= e($item['id']) ?>" aria-current="false">
          <span><?= e($item['name']) ?></span><small><?= e($item['english']) ?></small>
        </button>
      </li>
    <?php endforeach;
};
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="color-scheme" content="light dark">
  <title>PROMA PAY DESIGN SYSTEM</title>
  <link rel="icon" href="<?= e($designSystemTemplateUrl('images/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e($designSystemTemplateUrl('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e($designSystemTemplateUrl('css/vendors/themify.css')) ?>">
  <link rel="stylesheet" href="<?= e($designSystemTemplateUrl('css/vendors/feather-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e($designSystemAssetUrl('assets/css/design-system/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e($designSystemAssetUrl('assets/css/pages/design-system.css')) ?>">
</head>
<body class="ppds-page" data-design-system-page>
  <a class="ppds-skip-link" href="#ppds-main">رفتن به محتوای مستندات</a>
  <header class="ppds-header">
    <a class="ppds-brand" href="<?= e(url('dashboard')) ?>" aria-label="بازگشت به سامانه پروماپی">
      <span class="ppds-brand-mark" aria-hidden="true">P</span>
      <span class="ppds-brand-copy"><strong>PROMA PAY</strong><small>DESIGN SYSTEM</small></span>
    </a>
    <span class="ppds-header-divider" aria-hidden="true"></span>
    <span class="ppds-version"><span class="ppds-live-dot" aria-hidden="true"></span><?= e($designSystemVersion) ?></span>
    <div class="ppds-header-search" data-search-wrap>
      <label class="ppds-search-field" for="ppds-search">
        <i data-feather="search" aria-hidden="true"></i>
        <input id="ppds-search" type="search" autocomplete="off" placeholder="جست‌وجوی کامپوننت…" aria-label="جست‌وجوی کامپوننت‌ها" aria-controls="ppds-search-results" aria-expanded="false" data-component-search>
        <kbd>Ctrl K</kbd>
      </label>
      <div class="ppds-search-results" id="ppds-search-results" role="listbox" aria-label="نتایج جست‌وجو" hidden data-search-results></div>
    </div>
    <nav class="ppds-header-links" aria-label="پیوندهای راهنما">
      <a href="#ppds-main" data-scroll-main>Documentation</a>
      <a href="https://github.com/mehdirabani/Proma-pay" target="_blank" rel="noreferrer">GitHub <i data-feather="external-link" aria-hidden="true"></i></a>
    </nav>
    <div class="ppds-header-actions">
      <button class="ppds-icon-button ppds-mobile-only" type="button" aria-label="باز کردن فهرست" aria-controls="ppds-sidebar" aria-expanded="false" data-open-sidebar><i data-feather="menu" aria-hidden="true"></i></button>
      <button class="ppds-icon-button ppds-theme-toggle" type="button" aria-label="تغییر به حالت تاریک" aria-pressed="false" data-theme-toggle><i data-feather="moon" aria-hidden="true"></i></button>
      <button class="ppds-primary-button" type="button" data-explore-playground><i data-feather="sliders" aria-hidden="true"></i><span>Component Playground</span></button>
    </div>
  </header>

  <section class="ppds-hero" aria-labelledby="ppds-hero-title">
    <div class="ppds-hero-copy">
      <div class="ppds-eyebrow"><span>PRODUCT DESIGN LANGUAGE</span><span class="ppds-eyebrow-line"></span><span><?= e(strtoupper($designSystemVersion)) ?></span></div>
      <h1 id="ppds-hero-title">One Design Language.<br><span>Every Product Experience.</span></h1>
      <p>مرجع یکپارچه طراحی، توسعه و مستندسازی کامپوننت‌های پروماپی.</p>
      <div class="ppds-hero-actions">
        <button class="ppds-primary-button" type="button" data-explore-components>مشاهده کامپوننت‌ها <i data-feather="arrow-left" aria-hidden="true"></i></button>
        <a class="ppds-secondary-button" href="#ppds-documentation">مشاهده مستندات <i data-feather="book-open" aria-hidden="true"></i></a>
        <button class="ppds-text-button" type="button" data-explore-code>دیدن کد <i data-feather="code" aria-hidden="true"></i></button>
      </div>
    </div>
    <div class="ppds-hero-art" aria-hidden="true">
      <div class="ppds-art-grid"></div>
      <div class="ppds-art-card ppds-art-card--back"><span class="ppds-art-line short"></span><span class="ppds-art-line"></span><span class="ppds-art-line medium"></span><span class="ppds-art-chip"></span></div>
      <div class="ppds-art-card ppds-art-card--front"><span class="ppds-art-label">DESIGN SYSTEM</span><span class="ppds-art-title">P<span>·</span></span><span class="ppds-art-caption">Built for clarity</span><span class="ppds-art-palette"><i></i><i></i><i></i><i></i></span></div>
      <span class="ppds-art-orbit ppds-art-orbit--one"></span><span class="ppds-art-orbit ppds-art-orbit--two"></span>
    </div>
    <div class="ppds-stat-grid" aria-label="آمار مستندات">
      <article class="ppds-stat-card"><span class="ppds-stat-icon"><i data-feather="package" aria-hidden="true"></i></span><div><strong data-stat="components"><?= (int) $catalog['counts']['components'] ?></strong><span>کامپوننت مستندشده</span></div></article>
      <article class="ppds-stat-card"><span class="ppds-stat-icon"><i data-feather="layers" aria-hidden="true"></i></span><div><strong data-stat="categories"><?= (int) $catalog['counts']['categories'] ?></strong><span>دسته‌بندی راهنما</span></div></article>
      <article class="ppds-stat-card"><span class="ppds-stat-icon"><i data-feather="layout" aria-hidden="true"></i></span><div><strong data-stat="patterns"><?= (int) $catalog['counts']['patterns'] ?></strong><span>الگوی آماده صفحه</span></div></article>
      <article class="ppds-stat-card"><span class="ppds-stat-icon"><i data-feather="code" aria-hidden="true"></i></span><div><strong data-stat="codeExamples"><?= (int) $catalog['counts']['codeExamples'] ?></strong><span>نمونه کد قابل دریافت</span></div></article>
    </div>
  </section>

  <div class="ppds-doc-layout" id="ppds-documentation">
    <div class="ppds-drawer-backdrop" hidden data-drawer-backdrop></div>
    <aside class="ppds-sidebar" id="ppds-sidebar" aria-label="دسته‌بندی مستندات" data-sidebar>
      <div class="ppds-sidebar-head"><div><span class="ppds-overline">LIBRARY</span><h2>مستندات</h2></div><button class="ppds-icon-button ppds-mobile-only" type="button" aria-label="بستن فهرست" data-close-drawers><i data-feather="x" aria-hidden="true"></i></button></div>
      <div class="ppds-sidebar-filter"><i data-feather="filter" aria-hidden="true"></i><input type="search" placeholder="فیلتر دسته‌ها" aria-label="فیلتر دسته‌های مستندات" data-sidebar-filter></div>
      <nav class="ppds-nav-scroll" aria-label="فهرست کامپوننت‌ها">
        <ul class="ppds-nav-tree">
          <?php foreach ($catalog['categories'] as $category): ?>
            <li class="ppds-nav-section" data-category="<?= e($category['id']) ?>">
              <button class="ppds-nav-root" type="button" aria-expanded="true" data-nav-root="<?= e($category['id']) ?>">
                <i data-feather="<?= e($category['icon']) ?>" aria-hidden="true"></i><span><?= e($category['label']) ?></span>
                <small><?= $categoryItemCount($category['items']) ?></small>
                <i class="ppds-chevron" data-feather="chevron-down" aria-hidden="true"></i>
              </button>
              <ul class="ppds-nav-children">
                <?php foreach ($category['items'] as $node): ?>
                  <?php if (isset($node['items'])): ?>
                    <li class="ppds-nav-group" data-nav-group-wrap="<?= e($node['id']) ?>">
                      <button class="ppds-nav-group-button" type="button" aria-expanded="true" data-nav-group="<?= e($node['id']) ?>"><i data-feather="<?= e($node['icon'] ?? 'circle') ?>" aria-hidden="true"></i><span><?= e($node['label']) ?></span><small><?= count($node['items']) ?></small><i class="ppds-chevron" data-feather="chevron-down" aria-hidden="true"></i></button>
                      <ul class="ppds-nav-leaves"><?php $renderItems($node['items']); ?></ul>
                    </li>
                  <?php else: ?>
                    <li class="ppds-nav-group ppds-nav-group--flat" data-nav-group-wrap="<?= e($node['group']) ?>">
                      <?php $renderItems([$node]); ?>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <div class="ppds-sidebar-foot"><span class="ppds-live-dot" aria-hidden="true"></span><span>مستندات همگام با catalog</span><small><?= e($designSystemVersion) ?></small></div>
    </aside>

    <main class="ppds-main" id="ppds-main" tabindex="-1">
      <div class="ppds-page-toolbar"><button class="ppds-mobile-drawer-button" type="button" aria-controls="ppds-sidebar" aria-expanded="false" data-open-sidebar><i data-feather="menu" aria-hidden="true"></i> فهرست</button><div class="ppds-breadcrumb"><span>مستندات</span><i data-feather="chevron-left" aria-hidden="true"></i><strong data-breadcrumb-current>دکمه‌ها</strong></div><span class="ppds-local-demo"><i data-feather="shield" aria-hidden="true"></i> نمونه‌ها محلی و آزمایشی هستند</span><button class="ppds-mobile-drawer-button" type="button" aria-label="نمایش ویژگی‌ها و بازدیدهای اخیر" aria-controls="ppds-toc-panel" aria-expanded="false" data-open-right-panel><i data-feather="list" aria-hidden="true"></i> ویژگی‌ها</button></div>
      <div class="ppds-article" data-article>
        <header class="ppds-article-header">
          <div><span class="ppds-overline" data-doc-kicker>COMPONENTS / BUTTONS</span><h2 data-doc-title>دکمه اصلی</h2><p data-doc-overview>برای اقدام روشن و قابل پیش‌بینی استفاده می‌شود.</p></div>
          <button class="ppds-secondary-button ppds-reset-button" type="button" data-reset-playground><i data-feather="rotate-ccw" aria-hidden="true"></i> بازنشانی نمونه</button>
        </header>
        <section class="ppds-doc-section ppds-preview-section" id="live-preview" aria-labelledby="preview-heading">
          <div class="ppds-section-heading"><div><span class="ppds-section-index">01</span><div><h3 id="preview-heading">پیش‌نمایش زنده</h3><p>تنظیم‌ها را تغییر دهید؛ نمونه و کد هم‌زمان به‌روزرسانی می‌شوند.</p></div></div><span class="ppds-preview-label"><i data-feather="eye" aria-hidden="true"></i> LIVE PREVIEW</span></div>
          <div class="ppds-playground-grid">
            <div class="ppds-preview-canvas" aria-live="polite" data-preview></div>
            <aside class="ppds-controls" aria-label="کنترل‌های Playground"><div class="ppds-controls-title"><i data-feather="sliders" aria-hidden="true"></i><strong>تنظیمات نمونه</strong><button type="button" class="ppds-reset-icon" aria-label="بازنشانی تنظیم‌ها" data-reset-playground><i data-feather="rotate-ccw" aria-hidden="true"></i></button></div><div class="ppds-controls-body" data-controls></div></aside>
          </div>
        </section>
        <section class="ppds-doc-section" id="usage" aria-labelledby="usage-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">02</span><div><h3 id="usage-heading">کاربرد</h3><p>راهنمای استفاده در رابط پروماپی</p></div></div></div><div class="ppds-usage-card"><i data-feather="info" aria-hidden="true"></i><p data-doc-usage>از این کامپوننت برای اقدام‌های رایج استفاده کنید.</p></div></section>
        <section class="ppds-doc-section" id="properties" aria-labelledby="properties-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">03</span><div><h3 id="properties-heading">ویژگی‌ها</h3><p>قرارداد Props و مقدارهای قابل تنظیم</p></div></div></div><div class="ppds-table-wrap"><table class="ppds-table"><thead><tr><th scope="col">نام</th><th scope="col">نوع</th><th scope="col">پیش‌فرض</th><th scope="col">توضیح</th></tr></thead><tbody data-props></tbody></table></div></section>
        <section class="ppds-doc-section" id="events" aria-labelledby="events-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">04</span><div><h3 id="events-heading">رویدادها و وضعیت‌ها</h3><p>تعامل‌های پشتیبانی‌شده و نمایش state</p></div></div></div><div class="ppds-two-column"><div class="ppds-detail-card"><h4>رویدادها</h4><ul data-events></ul></div><div class="ppds-detail-card"><h4>وضعیت‌ها</h4><div class="ppds-state-list" data-states></div></div></div></section>
        <section class="ppds-doc-section" id="accessibility" aria-labelledby="a11y-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">05</span><div><h3 id="a11y-heading">دسترس‌پذیری و Responsive</h3><p>راهنمای استفاده برای همه کاربران و اندازه‌ها</p></div></div></div><div class="ppds-two-column"><div class="ppds-detail-card"><h4><i data-feather="eye" aria-hidden="true"></i> دسترس‌پذیری</h4><p data-doc-a11y></p></div><div class="ppds-detail-card"><h4><i data-feather="smartphone" aria-hidden="true"></i> رفتار واکنش‌گرا</h4><p data-doc-responsive></p></div></div></section>
        <section class="ppds-doc-section" id="code-examples" aria-labelledby="code-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">06</span><div><h3 id="code-heading">نمونه کد</h3><p data-code-purpose>نمونه‌های مستقل و متناسب با تنظیم فعلی</p></div></div><button class="ppds-secondary-button ppds-download-code" type="button" data-download-code><i data-feather="download" aria-hidden="true"></i> دریافت فایل</button></div><div class="ppds-code-card"><div class="ppds-code-toolbar"><label class="ppds-language-picker"><span class="sr-only">زبان نمونه کد</span><select aria-label="زبان نمونه کد" data-language-select><?php foreach ($designSystemLanguages as $language): ?><option value="<?= e($language['id']) ?>"><?= e($language['label']) ?></option><?php endforeach; ?></select><i data-feather="chevron-down" aria-hidden="true"></i></label><span class="ppds-code-status"><span></span> آماده برای کپی</span><div><button class="ppds-code-expand" type="button" aria-expanded="false" data-expand-code><i data-feather="maximize-2" aria-hidden="true"></i><span>گسترش</span></button><button class="ppds-copy-button" type="button" data-copy-code><i data-feather="copy" aria-hidden="true"></i><span>کپی کد</span></button></div></div><p class="ppds-code-purpose" data-language-purpose></p><div class="ppds-code-scroll" data-code-scroll><pre class="ppds-code" data-line-numbers><code data-code aria-label="نمونه کد"></code></pre></div></div></section>
        <section class="ppds-doc-section" id="guidance" aria-labelledby="guidance-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">07</span><div><h3 id="guidance-heading">راهنمای استفاده</h3><p>الگوی صحیح استفاده و نکته‌های تکمیلی</p></div></div></div><div class="ppds-two-column"><div class="ppds-guidance-card ppds-guidance-card--do"><h4><i data-feather="check-circle" aria-hidden="true"></i> انجام دهید</h4><p data-doc-do></p></div><div class="ppds-guidance-card ppds-guidance-card--dont"><h4><i data-feather="alert-circle" aria-hidden="true"></i> پرهیز کنید</h4><p data-doc-dont></p></div></div></section>
        <section class="ppds-doc-section" id="related" aria-labelledby="related-heading"><div class="ppds-section-heading"><div><span class="ppds-section-index">08</span><div><h3 id="related-heading">کامپوننت‌های مرتبط</h3><p>الگوهایی که معمولاً در همین workflow استفاده می‌شوند</p></div></div></div><div class="ppds-related-grid" data-related></div></section>
      </div>
      <footer class="ppds-footer"><span>Proma Pay Design System</span><span>ساخته‌شده برای تجربه یکپارچه پروماپی</span><a href="https://github.com/mehdirabani/Proma-pay" target="_blank" rel="noreferrer">مشاهده مخزن در GitHub <i data-feather="external-link" aria-hidden="true"></i></a></footer>
    </main>

    <aside class="ppds-right-panel" id="ppds-toc-panel" aria-label="ناوبری سریع و اطلاعات کامپوننت" data-right-panel>
      <div class="ppds-right-panel-head"><div><span class="ppds-overline">ON THIS PAGE</span><h2>ناوبری سریع</h2></div><button class="ppds-icon-button ppds-mobile-only" type="button" aria-label="بستن پنل اطلاعات" data-close-drawers><i data-feather="x" aria-hidden="true"></i></button></div>
      <nav class="ppds-toc" aria-label="بخش‌های همین صفحه"><a class="is-active" href="#live-preview">پیش‌نمایش زنده</a><a href="#usage">کاربرد</a><a href="#properties">ویژگی‌ها</a><a href="#events">رویدادها و وضعیت‌ها</a><a href="#accessibility">دسترس‌پذیری</a><a href="#code-examples">نمونه کد</a><a href="#guidance">راهنمای استفاده</a><a href="#related">مرتبط</a></nav>
      <div class="ppds-right-divider"></div>
      <div class="ppds-quick-properties"><h3>ویژگی‌ها</h3><div data-quick-props></div></div>
      <div class="ppds-right-divider"></div>
      <div class="ppds-recent"><div><h3>بازدیدهای اخیر</h3><button type="button" data-clear-recent aria-label="پاک‌کردن بازدیدهای اخیر">پاک‌کردن</button></div><div data-recent-list><p class="ppds-muted-small">کامپوننتی انتخاب نشده است.</p></div></div>
      <div class="ppds-version-card"><span class="ppds-overline">CURRENT RELEASE</span><strong><?= e($designSystemVersion) ?></strong><p>مستندات بر اساس نسخه هسته جاری ساخته شده‌اند.</p><a href="https://github.com/mehdirabani/Proma-pay" target="_blank" rel="noreferrer">تاریخچه انتشار <i data-feather="arrow-left" aria-hidden="true"></i></a></div>
    </aside>
  </div>

  <div class="ppds-toast-region" role="status" aria-live="polite" aria-atomic="true" data-toast-region></div>
  <div class="ppds-modal-backdrop" hidden data-demo-modal>
    <section class="ppds-modal" role="dialog" aria-modal="true" aria-labelledby="ppds-modal-title" tabindex="-1">
      <header><div><span class="ppds-overline">INTERACTIVE EXAMPLE</span><h2 id="ppds-modal-title">پیش‌نمایش تعاملی</h2></div><button class="ppds-icon-button" type="button" aria-label="بستن پنجره" data-modal-close><i data-feather="x" aria-hidden="true"></i></button></header>
      <div class="ppds-modal-body" data-modal-body><p>این پنجره فقط بخشی از نمونه تعاملی است. هیچ اطلاعاتی ثبت یا ارسال نمی‌شود.</p></div>
      <footer><button class="ppds-secondary-button" type="button" data-modal-close>انصراف</button><button class="ppds-primary-button" type="button" data-modal-confirm>تأیید نمونه</button></footer>
    </section>
  </div>
  <script type="application/json" id="ppds-catalog-data"><?= is_string($jsonCatalog) ? $jsonCatalog : '{}' ?></script>
  <script src="<?= e($designSystemTemplateUrl('js/icons/feather-icon/feather.min.js')) ?>"></script>
  <script src="<?= e($designSystemAssetUrl('assets/js/design-system.js')) ?>"></script>
</body>
</html>
