<?php
$logoPath = trim((string) ($settings['contract_logo_path'] ?? $settings['logo_path'] ?? $settings['logo_icon_path'] ?? ''));
$logoText = trim((string) ($settings['company_name'] ?? $settings['system_name'] ?? 'پروما'));
?>
<!doctype html>
<html lang="fa" dir="rtl" style="<?= e(ContractPrintProfile::styleVariables($profile)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>پیش‌نمایش قرارداد</title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/contract-print.css')) ?>">
  <style><?= ContractPrintProfile::pageRule($profile) ?></style>
</head>
<body class="contract-preview-document <?= $profile['color_mode'] === 'monochrome' ? 'contract-monochrome' : '' ?>">
  <main class="contract-print-page contract-print-body">
    <header class="proma-contract-letterhead">
      <section class="proma-letterhead-number"><small>شماره قرارداد</small><strong dir="ltr">PR-1405-1001</strong></section>
      <section class="proma-letterhead-logo"><?php if ($logoPath): ?><img src="<?= e(asset_url($logoPath)) ?>" alt="لوگوی <?= e($logoText) ?>"><?php else: ?><span class="proma-letterhead-logo-fallback"><?= e($logoText) ?></span><?php endif; ?></section>
      <section class="proma-letterhead-meta"><span>تاریخ قرارداد: ۱۴۰۵/۰۴/۲۲</span><?php if (!empty($profile['show_customer_header'])): ?><span>نام مشتری: مشتری نمونه</span><?php endif; ?></section>
      <?php if (!empty($profile['show_contract_title'])): ?><section class="proma-letterhead-title"><h1><?= e($settings['contract_document_title'] ?? 'قرارداد اجاره به شرط تملیک / امانت‌داری') ?></h1></section><?php endif; ?>
    </header>
    <?= $body ?>
    <?php if (!empty($profile['show_footer'])): ?><footer class="contract-print-footer"><?= e($settings['company_name'] ?? $logoText) ?> - نسخه پیش‌نمایش</footer><?php endif; ?>
  </main>
</body>
</html>
