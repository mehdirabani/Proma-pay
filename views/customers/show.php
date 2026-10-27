<?php
$customerPhones = [];
foreach ([['label' => 'شماره اصلی', 'value' => $customer['mobile'] ?? ''], ['label' => 'شماره دوم', 'value' => $customer['secondary_phone'] ?? '']] as $phoneEntry) {
    $number = normalize_iran_phone($phoneEntry['value']);
    if ($number !== '' && !isset($customerPhones[$number])) {
        $customerPhones[$number] = ['label' => $phoneEntry['label'], 'number' => $number];
    }
}
$customerPhones = array_values($customerPhones);
$activeTabData = is_array($activeTabData ?? null) ? $activeTabData : [];
$contracts = Contract::paginated(['customer_id' => (int) $customer['id'], 'page' => 1, 'per_page' => 5]);
$payments = Payment::recentForCustomer((int) $customer['id'], 5);
$tabLabels = [
    'overview' => 'نمای کلی', 'contracts' => 'قراردادها', 'installments' => 'اقساط', 'payments' => 'پرداخت‌ها',
    'documents' => 'مدارک', 'accounting' => 'حسابداری', 'calls' => 'تماس و پیگیری', 'referrals' => 'معرفی مشتری',
    'legal' => 'حقوقی', 'timeline' => 'تایم‌لاین', 'loyalty' => 'امتیاز و سطح‌بندی',
];
$activeTab = isset($activeTab) && isset($tabLabels[$activeTab]) ? $activeTab : 'overview';
?>
<section class="proma-customer-360" aria-label="پرونده یکپارچه مشتری" data-customer-360 data-customer-id="<?= (int) $customer['id'] ?>">
  <section class="card proma-customer-360__hero">
    <div class="proma-customer-360__identity">
      <?php $customerAvatar = avatar_key_for($customer['avatar_key'] ?? null, $customer['id'] ?? $customer['full_name']); ?>
      <div class="proma-customer-avatar <?= e($customerAvatar) ?>"><img data-avatar-image src="<?= e(user_avatar_asset_url($customer)) ?>" alt="آواتار <?= e($customer['full_name']) ?>"></div>
      <div><span class="proma-customer-360__eyebrow">پرونده یکپارچه مشتری</span><h2 id="customer-name"><?= e($customer['full_name']) ?></h2><p><?= to_persian_digits($customer['national_id'] ?? '') ?><?php if ($customerPhones): ?> · <?= implode(' · ', array_map(static fn ($phone) => to_persian_digits(format_iran_phone($phone['number'])), $customerPhones)) ?><?php endif; ?></p><span class="badge <?= e(badge_class(!empty($customer['archived_at']) ? 'archived' : ($customer['status'] ?? 'inactive'))) ?>"><?= e(status_label(!empty($customer['archived_at']) ? 'archived' : ($customer['status'] ?? 'inactive'))) ?></span><?php if (!empty($creditProfile)): ?> <span class="badge <?= ($creditProfile['risk_level'] ?? '') === 'low' ? 'success' : (($creditProfile['risk_level'] ?? '') === 'medium' ? 'warning' : 'danger') ?>" title="رتبهٔ اعتباری مشتری">Proma Score <?= to_persian_digits($creditProfile['score'] ?? 0) ?> · <?= e(($creditProfile['risk_level'] ?? '') === 'low' ? 'کم‌ریسک' : (($creditProfile['risk_level'] ?? '') === 'medium' ? 'ریسک متوسط' : 'پرریسک')) ?></span><?php endif; ?></div>
    </div>
    <div class="proma-customer-360__actions">
      <a class="btn secondary" href="<?= e(url('risk/profile/' . (int) $customer['id'])) ?>"><?= proma_icon('activity') ?><span>پروفایل ریسک</span></a>
      <?php if ($customerPhones): ?><details class="proma-customer-360__phone-picker"><summary class="btn secondary"><?= proma_icon('phone') ?><span>تماس</span><span class="proma-customer-360__phone-count"><?= to_persian_digits(count($customerPhones)) ?></span></summary><div class="proma-customer-360__phone-menu" aria-label="شماره‌های تماس مشتری"><?php foreach ($customerPhones as $phone): ?><a href="tel:<?= e($phone['number']) ?>"><?= proma_icon('phone') ?><span><strong><?= e($phone['label']) ?></strong><small dir="ltr"><?= e(to_persian_digits(format_iran_phone($phone['number']))) ?></small></span></a><?php endforeach; ?></div></details><?php endif; ?>
      <?php if (empty($customer['archived_at']) && ($customer['status'] ?? '') === 'active'): ?><a class="btn" href="<?= e(url('contracts', ['customer_id' => (int) $customer['id'], 'open' => 'create-contract'])) ?>#create-contract"><?= proma_icon('file-plus') ?> قرارداد جدید</a><?php endif; ?>
      <?php if (Auth::role() === 'admin' && !empty($customer['archived_at'])): ?><form method="post" action="<?= e(url('customers/restore/' . (int) $customer['id'])) ?>" class="proma-inline-form"><?= csrf_field() ?><button class="btn secondary" type="submit">بازیابی از بایگانی</button></form><?php endif; ?>
      <?php if (Auth::role() === 'admin'): ?><button class="btn success" type="button" data-open-modal="header-quick-payment"><?= proma_icon('credit-card') ?> ثبت پرداخت</button><?php endif; ?>
      <details class="proma-customer-360__more"><summary class="btn secondary"><?= proma_icon('more') ?> اقدامات بیشتر</summary><div class="proma-customer-360__menu">
        <button type="button" data-customer-open-tab="contracts">مشاهده قراردادها</button>
        <button type="button" data-customer-open-tab="documents">مدارک مشتری</button>
        <button type="button" data-customer-open-tab="accounting">گردش حساب</button>
        <?php foreach ($customerPhones as $phone): ?><button type="button" data-copy-phone="<?= e($phone['number']) ?>">کپی <?= e($phone['label']) ?></button><?php endforeach; ?>
      </div></details>
    </div>
  </section>

  <section class="proma-customer-360__metrics" aria-label="خلاصه وضعیت مشتری">
    <article class="card"><span><?= $accountingAvailable ? 'مانده حساب' : 'مانده اقساط' ?></span><strong><?= $accountingAvailable && $account ? money_toman($account['balance'] ?? 0) : money_toman($summary['installment_balance'] ?? 0) ?></strong><small><?= $accountingAvailable && $account ? e($account['label'] ?? '') : 'جمع اصل اقساط پرداخت‌نشده' ?></small></article>
    <article class="card"><span>قرارداد فعال</span><strong><?= to_persian_digits($summary['active_contract_count'] ?? 0) ?></strong><small>از <?= to_persian_digits($summary['contract_count'] ?? 0) ?> قرارداد</small></article>
    <article class="card"><span>قسط عقب‌افتاده</span><strong><?= to_persian_digits($summary['overdue_installment_count'] ?? 0) ?></strong><small>بر اساس سررسید و وضعیت ثبت‌شده</small></article>
    <article class="card"><span>وضعیت حساب</span><strong><?= e(!empty($customer['archived_at']) ? 'بایگانی‌شده' : (($customer['status'] ?? '') === 'active' ? 'فعال' : 'غیرفعال')) ?></strong><small><?= e($customer['mobile'] ?? 'شماره تماس ثبت نشده') ?></small></article>
  </section>

  <nav class="proma-customer-360__tabs" role="tablist" aria-label="بخش‌های پرونده مشتری">
    <?php foreach ($tabLabels as $key => $label): ?><a id="customer-tab-<?= e($key) ?>" role="tab" aria-controls="customer-tab-panel" aria-selected="<?= $key === $activeTab ? 'true' : 'false' ?>" tabindex="<?= $key === $activeTab ? '0' : '-1' ?>" class="<?= $key === $activeTab ? 'is-active' : '' ?>" data-customer-tab-btn="<?= e($key) ?>" href="<?= e(url('customers/tab/' . (int) $customer['id'] . '/' . $key)) ?>" data-tab-url="<?= e(url('customers/tab/' . (int) $customer['id'] . '/' . $key)) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </nav>

  <div id="customer-tab-panel" class="proma-customer-360__panel" role="tabpanel" aria-labelledby="customer-tab-<?= e($activeTab) ?>" data-customer-tab-panel data-active-tab="<?= e($activeTab) ?>">
    <?php if ($activeTab === 'overview'): ?><?php require __DIR__ . '/tabs/overview.php'; ?><?php else: ?><?php extract($activeTabData, EXTR_SKIP); require __DIR__ . '/tabs/' . $activeTab . '.php'; ?><?php endif; ?>
  </div>

  <?php if (!empty($customer['archived_at'])): ?>
    <section class="card" aria-label="وضعیت بایگانی مشتری">
      <div class="card-header"><div><h2>پرونده بایگانی‌شده</h2><p class="text-muted">دسترسی ورود و ایجاد قرارداد جدید بسته است؛ سوابق مالی و حقوقی حفظ شده‌اند.</p></div></div>
      <p>زمان بایگانی: <strong><?= e(jdatetime($customer['archived_at'])) ?></strong><?php if (!empty($customer['archived_by'])): ?> · مدیر شماره <?= to_persian_digits($customer['archived_by']) ?><?php endif; ?></p>
      <?php if (!empty($customer['archive_reason'])): ?><p>علت: <?= e($customer['archive_reason']) ?></p><?php endif; ?>
      <?php if (!empty($customer['restored_at'])): ?><p>آخرین بازیابی: <?= e(jdatetime($customer['restored_at'])) ?></p><?php endif; ?>
    </section>
  <?php elseif (!empty($customer['restored_at'])): ?>
    <section class="card" aria-label="بازیابی پرونده مشتری">
      <div class="card-header"><div><h2>پرونده از بایگانی بازیابی شده</h2><p class="text-muted">سوابق قبلی حفظ شده‌اند و وضعیت حساب به حالت پیش از بایگانی برگشته است.</p></div></div>
      <p>زمان آخرین بازیابی: <strong><?= e(jdatetime($customer['restored_at'])) ?></strong><?php if (!empty($customer['archive_reason'])): ?> · علت آخرین بایگانی: <?= e($customer['archive_reason']) ?><?php endif; ?></p>
    </section>
  <?php endif; ?>

  <section class="card" aria-label="تاریخچه چرخه عمر مشتری">
    <div class="card-header"><div><h2>تاریخچه وضعیت مشتری</h2><p class="text-muted">ثبت‌های حسابرسی فعال‌سازی، غیرفعال‌سازی، بایگانی، بازیابی و ادغام</p></div></div>
    <?php if (!empty($customerLifecycleHistory)): ?>
      <div class="proma-customer-360__list">
        <?php foreach ($customerLifecycleHistory as $lifecycleEvent): ?>
          <article class="proma-customer-360__row"><span><strong><?= e($lifecycleEvent['description'] ?: $lifecycleEvent['event_action']) ?></strong><small><?= e(jdatetime($lifecycleEvent['created_at'] ?? '')) ?> · مدیر/کاربر شماره <?= to_persian_digits($lifecycleEvent['actor_user_id'] ?? '—') ?></small></span><em class="badge <?= ($lifecycleEvent['event_result'] ?? '') === 'success' ? 'success' : 'warning' ?>"><?= e($lifecycleEvent['event_result'] ?? '') ?></em></article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted">رخداد چرخه عمر ثبت نشده است.</p>
    <?php endif; ?>
  </section>
</section>

