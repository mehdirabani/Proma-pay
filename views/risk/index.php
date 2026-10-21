<?php
$summary = $summary ?? ['low' => 0, 'medium' => 0, 'high' => 0, 'pending' => 0, 'average' => 0];
$pagination = $pagination ?? ['total' => count($customers ?? []), 'page' => 1, 'pages' => 1, 'per_page' => 25];
$pageUrl = static fn (int $page): string => url('risk', array_filter([
    'q' => $query ?? '',
    'per_page' => $pagination['per_page'] ?? 25,
    'page' => $page > 1 ? $page : null,
], static fn ($value): bool => $value !== null && $value !== ''));
$riskMeta = static function (?array $profile): array {
    if (!$profile) return ['بدون ارزیابی', 'pending', 0];
    $level = (string) ($profile['risk_level'] ?? 'high');
    $map = [
        'low' => ['کم‌ریسک', 'low'],
        'medium' => ['ریسک متوسط', 'medium'],
        'high' => ['پرریسک', 'high'],
    ];
    [$label, $class] = $map[$level] ?? $map['high'];
    return [$label, $class, max(0, min(1000, (int) ($profile['score'] ?? 0)))];
};
?>
<section class="proma-risk-workspace" aria-labelledby="risk-page-title">
  <header class="proma-page-intro proma-risk-intro">
    <div>
      <span class="proma-eyebrow">اعتبارسنجی مشتریان</span>
      <h2 id="risk-page-title">پروفایل ریسک اعتباری</h2>
      <p>امتیازها بر پایهٔ سوابق ثبت‌شده محاسبه می‌شوند و برای تصمیم‌گیری نهایی اعتبار، نظر مدیر لازم است.</p>
    </div>
    <a class="btn secondary" href="<?= e(url('settings', ['tab' => 'credit'])) ?>"><?= proma_icon('settings') ?><span>تنظیم قواعد</span></a>
  </header>

  <div class="proma-risk-summary" aria-label="خلاصه فهرست مشتریان">
    <article class="proma-risk-metric is-primary"><span class="proma-risk-metric__icon"><?= proma_icon('user') ?></span><div><small>مشتری فعال</small><strong><?= to_persian_digits($pagination['total'] ?? 0) ?></strong><span>نتیجهٔ جستجوی فعلی</span></div></article>
    <article class="proma-risk-metric is-low"><span class="proma-risk-metric__icon"><?= proma_icon('trending-up') ?></span><div><small>کم‌ریسک در این صفحه</small><strong><?= to_persian_digits($summary['low'] ?? 0) ?></strong><span>امتیاز ۷۰۰ تا ۱۰۰۰</span></div></article>
    <article class="proma-risk-metric is-medium"><span class="proma-risk-metric__icon"><?= proma_icon('activity') ?></span><div><small>ریسک متوسط در این صفحه</small><strong><?= to_persian_digits($summary['medium'] ?? 0) ?></strong><span>امتیاز ۵۰۰ تا ۶۹۹</span></div></article>
    <article class="proma-risk-metric is-high"><span class="proma-risk-metric__icon"><?= proma_icon('alert-circle') ?></span><div><small>پرریسک در این صفحه</small><strong><?= to_persian_digits($summary['high'] ?? 0) ?></strong><span>امتیاز کمتر از ۵۰۰</span></div></article>
    <article class="proma-risk-metric is-pending"><span class="proma-risk-metric__icon"><?= proma_icon('history') ?></span><div><small>نیازمند محاسبه</small><strong><?= to_persian_digits($summary['pending'] ?? 0) ?></strong><span>هنوز ارزیابی ذخیره نشده</span></div></article>
  </div>

  <section class="card proma-risk-filter-card">
    <div class="card-header card-no-border"><div class="header-top"><div><h3>جستجو و فیلتر</h3><p>نام، شماره تماس یا کد ملی را وارد کنید.</p></div><span class="proma-risk-average"><small>میانگین امتیاز ارزیابی‌شده در این صفحه</small><strong><?= to_persian_digits($summary['average'] ?? 0) ?><span> از ۱۰۰۰</span></strong></span></div></div>
    <div class="card-body pt-0">
      <form class="proma-risk-filter" method="get" action="<?= e(url('risk')) ?>">
        <input type="hidden" name="route" value="risk"><input type="hidden" name="page" value="1">
        <label class="proma-risk-search"><span>جستجوی مشتری</span><input name="q" value="<?= e($query ?? '') ?>" placeholder="نام، موبایل یا کد ملی" autocomplete="off"></label>
        <label><span>تعداد در صفحه</span><select name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= $size ?>"<?= selected($pagination['per_page'] ?? 25, $size) ?>><?= to_persian_digits($size) ?> مورد</option><?php endforeach; ?></select></label>
        <div class="proma-risk-filter__actions"><button class="btn" type="submit"><?= proma_icon('search') ?><span>جستجو</span></button><?php if (!empty($query)): ?><a class="btn secondary" href="<?= e(url('risk')) ?>">پاک‌کردن</a><?php endif; ?></div>
      </form>
    </div>
  </section>

  <section class="card proma-risk-list-card" aria-labelledby="risk-list-title">
    <div class="card-header card-no-border"><div class="header-top"><div><h3 id="risk-list-title">مشتریان و رتبهٔ اعتباری</h3><p>نمایش <?= to_persian_digits($pagination['total'] ? (($pagination['page'] - 1) * $pagination['per_page'] + 1) : 0) ?> تا <?= to_persian_digits(min($pagination['page'] * $pagination['per_page'], $pagination['total'])) ?> از <?= to_persian_digits($pagination['total']) ?> مشتری</p></div><a class="btn small secondary" href="<?= e(url('customers')) ?>">فهرست مشتریان</a></div></div>
    <div class="table-wrap proma-risk-table-wrap"><table class="proma-risk-table">
      <thead><tr><th scope="col">مشتری</th><th scope="col">شماره تماس</th><th scope="col">وضعیت حساب</th><th scope="col">سطح ریسک</th><th scope="col">امتیاز اعتباری</th><th scope="col">پروفایل</th></tr></thead>
      <tbody>
      <?php foreach (($customers ?? []) as $customer): $credit = $customer['credit_profile'] ?? null; [$riskLabel, $riskClass, $score] = $riskMeta($credit); ?>
        <tr>
          <td data-label="مشتری"><span class="proma-risk-customer"><span class="proma-risk-avatar"><?= e(mb_substr((string) ($customer['full_name'] ?? 'م'), 0, 1, 'UTF-8')) ?></span><span><strong><?= e($customer['full_name'] ?? '') ?></strong><small>شناسه <?= to_persian_digits((int) ($customer['id'] ?? 0)) ?></small></span></span></td>
          <td data-label="شماره تماس"><bdi class="proma-risk-phone"><?= to_persian_digits($customer['mobile'] ?? '') ?></bdi></td>
          <td data-label="وضعیت حساب"><span class="proma-risk-account-state"><i aria-hidden="true"></i><?= e(status_label($customer['status'] ?? 'active')) ?></span></td>
          <td data-label="سطح ریسک"><span class="proma-risk-badge <?= e($riskClass) ?>"><?= e($riskLabel) ?></span></td>
          <td data-label="امتیاز اعتباری">
            <?php if ($credit): ?><span class="proma-risk-score"><strong><?= to_persian_digits($score) ?></strong><span>/ ۱۰۰۰</span><span class="proma-risk-score__track" aria-label="<?= e($score) ?> از ۱۰۰۰"><i class="<?= e($riskClass) ?>" style="width:<?= e((string) round($score / 10)) ?>%"></i></span></span>
            <?php else: ?><span class="proma-risk-not-rated">با بازکردن پروفایل محاسبه می‌شود</span><?php endif; ?>
          </td>
          <td data-label="پروفایل"><a class="btn small secondary" href="<?= e(url('risk/profile/' . (int) $customer['id'])) ?>">مشاهده پروفایل</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?><tr><td colspan="6"><div class="empty">مشتری فعالی با این مشخصات پیدا نشد.</div></td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <?php if (($pagination['pages'] ?? 1) > 1): ?><nav class="proma-risk-pagination" aria-label="صفحه‌بندی مشتریان">
      <span>صفحهٔ <?= to_persian_digits($pagination['page']) ?> از <?= to_persian_digits($pagination['pages']) ?></span>
      <div><?php if ($pagination['page'] > 1): ?><a class="btn small secondary" href="<?= e($pageUrl($pagination['page'] - 1)) ?>">صفحهٔ قبل</a><?php endif; ?><?php if ($pagination['page'] < $pagination['pages']): ?><a class="btn small secondary" href="<?= e($pageUrl($pagination['page'] + 1)) ?>">صفحهٔ بعد</a><?php endif; ?></div>
    </nav><?php endif; ?>
  </section>
  <p class="proma-risk-disclaimer">Proma Score صرفاً شاخص کمکی است. پیشنهاد سقف اعتبار جایگزین بررسی انسانی، سیاست اعتباری سازمان یا کنترل مدارک نیست.</p>
</section>
