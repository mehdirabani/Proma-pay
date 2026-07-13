<?php
$status = in_array(($result['status'] ?? ''), ['success', 'pending', 'failure'], true) ? $result['status'] : 'failure';
$transaction = $result['transaction'] ?? [];
$systemName = $systemName ?? Settings::get('system_name', 'پروما');
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title><link rel="icon" href="data:,"><link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) ?>"><link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset_url('plugins/PromaZarinpal/assets/css/zarinpal.css')) ?>"></head><body class="proma-zp-callback-body">
<main class="proma-zp-result is-<?= e($status) ?>">
  <div class="proma-zp-result-icon"><?php if ($status === 'success'): ?>✓<?php elseif ($status === 'pending'): ?>…<?php else: ?>×<?php endif; ?></div>
  <span><?= e($systemName) ?> · زرین‌پال</span><h1><?= e($result['message'] ?? 'نتیجه پرداخت قابل بررسی نیست.') ?></h1>
  <?php if ($status === 'success' && $transaction): ?><dl><div><dt>مبلغ</dt><dd><?= money_toman($transaction['internal_amount_toman'] ?? 0) ?></dd></div><div><dt>قرارداد</dt><dd dir="ltr"><?= e($transaction['contract_number'] ?? '—') ?></dd></div><div><dt>قسط / گروه</dt><dd><?= !empty($transaction['installment_number']) ? 'قسط ' . to_persian_digits($transaction['installment_number']) : (!empty($transaction['payment_group_id']) ? 'پرداخت گروهی #' . to_persian_digits($transaction['payment_group_id']) : '—') ?></dd></div><div><dt>شناسه مرجع</dt><dd dir="ltr"><?= e($transaction['ref_id'] ?? '—') ?></dd></div><?php if (!empty($transaction['card_pan_masked'])): ?><div><dt>کارت</dt><dd dir="ltr"><?= e($transaction['card_pan_masked']) ?></dd></div><?php endif; ?><div><dt>زمان پرداخت</dt><dd><?= e(jdatetime($transaction['verified_at'] ?? date('Y-m-d H:i:s'))) ?></dd></div></dl><?php endif; ?>
  <?php if ($status === 'pending'): ?><p>برای جلوگیری از ثبت تکراری، پرداخت تازه‌ای نسازید و چند دقیقه بعد سوابق اقساط را بررسی کنید.</p><?php endif; ?>
  <a class="btn btn-primary" href="<?= e(url('installments/panel')) ?>"><?= $status === 'failure' ? 'بازگشت و تلاش مجدد' : 'بازگشت به اقساط' ?></a>
</main></body></html>
