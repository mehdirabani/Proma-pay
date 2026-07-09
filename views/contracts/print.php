<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
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
    body { margin: 0; background: #f3f4f7; color: #1f2937; font-family: "YekanBakh", Tahoma, sans-serif; line-height: 1.9; }
    .contract-print-page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm; background: #fff; }
    .contract-print-body { font-size: 11px; line-height: 1.9; }
    .contract-print-body h1, .contract-print-body h2, .contract-print-body h3, .contract-print-body strong { font-weight: 700; }
    .contract-print-header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; page-break-inside: avoid; }
    .contract-print-header h1 { margin: 0; font-size: 16px; }
    .contract-print-header span { display: block; font-size: 11px; color: #4b5563; }
    .contract-document-body { font-size: 11px; }
    .contract-print-table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 10.5px; page-break-inside: avoid; }
    .contract-print-table th, .contract-print-table td { border: 1px solid #111827; padding: 4px 6px; text-align: center; vertical-align: top; }
    .contract-print-table th { background: #f3f4f6; font-weight: 700; }
    .contract-guarantors-section, .contract-signature-grid { page-break-inside: avoid; }
    .contract-guarantor-box { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 14px; border: 1px solid #d1d5db; padding: 10px; margin: 10px 0; }
    .contract-guarantor-box .full { grid-column: 1 / -1; }
    .contract-signature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 34px; }
    .contract-signature-box { min-height: 95px; border-top: 1px solid #111827; padding-top: 8px; text-align: center; }
    .contract-empty { color: #6b7280; border: 1px dashed #d1d5db; padding: 10px; margin: 10px 0; }
    @page { size: A4 portrait; margin: 12mm; }
    @media print {
      body { background: #fff; }
      .contract-print-page { width: auto; min-height: auto; margin: 0; padding: 0; }
      .contract-print-table, .contract-guarantors-section, .contract-signature-grid { break-inside: avoid; }
      a { color: inherit; text-decoration: none; }
    }
  </style>
</head>
<body>
  <main class="contract-print-page contract-print-body">
    <header class="contract-print-header">
      <div>
        <h1>قرارداد اجاره به شرط تملیک / امانت‌داری</h1>
        <span>شماره قرارداد: <?= e($contract['contract_number']) ?></span>
      </div>
      <div>
        <span>تاریخ قرارداد: <?= e(jdate($contract['start_date'])) ?></span>
        <span>امانت‌دار: <?= e($contract['customer_name']) ?></span>
      </div>
    </header>
    <?= $body ?>
  </main>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
