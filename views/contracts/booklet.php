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
      color: #20212a;
      background: #f4f6fb;
      font-family: "YekanBakh", Tahoma, Arial, sans-serif;
      direction: rtl;
    }
    .booklet-shell {
      width: min(780px, calc(100% - 24px));
      margin: 24px auto;
    }
    .booklet-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
    }
    .booklet-toolbar h1 {
      margin: 0;
      font-size: 22px;
    }
    .booklet-toolbar button {
      min-height: 42px;
      border: 0;
      border-radius: 8px;
      padding: 9px 18px;
      color: #fff;
      background: #7366ff;
      font: inherit;
      cursor: pointer;
    }
    .booklet-cover,
    .coupon {
      border: 1px solid #dfe4ef;
      border-radius: 8px;
      background: #fff;
      box-shadow: 0 10px 26px rgba(82, 82, 108, .08);
    }
    .booklet-cover {
      padding: 22px;
      margin-bottom: 16px;
    }
    .booklet-cover h2 {
      margin: 0 0 14px;
      font-size: 20px;
    }
    .meta-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    .meta-grid div {
      min-height: 72px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #f7f8fc;
    }
    .meta-grid span,
    .coupon small {
      display: block;
      margin-bottom: 5px;
      color: #747684;
      font-size: 12px;
    }
    .meta-grid strong,
    .coupon strong {
      font-size: 15px;
      line-height: 1.8;
    }
    .coupon-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .coupon {
      min-height: 164px;
      padding: 12px;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .coupon-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding-bottom: 10px;
      margin-bottom: 10px;
      border-bottom: 1px dashed #cbd3e3;
    }
    .coupon-number {
      display: inline-grid;
      place-items: center;
      min-width: 48px;
      min-height: 34px;
      border-radius: 8px;
      color: #fff;
      background: #7366ff;
      font-weight: 800;
    }
    .coupon-body {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }
    .coupon-footer {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px dashed #cbd3e3;
      color: #747684;
      font-size: 12px;
    }
    @media print {
      @page { size: A4 portrait; margin: 8mm; }
      html,
      body {
        width: auto;
        min-width: 0;
        color: #111827;
        background: #fff;
        font-size: 9.5pt;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .booklet-shell {
        width: 100%;
        margin: 0;
      }
      .booklet-toolbar {
        display: none;
      }
      .booklet-cover,
      .coupon {
        border-color: #1f2937;
        border-radius: 0;
        box-shadow: none;
      }
      .booklet-cover {
        padding: 4mm;
        margin-bottom: 4mm;
        break-after: avoid;
        page-break-after: auto;
      }
      .booklet-cover h2 {
        margin-bottom: 3mm;
        font-size: 13pt;
      }
      .meta-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 2mm;
      }
      .meta-grid div {
        min-height: 13mm;
        padding: 1.8mm 2mm;
        border: 1px solid #d1d5db;
        border-radius: 0;
        background: #fff;
      }
      .meta-grid span,
      .coupon small {
        margin-bottom: 1mm;
        color: #374151;
        font-size: 8pt;
      }
      .meta-grid strong,
      .coupon strong {
        font-size: 9.5pt;
        line-height: 1.55;
      }
      .coupon-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 4mm;
        align-items: start;
      }
      .coupon {
        min-height: 58mm;
        padding: 3mm;
        break-inside: avoid;
        page-break-inside: avoid;
      }
      .coupon-header {
        padding-bottom: 2mm;
        margin-bottom: 2mm;
        border-bottom-color: #4b5563;
      }
      .coupon-number {
        min-width: 23mm;
        min-height: 9mm;
        border: 1px solid #111827;
        border-radius: 0;
        color: #111827;
        background: #fff;
      }
      .coupon-body {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 2mm;
      }
      .coupon-footer {
        min-height: 12mm;
        gap: 3mm;
        margin-top: 3mm;
        padding-top: 3mm;
        border-top-color: #4b5563;
        color: #111827;
      }
    }
  </style>
</head>
<body>
  <main class="booklet-shell">
    <div class="booklet-toolbar">
      <h1>دفترچه اقساط قرارداد <?= e($contract['contract_number']) ?></h1>
      <button type="button" onclick="window.print()">چاپ دفترچه</button>
    </div>

    <section class="booklet-cover">
      <h2>مشخصات قرارداد</h2>
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
      <?php if ($guarantors): ?>
        <p><strong>ضامنان:</strong> <?= e(implode('، ', array_column($guarantors, 'full_name'))) ?></p>
      <?php endif; ?>
    </section>

    <section class="coupon-grid">
      <?php foreach ($installments as $item): ?>
        <article class="coupon">
          <div class="coupon-header">
            <div>
              <small>قرارداد</small>
              <strong><?= e($contract['contract_number']) ?></strong>
            </div>
            <div class="coupon-number">قسط <?= to_persian_digits($item['installment_number']) ?></div>
          </div>
          <div class="coupon-body">
            <div><small>نام مشتری</small><strong><?= e($contract['customer_name']) ?></strong></div>
            <div><small>کد ملی</small><strong><?= to_persian_digits($contract['national_id']) ?></strong></div>
            <div><small>تاریخ سررسید</small><strong><?= e(jdate($item['due_date'])) ?></strong></div>
            <div><small>مبلغ قسط</small><strong><?= money_toman($item['base_amount']) ?></strong></div>
            <div><small>وضعیت</small><strong><?= e(status_label($item['status'])) ?></strong></div>
          </div>
          <div class="coupon-footer">
            <span>امضا/مهر دریافت‌کننده:</span>
            <span>تاریخ پرداخت:</span>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
