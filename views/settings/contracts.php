<?php
$section = $section ?? 'dashboard';
$effectiveVersion = (int) ($effectiveTemplate['version_number'] ?? 0);
$draftVersion = (int) ($draftTemplate['version_number'] ?? 0);
$editorSource = (string) ($editorTemplate['body_source'] ?? ContractDocument::defaultTemplate());
$editorFormat = (string) ($editorTemplate['body_format'] ?? ContractTemplateRenderer::FORMAT_PLAIN);
$statusLabels = ['draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'superseded' => 'جایگزین‌شده', 'archived' => 'بایگانی'];
$contractPreviewUrl = $contractPreviewUrl ?? url('settings/contractsPreview');
$tabs = [
    'dashboard' => ['خانه قرارداد', 'home'],
    'numbering' => ['شماره‌گذاری', 'hash'],
    'template' => ['قالب قرارداد', 'edit-3'],
    'print' => ['ظاهر و چاپ', 'printer'],
    'versions' => ['نسخه‌های قالب', 'layers'],
    'variables' => ['متغیرها', 'code'],
    'preview' => ['پیش‌نمایش', 'eye'],
    'rebuild' => ['بازسازی اسناد', 'refresh-cw'],
];
?>

<section class="card proma-contract-settings-head">
  <div class="card-body">
    <div>
      <span class="badge badge-light-primary">تنظیمات مستقل قرارداد</span>
      <h2>قالب، نسخه‌ها و موتور چاپ قرارداد</h2>
      <p>محتوای حقوقی از ظاهر چاپ جداست؛ تغییر فاصله‌ها و حاشیه چاپ، اسناد قدیمی را بازنویسی نمی‌کند.</p>
    </div>
    <a class="btn secondary" href="<?= e(url('settings')) ?>"><i data-feather="settings"></i> تنظیمات اصلی</a>
  </div>
</section>

<nav class="proma-contract-settings-nav" aria-label="بخش‌های تنظیمات قرارداد">
  <?php foreach ($tabs as $key => $tab): ?>
    <a class="<?= $section === $key ? 'active' : '' ?>" href="<?= e(url('settings/contracts/' . $key)) ?>">
      <i data-feather="<?= e($tab[1]) ?>"></i><span><?= e($tab[0]) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($section === 'dashboard'): ?>
  <section class="proma-contract-settings-kpis">
    <article><span><i data-feather="file-text"></i></span><small>قالب فعال</small><strong><?= !empty($effectiveTemplate['id']) ? 'سفارشی' : 'پیش‌فرض سامانه' ?></strong></article>
    <article><span><i data-feather="git-commit"></i></span><small>نسخه فعال</small><strong><?= $effectiveVersion ? 'V' . to_persian_digits($effectiveVersion) : 'سیستمی' ?></strong></article>
    <article><span><i data-feather="clock"></i></span><small>اسناد نیازمند بازسازی</small><strong><?= to_persian_digits($templateStats['stale_documents'] ?? 0) ?></strong></article>
    <article><span><i data-feather="image"></i></span><small>لوگوی چاپ</small><strong><?= !empty($templateStats['has_logo']) ? 'آماده' : 'تنظیم نشده' ?></strong></article>
  </section>

  <div class="grid cols-2">
    <section class="card">
      <div class="card-header card-no-border"><h3>قالب و نسخه حقوقی</h3></div>
      <div class="card-body proma-contract-action-list">
        <a href="<?= e(url('settings/contracts/template')) ?>"><i data-feather="edit-3"></i><span><strong>ویرایش قالب</strong><small>ساخت پیش‌نویس بدون تغییر نسخه منتشرشده</small></span></a>
        <a href="<?= e(url('settings/contracts/versions')) ?>"><i data-feather="layers"></i><span><strong>مشاهده نسخه‌ها</strong><small>انتشار، مقایسه و بازگردانی نسخه‌های پیشین</small></span></a>
        <button type="button" data-contract-copy-source="effective-template-source"><i data-feather="copy"></i><span><strong>کپی متن قالب فعلی</strong><small>متغیرها بدون جایگزینی کپی می‌شوند</small></span></button>
      </div>
    </section>
    <section class="card">
      <div class="card-header card-no-border"><h3>ظاهر و خروجی چاپ</h3></div>
      <div class="card-body proma-contract-action-list">
        <a href="<?= e(url('settings/contracts/print')) ?>"><i data-feather="sliders"></i><span><strong>مدیریت ظاهر چاپ</strong><small>حاشیه، فونت، line-height و تراکم جدول</small></span></a>
        <a href="<?= e(url('settings/contracts/preview')) ?>"><i data-feather="eye"></i><span><strong>پیش‌نمایش چاپ</strong><small>داده نمونه و همان renderer واقعی سامانه</small></span></a>
        <a href="<?= e(url('settings/contracts/rebuild')) ?>"><i data-feather="refresh-cw"></i><span><strong>بازسازی اسناد قدیمی</strong><small>پردازش دسته‌ای فقط برای اسناد غیرنهایی</small></span></a>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($section === 'numbering'): ?>
  <section class="card"><div class="card-header card-no-border"><h3>شماره‌گذاری قرارداد</h3></div><form method="post" action="<?= e(url('settings/saveContractNumbering')) ?>"><?= csrf_field() ?><div class="card-body form-grid two"><label>پیشوند<input name="contract_prefix" value="<?= e($settings['contract_prefix'] ?? 'PR') ?>" required></label><label>سال قرارداد<input name="contract_year" value="<?= e($settings['contract_year'] ?? '') ?>" inputmode="numeric"></label><label>شماره سریال بعدی<input name="contract_next_serial" value="<?= e($settings['contract_next_serial'] ?? '1') ?>" inputmode="numeric" required></label><label>الگوی شماره<input name="contract_number_format" value="<?= e($settings['contract_number_format'] ?? 'PR-{SERIAL:6}') ?>" dir="ltr" required></label><label class="full">دلیل تغییر<input name="change_reason" value="ویرایش تنظیمات شماره‌گذاری قرارداد"></label><div class="notice info full">متغیرهای قابل استفاده: <code>{PREFIX}</code>، <code>{YEAR}</code> و <code>{SERIAL:6}</code>.</div></div><div class="card-footer"><button class="btn" type="submit"><i data-feather="save"></i> ذخیره شماره‌گذاری</button></div></form></section>
<?php endif; ?>

<?php if ($section === 'template'): ?>
  <section class="card"><div class="card-header card-no-border"><h3>عنوان و سربرگ قرارداد</h3></div><form method="post" action="<?= e(url('settings/saveContractHeader')) ?>"><?= csrf_field() ?><div class="card-body form-grid"><label class="full required-field">عنوان چاپی<input name="contract_document_title" value="<?= e($settings['contract_document_title'] ?? '') ?>" required></label><label class="full">متن زیر عنوان<textarea name="contract_document_header" rows="3"><?= e($settings['contract_document_header'] ?? '') ?></textarea></label><label class="full">دلیل تغییر<input name="change_reason" value="ویرایش عنوان و سربرگ قرارداد"></label></div><div class="card-footer"><button class="btn" type="submit"><i data-feather="save"></i> ذخیره سربرگ</button></div></form></section>
  <section class="card proma-template-editor-card" data-contract-template-workspace>
    <div class="card-header card-no-border">
      <div class="header-top">
        <div><h3>ویرایشگر قالب قرارداد</h3><p>نسخه فعال: <?= $effectiveVersion ? 'V' . to_persian_digits($effectiveVersion) : 'قالب سیستمی' ?><?php if ($draftVersion): ?>، پیش‌نویس: V<?= to_persian_digits($draftVersion) ?><?php endif; ?></p></div>
        <div class="proma-editor-mode-switch" role="tablist">
          <button class="active" type="button" data-template-mode="simple">ویرایش ساده</button>
          <button type="button" data-template-mode="source">کد قالب</button>
          <a href="<?= e(url('settings/contracts/preview')) ?>">پیش‌نمایش</a>
        </div>
      </div>
    </div>
    <form method="post" action="<?= e(url('settings/saveContractDraft')) ?>" data-contract-template-form>
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="proma-template-toolbar">
          <button class="btn secondary small" type="button" data-contract-copy-source="editor-template-source"><i data-feather="copy"></i> کپی متن</button>
          <button class="btn secondary small" type="button" data-load-contract-source="effective-template-source"><i data-feather="download"></i> بارگذاری قالب فعلی</button>
          <button class="btn secondary small" type="button" data-load-contract-source="default-template-source"><i data-feather="file-plus"></i> بارگذاری پیش‌فرض</button>
          <button class="btn secondary small" type="button" data-template-find><i data-feather="search"></i> یافتن و جایگزینی</button>
          <button class="btn secondary small" type="button" data-insert-important-clause><i data-feather="alert-circle"></i> درج بند مهم</button>
          <select data-contract-variable-select aria-label="درج متغیر"><option value="">درج متغیر...</option><?php foreach ($variableCatalog as $group => $variables): ?><optgroup label="<?= e($group) ?>"><?php foreach ($variables as $variable): ?><option value="<?= e($variable['code']) ?>"><?= e($variable['title']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
          <span data-template-count>۰ واژه، ۰ نویسه</span>
        </div>
        <div class="notice info mt-3">
          <strong>راهنمای برجسته‌کردن بند مهم</strong><br>
          برای نمایش یک بند مهم به‌صورت بولد و یک پیکسل بزرگ‌تر، متن را بین دو علامت <code>**</code> قرار دهید.<br>
          <code>**این بند در نسخه چاپی با فونت ۷ پیکسل و به‌صورت بولد نمایش داده می‌شود.**</code>
        </div>
        <label class="proma-template-source-field">متن قالب
          <textarea id="editor-template-source" name="body_source" rows="24" data-rich-editor data-rich-editor-height="620" data-contract-template-editor required><?= e($editorSource) ?></textarea>
        </label>
        <div class="form-grid two mt-3">
          <label>فرمت محتوا
            <select name="body_format">
              <option value="plain_text_v1" <?= $editorFormat === 'plain_text_v1' ? 'selected' : '' ?>>متن ساده سازگار</option>
              <option value="structured_html_v1" <?= $editorFormat === 'structured_html_v1' ? 'selected' : '' ?>>HTML ساختاریافته امن</option>
            </select>
          </label>
          <label class="required-field">دلیل تغییر<input name="change_reason" required minlength="3" placeholder="خلاصه تغییرات این پیش‌نویس"></label>
        </div>
      </div>
      <div class="card-footer proma-template-editor-footer">
        <span data-unsaved-indicator hidden>تغییرات ذخیره‌نشده دارید.</span>
        <div class="actions"><button class="btn" type="submit"><i data-feather="save"></i> ذخیره پیش‌نویس</button><a class="btn secondary" href="<?= e(url('settings/contracts/preview')) ?>"><i data-feather="eye"></i> پیش‌نمایش</a></div>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><div class="header-top"><h3>بازنشانی کنترل‌شده</h3><button class="btn danger small" type="button" data-open-modal="reset-contract-template"><i data-feather="rotate-ccw"></i> ساخت پیش‌نویس پیش‌فرض</button></div></div>
    <div class="card-body"><div class="notice warning">بازنشانی، نسخه منتشرشده را حذف نمی‌کند؛ فقط یک پیش‌نویس تازه از قالب پیش‌فرض می‌سازد.</div></div>
  </section>
  <div class="modal" id="reset-contract-template"><div class="modal-content"><div class="modal-header"><h3>بازنشانی قالب</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('settings/resetContractTemplate')) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice warning full">نسخه فعلی دست‌نخورده می‌ماند و قالب پیش‌فرض به‌عنوان پیش‌نویس ایجاد می‌شود.</div><label class="full required-field">دلیل بازنشانی<input name="change_reason" required value="بازنشانی به قالب پیش‌فرض سامانه"></label></div><div class="modal-footer"><button class="btn danger" type="submit">ادامه و ساخت پیش‌نویس</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div>
<?php endif; ?>

<?php if ($section === 'print'): ?>
  <div class="proma-contract-print-layout" data-print-profile-workspace>
    <section class="card">
      <div class="card-header card-no-border"><h3>تنظیمات ظاهر و چاپ</h3></div>
      <form method="post" action="<?= e(url('settings/saveContractPrint')) ?>" data-print-profile-form>
        <?= csrf_field() ?>
        <div class="card-body form-grid two">
          <label class="full">تراکم چاپ<select name="preset" data-print-profile-input><?php foreach ($printPresets as $presetKey => $presetValues): ?><option value="<?= e($presetKey) ?>" <?= $printProfile['preset'] === $presetKey ? 'selected' : '' ?>><?= e($presetValues['label'] ?? $presetKey) ?></option><?php endforeach; ?><option value="custom" <?= $printProfile['preset'] === 'custom' ? 'selected' : '' ?>>سفارشی</option></select></label>
          <div class="notice info full">در پروفایل رسمی فشرده، متن معمولی تا ۷px و عنوان‌ها و بندهای مهم تا ۸px خوانا باقی می‌مانند؛ تراکم از فاصله‌گذاری کنترل می‌شود، نه از کوچک‌کردن افراطی فونت.</div>
          <?php $printFields = [
            'margin_top' => ['حاشیه بالا', 4, 25, .5, 'mm'], 'margin_right' => ['حاشیه راست', 7, 25, .5, 'mm'],
            'margin_bottom' => ['حاشیه پایین', 7, 30, .5, 'mm'], 'margin_left' => ['حاشیه چپ', 7, 25, .5, 'mm'],
            'body_font_size' => ['اندازه فونت متن', 5, 14, .1, 'px'], 'important_font_size' => ['فونت بند مهم', 6, 15, .1, 'px'], 'heading_font_size' => ['فونت عنوان داخلی', 6, 15, .1, 'px'],
            'body_line_height' => ['فاصله خطوط متن', 1.15, 2, .01, ''], 'important_line_height' => ['فاصله خطوط بند مهم', 1.15, 2, .01, ''], 'heading_line_height' => ['فاصله خطوط عنوان', 1.15, 2, .01, ''],
            'paragraph_spacing' => ['فاصله پاراگراف', 0, 12, .5, 'px'], 'section_top_spacing' => ['فاصله بالای عنوان', 0, 16, .5, 'px'],
            'section_bottom_spacing' => ['فاصله پایین عنوان', 0, 12, .5, 'px'], 'list_spacing' => ['فاصله فهرست', 0, 12, .5, 'px'], 'list_indent' => ['تورفتگی فهرست', 6, 30, 1, 'px'],
            'header_top_spacing' => ['فاصله بالای هدر', 0, 12, .5, 'px'], 'header_bottom_spacing' => ['فاصله پایین هدر', 0, 16, .5, 'px'], 'header_divider_spacing' => ['فاصله خط جداکننده', 0, 16, .5, 'px'],
            'logo_width' => ['عرض لوگو', 15, 60, 1, 'mm'], 'logo_height' => ['ارتفاع لوگو', 8, 30, 1, 'mm'],
            'table_font_size' => ['فونت جدول', 5, 13, .1, 'px'], 'table_heading_font_size' => ['فونت سرستون جدول', 5.5, 14, .1, 'px'], 'table_line_height' => ['فاصله خطوط جدول', 1.1, 1.8, .01, ''],
            'table_cell_vertical_padding' => ['فاصله عمودی سلول', .5, 8, .1, 'px'], 'table_cell_horizontal_padding' => ['فاصله افقی سلول', 1, 10, .1, 'px'], 'table_margin' => ['فاصله جدول', 0, 12, .5, 'px'],
            'signature_top_spacing' => ['فاصله امضا', 4, 30, .5, 'mm'], 'signature_box_height' => ['ارتفاع کادر امضا', 15, 45, .5, 'mm'],
          ]; foreach ($printFields as $key => $meta): ?>
            <label><?= e($meta[0]) ?><span class="proma-number-control"><input type="number" name="<?= e($key) ?>" value="<?= e($printProfile[$key]) ?>" min="<?= e($meta[1]) ?>" max="<?= e($meta[2]) ?>" step="<?= e($meta[3]) ?>" data-print-profile-input data-print-unit="<?= e($meta[4]) ?>"><small><?= e($meta[4]) ?></small></span></label>
          <?php endforeach; ?>
          <label class="proma-checkbox-field"><input type="checkbox" name="show_customer_header" value="1" <?= !empty($printProfile['show_customer_header']) ? 'checked' : '' ?> data-print-profile-input> نمایش نام مشتری در هدر</label>
          <label class="proma-checkbox-field"><input type="checkbox" name="show_contract_title" value="1" <?= !empty($printProfile['show_contract_title']) ? 'checked' : '' ?> data-print-profile-input> نمایش عنوان قرارداد</label>
          <label class="proma-checkbox-field"><input type="checkbox" name="show_footer" value="1" <?= !empty($printProfile['show_footer']) ? 'checked' : '' ?> data-print-profile-input> نمایش پابرگ سند</label>
          <label>حالت رنگ<select name="color_mode" data-print-profile-input><option value="color" <?= $printProfile['color_mode'] === 'color' ? 'selected' : '' ?>>رنگی</option><option value="monochrome" <?= $printProfile['color_mode'] === 'monochrome' ? 'selected' : '' ?>>تک‌رنگ</option></select></label>
          <label class="full">دلیل تغییر<input name="change_reason" placeholder="ویرایش تنظیمات چاپ"></label>
        </div>
        <div class="card-footer"><div class="actions"><button class="btn" type="submit"><i data-feather="save"></i> ذخیره تنظیمات چاپ</button><button class="btn secondary" type="submit" name="apply_preset" value="1"><i data-feather="rotate-ccw"></i> اعمال preset انتخابی</button></div></div>
      </form>
    </section>
    <section class="card proma-live-print-preview">
      <div class="card-header card-no-border"><div class="header-top"><h3>پیش‌نمایش زنده A4</h3><div class="proma-preview-zoom"><button type="button" data-preview-zoom=".5">۵۰٪</button><button type="button" data-preview-zoom=".75">۷۵٪</button><button class="active" type="button" data-preview-zoom="1">۱۰۰٪</button><button type="button" data-preview-fit>عرض</button></div></div></div>
      <div class="card-body"><div class="notice info">برای خروجی تمیز، در پنجره چاپ گزینه «سرصفحه و پابرگ / Headers and footers» را غیرفعال کنید.</div><div class="proma-preview-frame-shell"><iframe title="پیش‌نمایش زنده قرارداد" src="<?= e($contractPreviewUrl) ?>" data-contract-preview-frame></iframe></div></div>
    </section>
  </div>
<?php endif; ?>

<?php if ($section === 'versions'): ?>
  <?php if (!empty($versionComparison)): ?>
    <section class="card">
      <div class="card-header card-no-border"><h3>مقایسه V<?= to_persian_digits($versionComparison['left']['version_number']) ?> با نسخه فعال V<?= to_persian_digits($versionComparison['right']['version_number']) ?></h3></div>
      <div class="card-body grid cols-2">
        <div class="notice success"><strong>خطوط افزوده‌شده</strong><pre><?= e(implode("\n", $versionComparison['added_lines']) ?: 'بدون تغییر') ?></pre></div>
        <div class="notice warning"><strong>خطوط حذف‌شده</strong><pre><?= e(implode("\n", $versionComparison['removed_lines']) ?: 'بدون تغییر') ?></pre></div>
      </div>
    </section>
  <?php endif; ?>
  <section class="card">
    <div class="card-header card-no-border"><div class="header-top"><div><h3>تاریخچه نسخه‌های قالب</h3><p>نسخه‌های فعال و دارای ارجاع حقوقی حذف نمی‌شوند.</p></div><div class="actions"><a class="btn secondary" href="<?= e(url('settings/contracts/versions') . (!empty($showArchivedVersions) ? '' : '?show_archived=1')) ?>"><i data-feather="archive"></i> <?= !empty($showArchivedVersions) ? 'پنهان‌کردن بایگانی‌ها' : 'نمایش نسخه‌های بایگانی‌شده' ?></a><a class="btn" href="<?= e(url('settings/contracts/template')) ?>"><i data-feather="plus"></i> پیش‌نویس جدید</a></div></div></div>
    <div class="table-wrap"><table><thead><tr><th>نسخه و وضعیت</th><th>ایجاد/انتشار</th><th>دلیل تغییر</th><th>استفاده</th><th>عملیات</th></tr></thead><tbody>
      <?php foreach ($templateVersions as $version): ?>
        <tr>
          <td><strong>V<?= to_persian_digits($version['version_number']) ?></strong><br><span class="badge <?= $version['status'] === 'published' ? 'success' : ($version['status'] === 'draft' ? 'warning' : 'secondary') ?>"><?= e($statusLabels[$version['status']] ?? $version['status']) ?></span><?php if (!empty($version['is_current'])): ?> <span class="badge success">فعال</span><?php endif; ?></td>
          <td><small>ایجاد: <?= e($version['created_by_name'] ?? 'سامانه') ?>، <?= !empty($version['created_at']) ? e(jdatetime($version['created_at'])) : '-' ?></small><br><small>انتشار: <?= e($version['published_by_name'] ?? '-') ?>، <?= !empty($version['published_at']) ? e(jdatetime($version['published_at'])) : '-' ?></small></td>
          <td><?= e($version['change_reason'] ?? '-') ?></td>
          <td><small>کل: <?= to_persian_digits($version['usage_count'] ?? 0) ?></small><br><small>سند تولیدشده: <?= to_persian_digits($version['generated_document_reference_count'] ?? 0) ?></small><br><small>نهایی: <?= to_persian_digits($version['finalized_document_reference_count'] ?? 0) ?></small></td>
          <td><div class="actions">
            <a class="btn small secondary" target="_blank" href="<?= e(url('settings/contractsPreview/' . (int) $version['id'])) ?>">پیش‌نمایش</a>
            <a class="btn small secondary" href="<?= e(url('settings/contracts/versions') . '?compare=' . (int) $version['id']) ?>">مقایسه</a>
            <button class="btn small secondary" type="button" data-contract-copy-textarea="version-source-<?= (int) $version['id'] ?>">کپی</button>
            <?php if ($version['status'] !== 'published' && $version['status'] !== 'archived'): ?><button class="btn small" type="button" data-open-modal="publish-template-<?= (int) $version['id'] ?>">انتشار</button><?php endif; ?>
            <form method="post" action="<?= e(url('settings/restoreContractTemplate/' . (int) $version['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="change_reason" value="بازگردانی نسخه V<?= e($version['version_number']) ?> به‌عنوان پیش‌نویس"><button class="btn small secondary" type="submit">بازیابی</button></form>
            <?php if (!empty($version['can_archive'])): ?><button class="btn small secondary" type="button" data-open-modal="archive-template-<?= (int) $version['id'] ?>">بایگانی</button><?php endif; ?>
            <?php if (!empty($version['can_delete'])): ?><button class="btn small danger" type="button" data-open-modal="delete-template-<?= (int) $version['id'] ?>">حذف</button><?php elseif (!empty($version['is_current'])): ?><small>نسخه فعال قابل حذف نیست.</small><?php elseif (!empty($version['usage_count'])): ?><small>نسخه ارجاع‌شده فقط بایگانی می‌شود.</small><?php endif; ?>
          </div><textarea id="version-source-<?= (int) $version['id'] ?>" hidden><?= e($version['body_source']) ?></textarea></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$templateVersions): ?><tr><td colspan="5" class="empty">هنوز نسخه سفارشی ایجاد نشده است.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <?php foreach ($templateVersions as $version): ?>
    <?php if ($version['status'] !== 'published' && $version['status'] !== 'archived'): ?><div class="modal" id="publish-template-<?= (int) $version['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>انتشار نسخه V<?= to_persian_digits($version['version_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('settings/publishContractTemplate/' . (int) $version['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice warning full">پس از انتشار، قراردادهای آینده از این قالب استفاده می‌کنند و اسناد غیرنهایی قبلی برای بازسازی علامت می‌خورند.</div><label class="full required-field">دلیل انتشار<input name="change_reason" required minlength="3" value="<?= e($version['change_reason'] ?? '') ?>"></label><label class="full proma-confirm-check"><input type="checkbox" required> پیش‌نمایش را بررسی کرده‌ام و انتشار این نسخه را تایید می‌کنم.</label></div><div class="modal-footer"><button class="btn" type="submit">انتشار قالب</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div><?php endif; ?>
    <?php if (!empty($version['can_archive'])): ?><div class="modal" id="archive-template-<?= (int) $version['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>بایگانی نسخه V<?= to_persian_digits($version['version_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('settings/archiveContractTemplate/' . (int) $version['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice info full">نسخه در تاریخچه باقی می‌ماند و برای بازیابی، یک پیش‌نویس تازه از آن ساخته خواهد شد.</div><label class="full required-field">علت بایگانی<input name="change_reason" required minlength="3"></label></div><div class="modal-footer"><button class="btn" type="submit">بایگانی نسخه</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div><?php endif; ?>
    <?php if (!empty($version['can_delete'])): ?><div class="modal" id="delete-template-<?= (int) $version['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>حذف نسخه قالب قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('settings/retireContractTemplate/' . (int) $version['id'])) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice danger full">نسخه V<?= to_persian_digits($version['version_number']) ?> با وضعیت <?= e($statusLabels[$version['status']] ?? $version['status']) ?> و صفر ارجاع، به‌طور دائمی حذف می‌شود.</div><label class="full required-field">علت حذف نسخه<input name="change_reason" required minlength="3"></label><label class="full proma-confirm-check"><input type="checkbox" name="confirm_retirement" value="1" required> از حذف این نسخه اطمینان دارم.</label></div><div class="modal-footer"><button class="btn danger" type="submit">حذف نسخه</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div></form></div></div><?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($section === 'variables'): ?>
  <?php foreach ($variableCatalog as $group => $variables): ?><section class="card"><div class="card-header card-no-border"><h3><?= e($group) ?></h3></div><div class="card-body"><div class="proma-contract-variable-grid"><?php foreach ($variables as $variable): ?><article><code><?= e($variable['code']) ?></code><strong><?= e($variable['title']) ?></strong><p><?= e($variable['description']) ?></p><small>نمونه: <?= e(strip_tags($variable['example'])) ?></small><div class="actions"><button class="btn small secondary" type="button" data-copy-shortcode="<?= e($variable['code']) ?>">کپی</button><button class="btn small" type="button" data-insert-contract-variable="<?= e($variable['code']) ?>">درج در ویرایشگر</button></div></article><?php endforeach; ?></div></div></section><?php endforeach; ?>
