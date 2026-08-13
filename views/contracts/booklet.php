<?php
$lastDueDate = '';
foreach ($installments as $item) {
    if (!empty($item['due_date']) && $item['due_date'] > $lastDueDate) {
        $lastDueDate = $item['due_date'];
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - <?= e($contract['contract_number']) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/booklet-print.css')) ?>">
  <script src="<?= e(asset_url('assets/js/booklet-print.js')) ?>" defer></script>
</head>
<body class="booklet-page">
  <main class="booklet-shell">
    <header class="booklet-toolbar">
      <div><span class="booklet-kicker">مدیریت اقساط پروما</span><h1>دفترچه اقساط قرارداد <?= e($contract['contract_number']) ?></h1></div>
      <button type="button" data-print-booklet>چاپ دفترچه</button>
    </header>

    <section class="booklet-cover" aria-labelledby="booklet-contract-title">
      <h2 id="booklet-contract-title">مشخصات قرارداد</h2>
      <div class="meta-grid">
        <div><span>شماره قرارداد</span><strong><?= e($contract['contract_number']) ?></strong></div>
        <div><span>مشتری</span><strong><?= e($contract['customer_name']) ?></strong></div>
        <div><span>کد ملی</span><strong><?= to_persian_digits($contract['national_id']) ?></strong></div>
        <div><span>موبایل</span><strong><?= to_persian_digits($contract['mobile']) ?></strong></div>
        <div><span>تاریخ قرارداد</span><strong><?= e(jdate($contract['start_date'])) ?></strong></div>
        <div><span>اولین سررسید</span><strong><?= e(jdate($contract['first_due_date'])) ?></strong></div>
        <div><span>تعداد اقساط</span><strong><?= to_persian_digits(count($installments)) ?></strong></div>
        <div><span>آخرین سررسید</span><strong><?= $lastDueDate ? e(jdate($lastDueDate)) : '-' ?></strong></div>
      </div>
      <?php if ($guarantors): ?><p><strong>ضامنان:</strong> <?= e(implode('، ', array_column($guarantors, 'full_name'))) ?></p><?php endif; ?>
    </section>

    <section class="coupon-grid" aria-label="برگه‌های اقساط">
      <?php foreach ($installments as $item): ?>
        <article class="coupon">
          <header class="coupon-header"><div><small>قرارداد</small><strong><?= e($contract['contract_number']) ?></strong></div><div class="coupon-number">قسط <?= to_persian_digits($item['installment_number']) ?></div></header>
          <div class="coupon-body">
            <div><small>نام مشتری</small><strong><?= e($contract['customer_name']) ?></strong></div>
            <div><small>کد ملی</small><strong><?= to_persian_digits($contract['national_id']) ?></strong></div>
            <div><small>تاریخ سررسید</small><strong><?= e(jdate($item['due_date'])) ?></strong></div>
            <div><small>مبلغ قسط</small><strong><?= money_toman($item['base_amount']) ?></strong></div>
            <div><small>وضعیت</small><strong><?= e(status_label($item['status'])) ?></strong></div>
            <?php if (!empty($item['is_custom']) && !empty($item['customer_visible']) && trim((string) ($item['custom_description'] ?? $item['notes'] ?? '')) !== ''): ?><div class="coupon-custom-description"><small>دلیل ایجاد</small><strong><?= e(trim((string) ($item['custom_description'] ?? $item['notes'] ?? ''))) ?></strong></div><?php endif; ?>
          </div>
          <footer class="coupon-footer"><span>امضا/مهر دریافت‌کننده:</span><span>تاریخ پرداخت:</span></footer>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
