<?php
$settings = $settings ?? Settings::allKeyed();
$logoPath = trim((string) ($settings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
$logoText = trim((string) ($settings['logo_text'] ?? $settings['system_name'] ?? 'پروما'));
$printLogoPath = $logoPath ?: $logoIconPath;
$documentTitle = trim((string) ($documentTitle ?? '')) ?: 'قرارداد';
$documentHeader = trim((string) ($documentHeader ?? ''));
$isCancelled = ($contract['status'] ?? '') === 'cancelled';
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
      line-height: 1.34;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .contract-print-page {
      position: relative;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      padding: 8mm 9mm;
      background: #fff;
    }
    .contract-print-header {
      position: relative;
      display: grid;
      grid-template-columns: 60px minmax(0, 1fr) 60px;
      gap: 6px 10px;
      align-items: center;
      overflow: hidden;
      border: 1px solid #1f2937;
      border-radius: 8px;
      padding: 6px 8px 7px;
      margin-bottom: 6px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      page-break-inside: avoid;
    }
    .contract-print-header::before {
      position: absolute;
      inset: 0 0 auto 0;
      height: 3px;
      background: linear-gradient(90deg, #7366ff, #16c7f9, #54ba4a);
      content: "";
    }
    .contract-print-logo {
      width: 50px;
      height: 50px;
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
      grid-column: 2;
      gap: 2px;
      min-width: 0;
      text-align: center;
    }
    .contract-print-title h1 {
      margin: 0;
      color: #111827;
      font-size: 16px;
      line-height: 1.22;
      font-weight: 700;
    }
    .contract-print-title p {
      margin: 0;
      color: #4b5563;
      font-size: 8.4px;
      line-height: 1.32;
      white-space: pre-line;
    }
    .contract-print-side-note {
      justify-self: end;
      width: 50px;
      min-height: 50px;
      display: grid;
      place-items: center;
      border: 1px solid #e5e7eb;
      border-radius: 7px;
      color: #374151;
      background: #fff;
      font-size: 8px;
      font-weight: 700;
      text-align: center;
      line-height: 1.45;
    }
    .contract-print-meta {
      grid-column: 1 / -1;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 4px 6px;
      padding-top: 4px;
      border-top: 1px dashed #d1d5db;
      color: #374151;
      font-size: 8.4px;
      line-height: 1.25;
    }
    .contract-print-meta span {
      min-height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      padding: 2px 4px;
      background: #fff;
    }
    .contract-cancelled-watermark {
      position: absolute;
      top: 42%;
      left: 50%;
      z-index: 2;
      color: rgba(185, 28, 28, .16);
      border: 2px solid rgba(185, 28, 28, .2);
      padding: 8px 22px;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 0;
      transform: translate(-50%, -50%) rotate(-18deg);
      pointer-events: none;
      white-space: nowrap;
    }
    .contract-cancelled-note {
      color: #b91c1c !important;
      font-weight: 700;
    }
    .contract-print-body,
    .contract-document-body {
      direction: rtl;
      color: #111827;
      font-size: 9px;
      line-height: 1.32;
    }
    .contract-print-body h1,
    .contract-print-body h2,
    .contract-print-body h3,
    .contract-print-body strong {
      font-weight: 700;
    }
    .contract-document-body p,
    .contract-document-body div,
    .contract-document-body li,
    .contract-document-body blockquote {
      line-height: 1.32;
    }
    .contract-document-body p,
    .contract-document-body div {
      margin-top: 0;
      margin-bottom: 2px;
    }
    .contract-document-body ul,
    .contract-document-body ol {
      margin: 2px 0 3px;
      padding-inline-start: 18px;
    }
    .contract-document-body li {
      margin-bottom: 1px;
    }
    .contract-document-body h1,
    .contract-document-body h2,
    .contract-document-body h3,
    .contract-document-body h4 {
      margin: 4px 0 2px;
      line-height: 1.22;
    }
    .contract-document-body br {
      line-height: 1.08;
    }
    .contract-print-table {
      width: 100%;
      border-collapse: collapse;
      margin: 3px 0 5px;
      font-size: 8px;
      line-height: 1.24;
      page-break-inside: avoid;
    }
    .contract-print-table th,
    .contract-print-table td {
      border: .8px solid #1f2937;
      padding: 1.5px 3px;
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
      margin: 4px 0 2px;
      font-size: 9px;
    }
    .contract-guarantor-box {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 2px 8px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 4px 5px;
      margin: 3px 0;
      font-size: 8px;
      line-height: 1.28;
    }
    .contract-guarantor-box .full {
      grid-column: 1 / -1;
    }
    .contract-signature-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 7px;
      margin-top: 12px;
    }
    .contract-signature-box {
      min-height: 42px;
      border-top: 1px solid #111827;
      padding-top: 4px;
      text-align: center;
      font-size: 8px;
    }
    .contract-empty {
      color: #6b7280;
      border: 1px dashed #d1d5db;
      border-radius: 6px;
      padding: 4px;
      margin: 3px 0;
      font-size: 8px;
    }
    @page { size: A4 portrait; margin: 6mm; }
    @media print {
      body { background: #fff; }
      .contract-print-page {
        width: auto;
        min-height: auto;
        margin: 0;
        padding: 0;
      }
      .contract-print-body,
      .contract-document-body {
        font-size: 8.7px;
        line-height: 1.26;
      }
      .contract-document-body p,
      .contract-document-body div,
      .contract-document-body li,
      .contract-document-body blockquote {
        line-height: 1.26;
      }
      .contract-document-body p,
      .contract-document-body div {
        margin-bottom: 1.5px;
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
      <span class="contract-print-side-note<?= $isCancelled ? ' contract-cancelled-note' : '' ?>"><?= $isCancelled ? 'قرارداد لغو شده' : 'نسخه چاپی<br>قرارداد' ?></span>
      <div class="contract-print-meta">
        <span>شماره قرارداد: <?= e($contract['contract_number']) ?></span>
        <span>تاریخ قرارداد: <?= e(jdate($contract['start_date'])) ?></span>
        <span class="<?= $isCancelled ? 'contract-cancelled-note' : '' ?>">امانت‌دار: <?= e($contract['customer_name']) ?></span>
      </div>
    </header>
    <?php if ($isCancelled): ?><div class="contract-cancelled-watermark">قرارداد لغو شده</div><?php endif; ?>
    <?= $body ?>
  </main>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
