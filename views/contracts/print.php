<?php
$settings = $settings ?? Settings::allKeyed();
$logoPath = trim((string) ($settings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
$logoText = trim((string) ($settings['logo_text'] ?? $settings['system_name'] ?? 'پروما'));
$printLogoPath = $logoPath ?: $logoIconPath;
$documentTitle = trim((string) ($documentTitle ?? '')) ?: 'قرارداد';
$documentHeader = trim((string) ($documentHeader ?? ''));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($documentTitle) ?></title>
  <style>
    @font-face {
      font-family: "YekanBakh";
      src: url("assets/fonts/woff2/YekanBakh-Regular.woff2") format("woff2"),
           url("assets/fonts/woff/YekanBakh-Regular.woff") format("woff");
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: "YekanBakh";
      src: url("assets/fonts/woff2/YekanBakh-Bold.woff2") format("woff2"),
           url("assets/fonts/woff/YekanBakh-Bold.woff") format("woff");
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: #eef1f6;
      color: #111827;
      font-family: "YekanBakh", Tahoma, sans-serif;
      line-height: 1.58;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .contract-print-page {
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      padding: 10mm 11mm;
      background: #fff;
    }
    .contract-print-header {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr);
      gap: 9px 12px;
      align-items: center;
      border: 1.2px solid #1f2937;
      border-radius: 7px;
      padding: 8px 10px;
      margin-bottom: 8px;
      page-break-inside: avoid;
    }
    .contract-print-logo {
      width: 54px;
      height: 54px;
      display: inline-grid;
      place-items: center;
      border: 1px solid #d8dee9;
      border-radius: 7px;
      overflow: hidden;
      background: #f8fafc;
      color: #7366ff;
      font-weight: 700;
      font-size: 20px;
    }
    .contract-print-logo img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .contract-print-title {
      display: grid;
      gap: 3px;
      min-width: 0;
    }
    .contract-print-title h1 {
      margin: 0;
      font-size: 15px;
      line-height: 1.35;
      font-weight: 700;
    }
    .contract-print-title p {
      margin: 0;
      color: #4b5563;
      font-size: 9.2px;
      line-height: 1.55;
      white-space: pre-line;
    }
    .contract-print-meta {
      grid-column: 1 / -1;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 4px;
      padding-top: 5px;
      border-top: 1px dashed #d1d5db;
      color: #374151;
      font-size: 8.8px;
    }
    .contract-print-body,
    .contract-document-body {
      direction: rtl;
      color: #111827;
      font-size: 9.6px;
      line-height: 1.58;
    }
    .contract-print-body h1,
    .contract-print-body h2,
    .contract-print-body h3,
    .contract-print-body strong {
      font-weight: 700;
    }
    .contract-document-body br {
      line-height: 1.28;
    }
    .contract-print-table {
      width: 100%;
      border-collapse: collapse;
      margin: 5px 0 7px;
      font-size: 8.7px;
      page-break-inside: avoid;
    }
    .contract-print-table th,
    .contract-print-table td {
      border: .8px solid #1f2937;
      padding: 2px 4px;
      text-align: center;
      vertical-align: top;
    }
    .contract-print-table th {
      background: #f3f4f6;
      font-weight: 700;
    }
    .contract-guarantors-section,
    .contract-signature-grid {
      page-break-inside: avoid;
    }
    .contract-guarantors-section h3 {
      margin: 6px 0 4px;
      font-size: 10px;
    }
    .contract-guarantor-box {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 3px 10px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 6px;
      margin: 5px 0;
      font-size: 8.8px;
    }
    .contract-guarantor-box .full {
      grid-column: 1 / -1;
    }
    .contract-signature-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 9px;
      margin-top: 18px;
    }
    .contract-signature-box {
      min-height: 58px;
      border-top: 1px solid #111827;
      padding-top: 5px;
      text-align: center;
      font-size: 8.8px;
    }
    .contract-empty {
      color: #6b7280;
      border: 1px dashed #d1d5db;
      border-radius: 6px;
      padding: 6px;
      margin: 5px 0;
      font-size: 8.8px;
    }
    @page { size: A4 portrait; margin: 8mm; }
    @media print {
      body { background: #fff; }
      .contract-print-page {
        width: auto;
        min-height: auto;
        margin: 0;
        padding: 0;
      }
      .contract-print-table,
      .contract-guarantors-section,
      .contract-signature-grid,
      .contract-print-header {
        break-inside: avoid;
      }
      a { color: inherit; text-decoration: none; }
    }
  </style>
</head>
<body>
  <main class="contract-print-page contract-print-body">
    <header class="contract-print-header">
      <span class="contract-print-logo">
        <?php if ($printLogoPath): ?>
          <img src="<?= e(asset_url($printLogoPath)) ?>" alt="<?= e($logoText) ?>">
        <?php else: ?>
          <?= e(mb_substr($logoText, 0, 1, 'UTF-8') ?: 'پ') ?>
        <?php endif; ?>
      </span>
      <div class="contract-print-title">
        <h1><?= e($documentTitle) ?></h1>
        <?php if ($documentHeader !== ''): ?><p><?= e($documentHeader) ?></p><?php endif; ?>
      </div>
      <div class="contract-print-meta">
        <span>شماره قرارداد: <?= e($contract['contract_number']) ?></span>
        <span>تاریخ قرارداد: <?= e(jdate($contract['start_date'])) ?></span>
        <span>امانت‌دار: <?= e($contract['customer_name']) ?></span>
      </div>
    </header>
    <?= $body ?>
  </main>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
