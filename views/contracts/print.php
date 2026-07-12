<?php
$settings = $settings ?? Settings::allKeyed();
$logoPath = trim((string) ($settings['contract_logo_path'] ?? $settings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
$logoText = trim((string) ($settings['company_name'] ?? $settings['logo_text'] ?? $settings['system_name'] ?? 'پرما پی'));
$printLogoPath = $logoPath ?: $logoIconPath;
$documentTitle = trim((string) ($documentTitle ?? '')) ?: 'قرارداد اجاره به شرط تملیک / امانت‌داری';
$documentHeader = trim((string) ($documentHeader ?? ''));
$isCancelled = ($contract['status'] ?? '') === 'cancelled';
$contractNumber = trim((string) ($contract['contract_number'] ?? '-'));
$contractDate = !empty($contract['start_date']) ? jdate($contract['start_date']) : '-';
$customerName = trim((string) ($contract['customer_name'] ?? '-'));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($documentTitle) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/contract-print.css')) ?>">
</head>
<body>
  <main class="contract-print-page contract-print-body">
    <header class="proma-contract-letterhead">
      <section class="proma-letterhead-number">
        <small>شماره قرارداد</small>
        <strong dir="ltr"><?= e($contractNumber) ?></strong>
      </section>

      <section class="proma-letterhead-logo">
        <?php if ($printLogoPath): ?>
          <img src="<?= e(asset_url($printLogoPath)) ?>" alt="لوگوی <?= e($logoText) ?>" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
          <span class="proma-letterhead-logo-fallback" hidden><?= e($logoText) ?></span>
        <?php else: ?>
          <span class="proma-letterhead-logo-fallback"><?= e($logoText) ?></span>
        <?php endif; ?>
      </section>

      <section class="proma-letterhead-meta">
        <span>تاریخ قرارداد: <?= e($contractDate) ?></span>
        <span class="<?= $isCancelled ? 'contract-cancelled-note' : '' ?>">نام مشتری: <?= e($customerName) ?></span>
      </section>

      <section class="proma-letterhead-title">
        <h1><?= e($documentTitle) ?></h1>
        <?php if ($documentHeader !== ''): ?><p><?= e($documentHeader) ?></p><?php endif; ?>
      </section>
    </header>

    <?php if ($isCancelled): ?><div class="contract-cancelled-watermark">قرارداد لغو شده</div><?php endif; ?>
    <?= $body ?>
  </main>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
