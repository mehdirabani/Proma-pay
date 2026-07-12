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
$printProfile = $printProfile ?? ContractPrintProfile::load($settings);
$autoPrint = array_key_exists('autoPrint', get_defined_vars()) ? (bool) $autoPrint : true;
?>
<!doctype html>
<html lang="fa" dir="rtl" style="<?= e(ContractPrintProfile::styleVariables($printProfile)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($documentTitle) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/contract-print.css')) ?>">
  <style><?= ContractPrintProfile::pageRule($printProfile) ?></style>
</head>
<body class="<?= ($printProfile['color_mode'] ?? 'color') === 'monochrome' ? 'contract-monochrome' : '' ?>">
  <aside class="contract-print-help">برای خروجی تمیز، در پنجره چاپ گزینه «سرصفحه و پابرگ / Headers and footers» را غیرفعال کنید.</aside>
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
        <?php if (!empty($printProfile['show_customer_header'])): ?><span class="<?= $isCancelled ? 'contract-cancelled-note' : '' ?>">نام مشتری: <?= e($customerName) ?></span><?php endif; ?>
      </section>

      <?php if (!empty($printProfile['show_contract_title'])): ?><section class="proma-letterhead-title">
        <h1><?= e($documentTitle) ?></h1>
        <?php if ($documentHeader !== ''): ?><p><?= e($documentHeader) ?></p><?php endif; ?>
      </section><?php endif; ?>
    </header>

    <?php if ($isCancelled): ?><div class="contract-cancelled-watermark">قرارداد لغو شده</div><?php endif; ?>
    <?= $body ?>
    <?php if (!empty($printProfile['show_footer'])): ?><footer class="contract-print-footer"><?= e($logoText) ?> - <?= e($contractNumber) ?></footer><?php endif; ?>
  </main>
  <?php if ($autoPrint): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
</body>
</html>
