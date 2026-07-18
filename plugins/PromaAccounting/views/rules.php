<?php
require_once __DIR__ . '/components/ui.php';
$basisLabels = ['principal_amount' => 'مبلغ کل قرارداد', 'financed_amount' => 'مبلغ تأمین مالی‌شده', 'profit_amount' => 'سود قرارداد', 'collected_amount' => 'مبلغ وصول‌شده'];
$timingLabels = ['at_contract_creation' => 'هنگام ایجاد قرارداد', 'after_down_payment' => 'پس از دریافت پیش‌پرداخت', 'after_first_installment' => 'پس از پرداخت اولین قسط', 'after_full_settlement' => 'پس از تسویه کامل', 'manual_approval' => 'با تأیید دستی مدیر'];
$formatRuleValue = static function (array $rule): string {
    $value = (string) ($rule['commission_value'] ?? '');
    if (($rule['commission_type'] ?? '') === 'fixed') {
        return (string) (int) $value;
    }
    return rtrim(rtrim($value, '0'), '.');
};
$moneyInput = static function ($value): string {
    return $value === null || $value === '' ? '' : (string) (int) $value;
};
ob_start(); ?><a class="btn btn-light" href="<?= e(url('plugin/accounting/help', ['section' => 'rules'])) ?>"><i data-feather="help-circle"></i><span>راهنمای قوانین</span></a><?php $headerActions = trim(ob_get_clean());
?>
<div class="proma-accounting proma-accounting-rules">
  <?php pa_page_header('کمیسیون فروش', 'قوانین کمیسیون', 'قوانین اختصاصی فروشندگان را بدون تغییر در محاسبات و اسناد قبلی تعریف، ویرایش یا غیرفعال کنید.', 'sliders', $headerActions); ?>
  <section class="proma-accounting-notice"><i data-feather="info"></i><div><strong>تغییرات قوانین آینده‌نگر است</strong><p>ویرایش یا حذف قانون، کمیسیون‌ها و اسناد قبلی را بازنویسی نمی‌کند. اگر قانون قبلاً استفاده شده باشد، حذف به‌صورت غیرفعال‌سازی امن انجام می‌شود.</p></div></section>

  <section class="proma-accounting-panel">
    <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon is-purple"><i data-feather="plus"></i></span><div><h3>تعریف قانون جدید</h3><p>قانون جدید فقط بر فروش‌ها و محاسبات آینده اثر می‌گذارد.</p></div></div></div>
    <form method="post" action="<?= e(url('plugin/accounting/rules/save')) ?>" class="proma-accounting-form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="is_active" value="1">
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-name">نام قانون <span class="proma-required">*</span></label><div class="proma-accounting-control"><input id="rule-name" name="name" required placeholder="مثال: فروش حضوری شعبه مرکزی"></div><div class="proma-accounting-help">نامی کوتاه و قابل تشخیص برای گزارش‌ها.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-user">فروشنده</label><div class="proma-accounting-control"><select id="rule-user" name="user_id"><option value="">قانون عمومی</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['full_name']) ?> - <?= e(role_label($person['role'])) ?></option><?php endforeach; ?></select></div><div class="proma-accounting-help">قانون اختصاصی بر تنظیمات عمومی اولویت دارد.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-type">نوع کمیسیون</label><div class="proma-accounting-control"><select id="rule-type" name="commission_type" data-accounting-commission-type><option value="percentage">درصدی</option><option value="fixed">مبلغ ثابت</option></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-value">مقدار کمیسیون <span class="proma-required">*</span></label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-value" name="commission_value" inputmode="decimal" required><span data-accounting-commission-unit>درصد</span></div><div class="proma-accounting-help">درصد بین صفر تا صد یا مبلغ ثابت نامنفی.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-basis">مبنای محاسبه</label><div class="proma-accounting-control"><select id="rule-basis" name="calculation_basis"><?php foreach ($basisLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= $value === 'financed_amount' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-timing">زمان شناسایی</label><div class="proma-accounting-control"><select id="rule-timing" name="calculation_timing"><?php foreach ($timingLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-min">حداقل کمیسیون</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-min" name="minimum_amount" inputmode="numeric" data-accounting-money-input><span>تومان</span></div><div class="proma-accounting-help">خالی یعنی بدون حداقل.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-max">حداکثر کمیسیون</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-max" name="maximum_amount" inputmode="numeric" data-accounting-money-input><span>تومان</span></div><div class="proma-accounting-help">خالی یعنی بدون سقف.</div></div>
      <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-priority">اولویت</label><div class="proma-accounting-control"><input id="rule-priority" name="priority" value="0" inputmode="numeric"></div><div class="proma-accounting-help">عدد بالاتر بین قوانین یک فروشنده مقدم است.</div></div>
      <div class="proma-accounting-field span-12"><label class="proma-accounting-switch-row"><input type="checkbox" name="requires_approval" value="1"><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>نیازمند تأیید مدیر</strong><small>کمیسیون تا پیش از تأیید وارد مانده حساب فروشنده نمی‌شود.</small></span></label></div>
      <div class="span-12 proma-accounting-form-actions"><button class="btn btn-primary" type="submit"><i data-feather="save"></i><span>ذخیره قانون جدید</span></button></div>
    </form>
  </section>

  <section class="proma-accounting-panel">
    <div class="proma-accounting-section-heading"><div><span class="proma-accounting-section-icon"><i data-feather="list"></i></span><div><h3>قوانین ثبت‌شده</h3><p>هر قانون را همین‌جا ویرایش کنید یا در صورت نیاز حذف/غیرفعال کنید.</p></div></div></div>
    <?php if (!empty($rules)): ?>
      <div class="proma-accounting-rule-grid">
        <?php foreach ($rules as $rule): $ruleId = (int) ($rule['id'] ?? 0); ?>
          <article class="proma-accounting-rule-card">
            <div class="proma-accounting-rule-card-header">
              <div><h4><?= e($rule['name']) ?></h4><p><?= e($rule['user_name'] ?: 'قانون عمومی برای همه فروشندگان') ?></p></div>
              <?= pa_status(!empty($rule['is_active']) ? 'active' : 'inactive') ?>
            </div>
            <form method="post" action="<?= e(url('plugin/accounting/rules/save')) ?>" class="proma-accounting-form-grid proma-accounting-rule-edit-form">
              <?= csrf_field() ?>
              <input type="hidden" name="rule_id" value="<?= $ruleId ?>">
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-name-<?= $ruleId ?>">نام قانون</label><div class="proma-accounting-control"><input id="rule-name-<?= $ruleId ?>" name="name" value="<?= e($rule['name']) ?>" required></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-user-<?= $ruleId ?>">فروشنده</label><div class="proma-accounting-control"><select id="rule-user-<?= $ruleId ?>" name="user_id"><option value="">قانون عمومی</option><?php foreach (($staff ?? []) as $person): ?><option value="<?= (int) $person['id'] ?>"<?= (int) ($rule['user_id'] ?? 0) === (int) $person['id'] ? ' selected' : '' ?>><?= e($person['full_name']) ?> - <?= e(role_label($person['role'])) ?></option><?php endforeach; ?></select></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-type-<?= $ruleId ?>">نوع کمیسیون</label><div class="proma-accounting-control"><select id="rule-type-<?= $ruleId ?>" name="commission_type" data-accounting-commission-type><option value="percentage"<?= ($rule['commission_type'] ?? '') === 'percentage' ? ' selected' : '' ?>>درصدی</option><option value="fixed"<?= ($rule['commission_type'] ?? '') === 'fixed' ? ' selected' : '' ?>>مبلغ ثابت</option></select></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-value-<?= $ruleId ?>">مقدار</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-value-<?= $ruleId ?>" name="commission_value" value="<?= e($formatRuleValue($rule)) ?>" inputmode="decimal" required><span data-accounting-commission-unit><?= ($rule['commission_type'] ?? '') === 'fixed' ? 'تومان' : 'درصد' ?></span></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-basis-<?= $ruleId ?>">مبنای محاسبه</label><div class="proma-accounting-control"><select id="rule-basis-<?= $ruleId ?>" name="calculation_basis"><?php foreach ($basisLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= ($rule['calculation_basis'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-label" for="rule-timing-<?= $ruleId ?>">زمان شناسایی</label><div class="proma-accounting-control"><select id="rule-timing-<?= $ruleId ?>" name="calculation_timing"><?php foreach ($timingLabels as $value => $label): ?><option value="<?= e($value) ?>"<?= ($rule['calculation_timing'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
              <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-min-<?= $ruleId ?>">حداقل</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-min-<?= $ruleId ?>" name="minimum_amount" value="<?= e($moneyInput($rule['minimum_amount'] ?? null)) ?>" inputmode="numeric" data-accounting-money-input><span>تومان</span></div></div>
              <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-max-<?= $ruleId ?>">حداکثر</label><div class="proma-accounting-control proma-accounting-input-group"><input id="rule-max-<?= $ruleId ?>" name="maximum_amount" value="<?= e($moneyInput($rule['maximum_amount'] ?? null)) ?>" inputmode="numeric" data-accounting-money-input><span>تومان</span></div></div>
              <div class="proma-accounting-field span-4"><label class="proma-accounting-label" for="rule-priority-<?= $ruleId ?>">اولویت</label><div class="proma-accounting-control"><input id="rule-priority-<?= $ruleId ?>" name="priority" value="<?= (int) ($rule['priority'] ?? 0) ?>" inputmode="numeric"></div></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-switch-row"><input type="checkbox" name="requires_approval" value="1"<?= !empty($rule['requires_approval']) ? ' checked' : '' ?>><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>نیازمند تأیید مدیر</strong></span></label></div>
              <div class="proma-accounting-field span-6"><label class="proma-accounting-switch-row"><input type="checkbox" name="is_active" value="1"<?= !empty($rule['is_active']) ? ' checked' : '' ?>><span class="proma-accounting-switch" aria-hidden="true"></span><span><strong>قانون فعال باشد</strong></span></label></div>
              <div class="span-12 proma-accounting-row-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i data-feather="save"></i><span>ذخیره ویرایش</span></button>
              </div>
            </form>
            <form method="post" action="<?= e(url('plugin/accounting/rules/delete/' . $ruleId)) ?>" class="proma-accounting-row-actions" data-accounting-confirm data-confirm-title="حذف قانون کمیسیون" data-confirm-description="اگر این قانون در سوابق مالی استفاده شده باشد، برای حفظ تاریخچه فقط غیرفعال می‌شود." data-confirm-person="<?= e($rule['user_name'] ?: 'همه فروشندگان') ?>" data-confirm-operation="حذف/غیرفعال‌سازی قانون <?= e($rule['name']) ?>" data-confirm-amount="—" data-confirm-effect="عدم استفاده در محاسبات آینده">
              <?= csrf_field() ?>
              <button class="proma-accounting-icon-button is-danger" type="submit" aria-label="حذف قانون" title="حذف قانون"><i data-feather="trash-2"></i></button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?= pa_empty_state('قانون کمیسیون تعریف نشده است', 'تا زمان تعریف قانون اختصاصی، تنظیمات عمومی کمیسیون استفاده می‌شود.', 'sliders') ?>
    <?php endif; ?>
  </section>
</div>
