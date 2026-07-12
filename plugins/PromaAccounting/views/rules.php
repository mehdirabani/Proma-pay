<?php
$basisLabels = ['principal_amount' => 'مبلغ کل قرارداد', 'financed_amount' => 'مبلغ تأمین مالی‌شده', 'profit_amount' => 'سود قرارداد', 'collected_amount' => 'مبلغ وصول‌شده'];
$timingLabels = ['at_contract_creation' => 'هنگام ایجاد قرارداد', 'after_down_payment' => 'پس از دریافت پیش‌پرداخت', 'after_first_installment' => 'پس از پرداخت اولین قسط', 'after_full_settlement' => 'پس از تسویه کامل', 'manual_approval' => 'با تأیید دستی مدیر'];
?>
<div class="proma-accounting proma-accounting-rules">
  <header class="proma-accounting-page-header">
    <div><span class="proma-accounting-eyebrow">کمیسیون فروش</span><h2>قوانین اختصاصی فروشندگان</h2><p>قانون اختصاصی فعال همیشه بر قانون پیش‌فرض اولویت دارد.</p></div>
    <a class="btn btn-light" href="<?= e(url('plugin/accounting/help', ['section' => 'rules'])) ?>"><i data-feather="help-circle"></i><span>راهنمای قوانین</span></a>
  </header>

  <section class="proma-accounting-notice"><i data-feather="info"></i><div><strong>ترتیب محاسبه ثابت است</strong><p>انتخاب قانون، تعیین مبنا، کمیسیون اولیه، حداقل، حداکثر و سپس گرد کردن.</p></div></section>

  <section class="proma-accounting-panel">
    <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon is-blue"><i data-feather="plus"></i></span><div><h3>افزودن قانون</h3><p>قانون جدید فقط روی محاسبات آینده اثر می‌گذارد.</p></div></div></div>
    <form method="post" action="<?= e(url('plugin/accounting/rules/save')) ?>" class="proma-accounting-form-grid" novalidate>
      <?= csrf_field() ?>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-name">نام قانون <span class="proma-required">*</span></label><div class="proma-accounting-control"><input id="rule-name" name="name" required></div><div class="proma-accounting-help">مثال: کمیسیون فروش حضوری شعبه مرکزی</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-user">فروشنده</label><div class="proma-accounting-control"><select id="rule-user" name="user_id"><option value="">قانون عمومی</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['full_name']) ?> - <?= e(role_label($person['role'])) ?></option><?php endforeach; ?></select></div><div class="proma-accounting-help">با انتخاب کاربر، این قانون فقط برای همان فروشنده است.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-type">نوع کمیسیون</label><div class="proma-accounting-control"><select id="rule-type" name="commission_type" data-accounting-commission-type><option value="percentage">درصدی</option><option value="fixed">مبلغ ثابت</option></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-value">مقدار کمیسیون <span class="proma-required">*</span></label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-value" name="commission_value" inputmode="decimal" required><span data-accounting-commission-unit>درصد</span></div><div class="proma-accounting-help">درصد بین صفر تا صد یا مبلغ ثابت نامنفی.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-basis">مبنای محاسبه</label><div class="proma-accounting-control"><select id="rule-basis" name="calculation_basis"><?php foreach ($basisLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= $value === 'financed_amount' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-timing">زمان شناسایی</label><div class="proma-accounting-control"><select id="rule-timing" name="calculation_timing"><?php foreach ($timingLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-min">حداقل کمیسیون هر فروش</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-min" name="minimum_amount" inputmode="numeric"><span>تومان</span></div><div class="proma-accounting-help">خالی بگذارید تا حداقل اعمال نشود.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-max">حداکثر کمیسیون هر فروش</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-max" name="maximum_amount" inputmode="numeric"><span>تومان</span></div><div class="proma-accounting-help">خالی بگذارید تا سقف اعمال نشود.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-priority">اولویت</label><div class="proma-accounting-control"><input id="rule-priority" name="priority" value="0" inputmode="numeric"></div><div class="proma-accounting-help">عدد بالاتر، اولویت بیشتر بین قوانین همان کاربر.</div></div>
      <div class="proma-accounting-field span-12"><label class="proma-accounting-switch-row"><input type="checkbox" name="requires_approval" value="1"><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>کمیسیون نیازمند تأیید مدیر باشد</strong><small>تا پیش از تأیید وارد مانده حساب فروشنده نمی‌شود.</small></span></label></div>
      <div class="span-12 proma-accounting-form-actions"><button class="btn btn-primary" type="submit"><i data-feather="save"></i><span>ذخیره قانون</span></button></div>
    </form>
  </section>

  <section class="proma-accounting-panel">
    <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="list"></i></span><div><h3>قوانین ثبت‌شده</h3><p>سوابق برای شفافیت مالی حفظ می‌شوند.</p></div></div></div>
    <div class="table-responsive"><table class="table proma-accounting-table"><thead><tr><th>نام</th><th>فروشنده</th><th>نوع و مقدار</th><th>مبنا</th><th>زمان شناسایی</th><th>تأیید</th></tr></thead><tbody><?php foreach (($rules ?? []) as $rule): ?><tr><td><strong><?= e($rule['name']) ?></strong></td><td><?= e($rule['user_name'] ?: 'عمومی') ?></td><td><?= $rule['commission_type'] === 'percentage' ? e($rule['commission_value']) . ' درصد' : money_toman($rule['commission_value']) ?></td><td><?= e($basisLabels[$rule['calculation_basis']] ?? $rule['calculation_basis']) ?></td><td><?= e($timingLabels[$rule['calculation_timing']] ?? '-') ?></td><td><span class="proma-accounting-status <?= !empty($rule['requires_approval']) ? 'is-pending' : 'is-active' ?>"><?= !empty($rule['requires_approval']) ? 'نیازمند تأیید' : 'خودکار' ?></span></td></tr><?php endforeach; ?><?php if (empty($rules)): ?><tr><td colspan="6" class="empty">قانون اختصاصی ثبت نشده است؛ تنظیمات پیش‌فرض استفاده می‌شود.</td></tr><?php endif; ?></tbody></table></div>
  </section>
</div>
