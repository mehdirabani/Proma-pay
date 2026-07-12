<?php
$settings = $settings ?? [];
$previewInput = $previewInput ?? [];
$type = ($settings['default_commission_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
$basisLabels = [
    'principal_amount' => 'مبلغ کل قرارداد',
    'financed_amount' => 'مبلغ تأمین مالی‌شده',
    'profit_amount' => 'سود قرارداد',
    'collected_amount' => 'مبلغ وصول‌شده',
];
$timingLabels = [
    'at_contract_creation' => 'هنگام ایجاد قرارداد',
    'after_down_payment' => 'پس از دریافت پیش‌پرداخت',
    'after_first_installment' => 'پس از پرداخت اولین قسط',
    'after_full_settlement' => 'پس از تسویه کامل قرارداد',
    'manual_approval' => 'با تأیید دستی مدیر',
];
$roundingLabels = ['none' => 'بدون گرد کردن', 'nearest' => 'نزدیک‌ترین مقدار', 'down' => 'رو به پایین', 'up' => 'رو به بالا'];
$roundingMethod = $settings['rounding_method'] ?? 'none';
$signedMoney = static function ($value) {
    $amount = (int) $value;
    if ($amount === 0) {
        return money_toman(0);
    }
    return ($amount > 0 ? '+' : '-') . money_toman(abs($amount));
};
?>
<div class="proma-accounting proma-accounting-settings" data-accounting-settings>
  <header class="proma-accounting-page-header">
    <div>
      <span class="proma-accounting-eyebrow">حسابداری کاربران</span>
      <h2>تنظیمات حسابداری</h2>
      <p>قانون پیش‌فرض فقط زمانی استفاده می‌شود که برای فروشنده قانون اختصاصی فعال وجود نداشته باشد.</p>
    </div>
    <div class="proma-accounting-header-actions">
      <a class="btn btn-light" href="<?= e(url('plugin/accounting/help', ['section' => 'commission'])) ?>"><i data-feather="book-open"></i><span>راهنما</span></a>
      <a class="btn btn-light" href="<?= e(url('plugin/accounting/setup')) ?>"><i data-feather="check-square"></i><span>راه‌اندازی مرحله‌ای</span></a>
    </div>
  </header>

  <section class="proma-accounting-summary" aria-labelledby="accounting-summary-title">
    <div class="proma-accounting-section-heading">
      <div><span class="proma-accounting-section-icon"><i data-feather="activity"></i></span><div><h3 id="accounting-summary-title">خلاصه تنظیمات فعال</h3><p>تصویر سریع قانون پیش‌فرض برای فروش‌های آینده</p></div></div>
    </div>
    <dl class="proma-accounting-summary-grid">
      <div><dt>نوع</dt><dd><?= $type === 'percentage' ? 'درصدی' : 'مبلغ ثابت' ?></dd></div>
      <div><dt>مقدار</dt><dd><?= e($settings['default_commission_value'] ?? '0') ?> <?= $type === 'percentage' ? 'درصد' : 'تومان' ?></dd></div>
      <div><dt>مبنا</dt><dd><?= e($basisLabels[$settings['default_calculation_basis'] ?? 'financed_amount'] ?? 'مبلغ تأمین مالی‌شده') ?></dd></div>
      <div><dt>حداقل</dt><dd><?= ($settings['minimum_commission_enabled'] ?? '0') === '1' ? money_toman($settings['minimum_commission'] ?? 0) : 'غیرفعال' ?></dd></div>
      <div><dt>حداکثر</dt><dd><?= ($settings['maximum_commission_enabled'] ?? '0') === '1' ? money_toman($settings['maximum_commission'] ?? 0) : 'غیرفعال' ?></dd></div>
      <div><dt>گرد کردن</dt><dd><?= e($roundingLabels[$roundingMethod] ?? 'بدون گرد کردن') ?><?= $roundingMethod !== 'none' ? ' ' . money_toman($settings['rounding_unit'] ?? 1000) : '' ?></dd></div>
      <div><dt>زمان شناسایی</dt><dd><?= e($timingLabels[$settings['default_calculation_timing'] ?? 'at_contract_creation'] ?? '-') ?></dd></div>
      <div><dt>ثبت در حساب</dt><dd><?= ($settings['automatic_ledger_posting'] ?? '0') === '1' ? 'خودکار' : 'دستی' ?></dd></div>
    </dl>
  </section>

  <form method="post" action="<?= e(url('plugin/accounting/settings/save')) ?>" class="proma-accounting-settings-form" data-accounting-dirty-form novalidate>
    <?= csrf_field() ?>
    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading">
        <div><span class="proma-accounting-section-icon is-blue"><i data-feather="percent"></i></span><div><h3>تنظیمات عمومی کمیسیون</h3><p>نوع، مقدار و مبنای پیش‌فرض محاسبه را مشخص کنید.</p></div></div>
      </div>
      <div class="proma-accounting-form-grid">
        <div class="proma-accounting-field span-4">
          <label class="proma-accounting-label" for="accounting-default-type">نوع پیش‌فرض کمیسیون</label>
          <div class="proma-accounting-control"><select id="accounting-default-type" name="default_commission_type" data-accounting-commission-type><option value="percentage"<?= $type === 'percentage' ? ' selected' : '' ?>>درصدی</option><option value="fixed"<?= $type === 'fixed' ? ' selected' : '' ?>>مبلغ ثابت</option></select></div>
          <div class="proma-accounting-help">درصدی از مبنای انتخاب‌شده یا مبلغ ثابت برای هر فروش.</div>
        </div>
        <div class="proma-accounting-field span-4">
          <label class="proma-accounting-label" for="accounting-default-value">مقدار پیش‌فرض کمیسیون</label>
          <div class="proma-accounting-control proma-accounting-input-group"><input id="accounting-default-value" name="default_commission_value" value="<?= e($settings['default_commission_value'] ?? '0') ?>" inputmode="decimal" required><span data-accounting-commission-unit><?= $type === 'percentage' ? 'درصد' : 'تومان' ?></span></div>
          <div class="proma-accounting-help">وقتی قانون اختصاصی وجود ندارد، این مقدار اعمال می‌شود.</div>
          <details class="proma-accounting-context-help"><summary>این گزینه چیست؟</summary><p>مثال: کمیسیون ۲ درصد برای مبنای ۲۰ میلیون تومان، مبلغ اولیه ۴۰۰ هزار تومان می‌سازد.</p></details>
        </div>
        <div class="proma-accounting-field span-4">
          <label class="proma-accounting-label" for="accounting-default-basis">مبنای پیش‌فرض محاسبه کمیسیون</label>
          <div class="proma-accounting-control"><select id="accounting-default-basis" name="default_calculation_basis"><?php foreach ($basisLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= ($settings['default_calculation_basis'] ?? 'financed_amount') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
          <div class="proma-accounting-help" data-accounting-basis-help>مبلغ قرارداد پس از کسر پیش‌پرداخت.</div>
          <details class="proma-accounting-context-help"><summary>توضیح مبناها</summary><p><strong>کل قرارداد:</strong> تمام مبلغ قرارداد. <strong>تأمین مالی‌شده:</strong> پس از کسر پیش‌پرداخت. <strong>سود:</strong> فقط سود اقساط. <strong>وصول‌شده:</strong> فقط دریافتی قطعی.</p></details>
        </div>
      </div>
    </section>

    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading">
        <div><span class="proma-accounting-section-icon is-green"><i data-feather="shield"></i></span><div><h3>محدودیت کمیسیون</h3><p>کف و سقف اختیاری برای هر فروش تعریف کنید.</p></div></div>
      </div>
      <div class="proma-accounting-form-grid">
        <div class="proma-accounting-field span-6">
          <label class="proma-accounting-switch-row">
            <input type="checkbox" name="minimum_commission_enabled" value="1"<?= ($settings['minimum_commission_enabled'] ?? '0') === '1' ? ' checked' : '' ?> data-accounting-toggle="minimum-commission-field">
            <span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>اعمال حداقل کمیسیون</strong><small>اگر مبلغ محاسبه‌شده کمتر باشد، حداقل جایگزین می‌شود.</small></span>
          </label>
          <div class="proma-accounting-dependent" id="minimum-commission-field">
            <label class="proma-accounting-label" for="minimum-commission">حداقل کمیسیون هر فروش</label>
            <div class="proma-accounting-control proma-accounting-input-group"><input id="minimum-commission" name="minimum_commission" value="<?= e($settings['minimum_commission'] ?? '0') ?>" inputmode="numeric"><span>تومان</span></div>
            <div class="proma-accounting-help">مثال: مبلغ اولیه ۱۸۰,۰۰۰ و حداقل ۲۵۰,۰۰۰؛ نتیجه ۲۵۰,۰۰۰ تومان.</div>
          </div>
        </div>
        <div class="proma-accounting-field span-6">
          <label class="proma-accounting-switch-row">
            <input type="checkbox" name="maximum_commission_enabled" value="1"<?= ($settings['maximum_commission_enabled'] ?? '0') === '1' ? ' checked' : '' ?> data-accounting-toggle="maximum-commission-field">
            <span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>اعمال حداکثر کمیسیون</strong><small>اگر مبلغ محاسبه‌شده بیشتر باشد، سقف جایگزین می‌شود.</small></span>
          </label>
          <div class="proma-accounting-dependent" id="maximum-commission-field">
            <label class="proma-accounting-label" for="maximum-commission">حداکثر کمیسیون هر فروش</label>
            <div class="proma-accounting-control proma-accounting-input-group"><input id="maximum-commission" name="maximum_commission" value="<?= e($settings['maximum_commission'] ?? '0') ?>" inputmode="numeric"><span>تومان</span></div>
            <div class="proma-accounting-help">مثال: مبلغ اولیه ۱,۲۰۰,۰۰۰ و سقف ۸۰۰,۰۰۰؛ نتیجه ۸۰۰,۰۰۰ تومان.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading">
        <div><span class="proma-accounting-section-icon is-amber"><i data-feather="corner-up-left"></i></span><div><h3>گرد کردن مبلغ</h3><p>گرد کردن پس از اعمال حداقل و حداکثر انجام می‌شود.</p></div></div>
      </div>
      <div class="proma-accounting-form-grid">
        <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rounding-method">روش گرد کردن</label><div class="proma-accounting-control"><select id="rounding-method" name="rounding_method" data-accounting-rounding-method><?php foreach ($roundingLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= $roundingMethod === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="proma-accounting-help">برای مبلغ ۴۰۳,۴۸۰ تومان، نزدیک‌ترین هزار برابر ۴۰۳,۰۰۰ تومان است.</div></div>
        <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rounding-unit">واحد گرد کردن</label><div class="proma-accounting-control"><select id="rounding-unit" name="rounding_unit" data-accounting-rounding-unit><?php foreach ([100, 500, 1000, 5000, 10000] as $unit): ?><option value="<?= $unit ?>"<?= (int) ($settings['rounding_unit'] ?? 1000) === $unit ? ' selected' : '' ?>><?= number_format($unit) ?> تومان</option><?php endforeach; ?></select></div><div class="proma-accounting-help">در حالت «بدون گرد کردن» این گزینه غیرفعال است.</div></div>
      </div>
    </section>

    <section class="proma-accounting-panel">
      <div class="proma-accounting-section-heading">
        <div><span class="proma-accounting-section-icon is-purple"><i data-feather="clock"></i></span><div><h3>شناسایی و ثبت در حساب</h3><p>زمان ایجاد کمیسیون و کنترل ثبت دفترکل را تعیین کنید.</p></div></div>
      </div>
      <div class="proma-accounting-form-grid">
        <div class="proma-accounting-field span-12"><label class="proma-accounting-label" for="accounting-timing">زمان شناسایی کمیسیون</label><div class="proma-accounting-control"><select id="accounting-timing" name="default_calculation_timing"><?php foreach ($timingLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= ($settings['default_calculation_timing'] ?? 'at_contract_creation') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="proma-accounting-help">در حالت تأیید دستی، کمیسیون در وضعیت در انتظار تأیید باقی می‌ماند.</div></div>
        <div class="proma-accounting-field span-6"><label class="proma-accounting-switch-row"><input type="checkbox" name="require_commission_approval" value="1"<?= ($settings['require_commission_approval'] ?? '0') === '1' ? ' checked' : '' ?>><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>نیاز به تأیید مدیر</strong><small>تا پیش از تأیید، مانده حساب کاربر تغییر نمی‌کند.</small></span></label></div>
        <div class="proma-accounting-field span-6"><label class="proma-accounting-switch-row"><input type="checkbox" name="automatic_ledger_posting" value="1"<?= ($settings['automatic_ledger_posting'] ?? '0') === '1' ? ' checked' : '' ?>><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>ثبت خودکار در حساب کاربر</strong><small>ثبت با کلید یکتا انجام می‌شود و تکراری نخواهد شد.</small></span></label></div>
      </div>
    </section>

    <div class="proma-accounting-sticky-actions"><span><i data-feather="info"></i> تنظیمات جدید فقط برای محاسبات آینده است.</span><button class="btn btn-primary" type="submit"><i data-feather="save"></i><span>ذخیره تنظیمات</span></button></div>
  </form>

  <section class="proma-accounting-panel proma-accounting-preview-panel" id="commission-preview">
    <div class="proma-accounting-section-heading">
      <div><span class="proma-accounting-section-icon is-cyan"><i data-feather="calculator"></i></span><div><h3>پیش‌نمایش محاسبه کمیسیون</h3><p>این محاسبه با همان سرویس فروش واقعی انجام می‌شود و هیچ داده‌ای ذخیره نمی‌کند.</p></div></div>
    </div>
    <form method="post" action="<?= e(url('plugin/accounting/settings/preview')) ?>" class="proma-accounting-form-grid">
      <?= csrf_field() ?>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="preview-seller">فروشنده</label><div class="proma-accounting-control"><select id="preview-seller" name="seller_user_id"><option value="0">بدون قانون اختصاصی</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>"<?= (int) ($previewInput['seller_user_id'] ?? 0) === (int) $person['id'] ? ' selected' : '' ?>><?= e($person['full_name']) ?></option><?php endforeach; ?></select></div></div>
      <?php foreach (['principal_amount' => 'مبلغ کل قرارداد', 'down_payment_amount' => 'پیش‌پرداخت', 'financed_amount' => 'مبلغ تأمین مالی‌شده', 'profit_amount' => 'سود قرارداد', 'collected_amount' => 'مبلغ وصول‌شده'] as $key => $label): ?><div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="preview-<?= e($key) ?>"><?= e($label) ?></label><div class="proma-accounting-control proma-accounting-input-group"><input id="preview-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($previewInput[$key] ?? ($key === 'principal_amount' ? '20000000' : ($key === 'financed_amount' ? '15000000' : '5000000'))) ?>" inputmode="numeric"><span>تومان</span></div></div><?php endforeach; ?>
      <div class="span-12 proma-accounting-form-actions"><button class="btn btn-primary" type="submit"><i data-feather="play"></i><span>محاسبه پیش‌نمایش</span></button></div>
    </form>
    <?php if (!empty($preview)): ?><div class="proma-accounting-breakdown" aria-live="polite">
      <div><span>قانون استفاده‌شده</span><strong><?= e($preview['rule_name']) ?> (<?= $preview['rule_source'] === 'seller' ? 'اختصاصی' : 'پیش‌فرض' ?>)</strong></div>
      <div><span>مبلغ مبنا</span><strong><?= money_toman($preview['basis_amount']) ?></strong></div>
      <div><span>کمیسیون اولیه</span><strong><?= money_toman($preview['raw_commission']) ?></strong></div>
      <div><span>اثر حداقل</span><strong><?= e($signedMoney($preview['minimum_adjustment'])) ?></strong></div>
      <div><span>اثر حداکثر</span><strong><?= e($signedMoney($preview['maximum_adjustment'])) ?></strong></div>
      <div><span>اثر گرد کردن</span><strong><?= e($signedMoney($preview['rounding_adjustment'])) ?></strong></div>
      <div class="is-final"><span>کمیسیون نهایی</span><strong><?= money_toman($preview['final_commission']) ?></strong></div>
    </div><?php endif; ?>
  </section>

  <dialog class="proma-accounting-dialog" data-accounting-unsaved-dialog><form method="dialog"><h3>تغییرات ذخیره‌نشده دارید</h3><p>با خروج از این صفحه، تغییرات ذخیره‌نشده از بین می‌رود.</p><div><button class="btn btn-light" value="stay">ماندن در صفحه</button><button class="btn btn-danger" value="leave">خروج بدون ذخیره</button></div></form></dialog>
</div>