<?php endif; ?>

<?php if ($section === 'preview'): ?>
  <section class="card"><div class="card-header card-no-border"><div class="header-top"><div><h3>پیش‌نمایش واقعی قرارداد</h3><p>این صفحه از همان renderer و profile چاپ نهایی استفاده می‌کند و داده‌ها نمونه هستند.</p></div><a class="btn secondary" href="<?= e(url('settings/contracts/print')) ?>">تنظیم ظاهر چاپ</a></div></div><div class="card-body"><div class="notice info">برای خروجی تمیز، در پنجره چاپ گزینه «سرصفحه و پابرگ / Headers and footers» را غیرفعال کنید.</div><div class="proma-preview-frame-shell standalone"><iframe title="پیش‌نمایش قرارداد" src="<?= e($contractPreviewUrl) ?>"></iframe></div></div></section>
<?php endif; ?>

<?php if ($section === 'rebuild'): ?>
  <section class="card"><div class="card-header card-no-border"><h3>بازسازی اسناد با قالب جدید</h3></div><div class="card-body grid"><div class="proma-rebuild-summary"><span><small>اسناد نیازمند بازسازی</small><strong><?= to_persian_digits($templateStats['stale_documents'] ?? 0) ?></strong></span><span><small>نسخه هدف</small><strong><?= $effectiveVersion ? 'V' . to_persian_digits($effectiveVersion) : '-' ?></strong></span><span><small>اندازه هر بسته</small><strong>۲۵ سند</strong></span></div><div class="notice warning">اسناد نهایی‌شده هرگز در این صف بازنویسی نمی‌شوند. عملیات قابل ادامه است و هزاران قرارداد را در یک درخواست پردازش نمی‌کند.</div><?php if ($latestRebuildJob): ?><div class="proma-rebuild-progress"><div><strong>وضعیت: <?= e($latestRebuildJob['status']) ?></strong><span><?= to_persian_digits($latestRebuildJob['processed_documents']) ?> از <?= to_persian_digits($latestRebuildJob['total_documents']) ?></span></div><progress max="<?= max(1, (int) $latestRebuildJob['total_documents']) ?>" value="<?= (int) $latestRebuildJob['processed_documents'] ?>"></progress></div><?php endif; ?><form method="post" action="<?= e(url('settings/rebuildContractDocuments' . ($latestRebuildJob && $latestRebuildJob['status'] !== 'completed' ? '/' . (int) $latestRebuildJob['id'] : ''))) ?>"><?= csrf_field() ?><button class="btn" type="submit"><i data-feather="refresh-cw"></i> <?= $latestRebuildJob && $latestRebuildJob['status'] !== 'completed' ? 'ادامه بازسازی' : 'شروع بازسازی دسته‌ای' ?></button></form></div></section>
<?php endif; ?>

<textarea id="effective-template-source" hidden><?= e($effectiveTemplate['body_source'] ?? ContractDocument::defaultTemplate()) ?></textarea>
<textarea id="default-template-source" hidden><?= e(ContractDocument::defaultTemplate()) ?></textarea>
