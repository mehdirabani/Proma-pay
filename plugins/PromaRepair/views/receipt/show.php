<?php
$logoPath = \Settings::get('logo_path', '');
$accessoryLabels = ['sim'=>'سیم‌کارت','case'=>'قاب','charger'=>'شارژر','cable'=>'کابل','memory_card'=>'کارت حافظه','box'=>'جعبه','other'=>'سایر'];
$conditionLabels = ['screen_cracked'=>'شکستگی نمایشگر','scratches'=>'خط و خش','frame_cracked'=>'شکستگی فریم','back_cracked'=>'شکستگی قاب پشت','water_damage'=>'آب‌خوردگی','impact'=>'اثر ضربه','opened_before'=>'بازشدگی قبلی','repaired_before'=>'تعمیر قبلی','other'=>'سایر'];
$accessories = json_decode((string) ($repair['accessories'] ?? ''), true) ?: [];
$conditions = json_decode((string) ($repair['condition_flags'] ?? ''), true) ?: [];
$trackingUrl = rtrim(detected_base_url(), '/') . '/index.php?route=plugin/repair/track&code=' . rawurlencode($repair['tracking_code']);
$totalRevenue = $financial ? \Proma\Plugins\Repair\Domain\RepairMoney::stored($financial['total_revenue'] ?? 0) : null;
$totalReceived = $financial ? \Proma\Plugins\Repair\Domain\RepairMoney::stored($financial['total_received'] ?? 0) : null;
$balance = $financial ? \Proma\Plugins\Repair\Domain\RepairMoney::stored($financial['customer_balance'] ?? 0) : null;
$paperSize = 'A5';
$copyMode = ($settings['receipt_copy_mode'] ?? 'single') === 'duplicate' ? 'duplicate' : 'single';
$contractPrintSettings = \Settings::allKeyed();
$printProfile = \ContractPrintProfile::load($contractPrintSettings);
$printProfile['show_customer_header'] = '1';
$printProfile['show_contract_title'] = '1';
$documentTitle = 'قرارداد پذیرش و انجام خدمات تعمیرات';
$documentNumberLabel = 'کد رهگیری';
$contractNumber = (string) ($repair['tracking_code'] ?? '-');
$contractDate = jdate($repair['created_at'] ?? '');
$customerName = (string) ($repair['customer_name'] ?? '-');
$logoText = trim((string) ($contractPrintSettings['company_name'] ?? $contractPrintSettings['logo_text'] ?? $contractPrintSettings['system_name'] ?? 'پرما پی'));
$printLogoPath = trim((string) ($contractPrintSettings['contract_logo_path'] ?? $contractPrintSettings['logo_path'] ?? $contractPrintSettings['logo_icon_path'] ?? ''));
$isCancelled = false;
$sellerSignaturePath = trim((string) \Settings::get('contract_seller_signature_path', ''));
$sellerSignatureHtml = $sellerSignaturePath !== ''
  ? '<img class="repair-seller-signature" src="' . e(asset_url($sellerSignaturePath)) . '" alt="امضای فروشنده">'
  : '<span class="repair-signature-placeholder">امضاء</span>';
?>
<!doctype html>
<html lang="fa" dir="rtl" style="<?= e(\ContractPrintProfile::styleVariables($printProfile)) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e($repair['tracking_code']) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/contract-print.css')) ?>">
  <style>
    *{box-sizing:border-box}html,body{margin:0;padding:0;color:#20283a;font-family:'Yekan Bakh','Vazirmatn',Tahoma,sans-serif;font-size:9pt;line-height:1.55;background:#f4f6fa}
    .actions{margin:12px auto;text-align:center}.actions button{border:0;border-radius:8px;background:#4f32e5;color:white;font:inherit;font-weight:700;padding:10px 18px;cursor:pointer}
    .receipt-page{display:grid;gap:8mm;margin:0 auto;max-width:148mm}.receipt-copy{background:white;border:1px solid #d9deea;border-radius:3mm;padding:0;position:relative;box-shadow:0 8px 28px #1f2a4414}.receipt-copy-label{font-size:8pt;font-weight:700}.receipt{margin:0 auto;max-width:none;padding:0}.repair-contract-page{--contract-page-width:148mm;--contract-page-height:210mm;color:#20283a}.repair-document>.repair-doc-header{display:none}
    .repair-document{color:#222b3c}.repair-doc-header{align-items:center;border-bottom:2px solid #4434ce;display:grid;gap:5mm;grid-template-columns:1fr auto;padding-bottom:4mm}.repair-doc-brand{align-items:center;display:flex;gap:4mm}.repair-doc-brand>p{margin:0}.repair-doc-brand img{max-height:20mm;max-width:34mm;object-fit:contain}.repair-doc-brand strong,.repair-doc-brand span{display:block}.repair-doc-brand strong{color:#172554;font-size:18pt}.repair-doc-brand span,.repair-doc-code span{color:#59657a;font-size:8pt;margin-top:1mm}.repair-doc-code{background:#f5f6ff;border:1px solid #e2e5ff;border-radius:2mm;min-width:43mm;padding:2.5mm;text-align:center}.repair-doc-code>*{display:block}.repair-doc-code>strong{color:#3024b7;font-size:13pt;letter-spacing:.3px}.repair-doc-code small{color:#64708a;font-weight:700}.repair-doc-code .track{margin-top:1mm}.repair-doc-code .track svg{height:17mm;width:17mm}
    .repair-doc-section{border:1px solid #e0e4ee;border-radius:2mm;margin-top:4mm;padding:3mm 4mm}.repair-doc-section h2,.repair-doc-terms h2{border-bottom:1px solid #d9deea;color:#172554;font-size:11pt;margin:0 0 2mm;padding-bottom:1.5mm}.repair-doc-facts{display:grid;gap:1mm 5mm;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}.repair-doc-facts>div{border-bottom:1px solid #edf0f5;display:grid;gap:2mm;grid-template-columns:34% 1fr;padding:1.4mm 0}.repair-doc-facts dt{color:#64708a}.repair-doc-facts dd{font-weight:700;margin:0;overflow-wrap:anywhere}.repair-doc-note{background:#f8f9fc;border-right:2px solid #8c82ed;margin-top:2mm;padding:2mm 3mm}.repair-doc-note strong{color:#38415a}.repair-doc-note p{margin:1mm 0 0;min-height:5mm;white-space:pre-wrap}.repair-doc-financial p{margin:1mm 0}.repair-doc-terms{margin-top:4mm}.repair-doc-terms>div,.repair-doc-terms>p{color:#505c70;font-size:8pt;margin:1mm 0}.repair-doc-terms ul,.repair-doc-terms ol{margin:1mm 0;padding-right:5mm}.repair-doc-signatures{align-items:end;display:grid;gap:8mm;grid-template-columns:1fr 1fr;margin-top:7mm}.repair-doc-signature{border-top:1px solid #778198;min-height:19mm;padding-top:1.5mm;text-align:center}.repair-doc-signature strong{display:block;color:#26324a}.repair-seller-signature{display:block!important;height:12mm;margin:0 auto 1mm;max-height:12mm!important;max-width:38mm!important;object-fit:contain}.repair-signature-placeholder{display:block;height:12mm}.repair-doc-footer{border-top:1px solid #d9deea;color:#69758a;display:flex;justify-content:space-between;margin-top:4mm;padding-top:2mm;font-size:8pt}.receipt img{object-fit:contain;vertical-align:middle}.repair-doc-code .track{display:inline-block}.actions{display:block}
    <?= \ContractPrintProfile::pageRule($printProfile, $paperSize) ?>
    @media print{html,body{background:white;font-size:9pt}.actions{display:none}.receipt-page{gap:0;max-width:none}.receipt-copy{border:0;border-radius:0;box-shadow:none;padding:0;break-after:page;page-break-after:always}.receipt-copy:last-child{break-after:auto;page-break-after:auto}.receipt-copy[hidden]{display:none!important}.repair-doc-section,.repair-doc-signatures{break-inside:avoid}.repair-doc-terms{break-inside:auto}}
    <?php if ($copyMode === 'single'): ?>.receipt-copy:nth-child(2){display:none}<?php endif; ?>
    @media screen and (max-width:650px){.receipt-page{padding:4mm}.repair-doc-header{grid-template-columns:1fr}.repair-doc-code{text-align:right}.repair-doc-facts{grid-template-columns:1fr}.receipt-copy{padding:0}}
  </style>
</head>
<body class="<?= ($printProfile['color_mode'] ?? 'color') === 'monochrome' ? 'contract-monochrome' : '' ?>">
  <div class="actions"><button type="button" onclick="window.print()">چاپ رسید <?= e($paperSize) ?><?= $copyMode === 'duplicate' ? ' · دو نسخه' : '' ?></button></div>
  <?php
    $accessoryText = implode('، ', array_map(static fn ($value) => $accessoryLabels[$value] ?? (string) $value, $accessories)) ?: 'ثبت نشده';
    $conditionText = implode('، ', array_map(static fn ($value) => $conditionLabels[$value] ?? (string) $value, $conditions)) ?: 'موردی ثبت نشده';
    $warrantyText = !empty($warranty['enabled']) ? to_persian_digits($warranty['warranty_days']) . ' روز' : 'ندارد';
    $financialHtml = '<p><strong>برآورد هزینه:</strong> ' . ($totalRevenue !== null ? money_toman($totalRevenue) : 'طبق اعلام مرکز تعمیر')
      . ' · <strong>مبلغ دریافت‌شده:</strong> ' . ($totalReceived !== null ? money_toman($totalReceived) : '—')
      . ' · <strong>مانده مشتری:</strong> ' . ($balance !== null ? money_toman($balance) : '—') . '</p>';
    $unionHtml = $settings['receipt_union_text'] ? '<h2>متن اتحادیه تعمیرکاران موبایل</h2><div>' . $settings['receipt_union_text'] . '</div><p>نسخه ' . to_persian_digits($settings['receipt_union_version']) . ' · ' . e($settings['receipt_union_updated_at']) . '</p>' : '';
    $receiptMarkup = \Proma\Plugins\Repair\Services\RepairReceiptTemplate::render(
      (string) ($settings['receipt_template_html'] ?? ''),
      [
        'copy_label' => 'نسخه مشتری',
        'logo_html' => $logoPath ? '<p><img src="' . e(asset_url($logoPath)) . '" alt="لوگوی کسب‌وکار"></p>' : '',
        'business_name' => $settings['receipt_business_name'] ?: Settings::get('app_name', 'Proma Pay'),
        'contact' => $settings['receipt_contact'] ?? '',
        'address' => $settings['store_address'] ?? '',
        'tracking_code' => $repair['tracking_code'] ?? '',
        'qr_html' => '<div class="track"><div data-repair-qr="' . e($trackingUrl) . '"></div></div>',
        'repair_number' => '#' . to_persian_digits($repair['id']),
        'accepted_at' => jdatetime($repair['created_at']),
        'customer_name' => $repair['customer_name'] ?? '',
        'customer_mobile' => to_persian_digits($repair['customer_mobile'] ?? ''),
        'device' => trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: ($repair['device_type'] ?? '—'),
        'status' => \Proma\Plugins\Repair\Domain\RepairWorkflow::label($repair['status']),
        'estimated_delivery' => !empty($repair['estimated_delivery_at']) ? jdatetime($repair['estimated_delivery_at']) : 'اعلام نشده',
        'accessories' => $accessoryText,
        'conditions' => $conditionText,
        'issue_summary' => $repair['customer_report'] ?? '',
        'condition_notes' => $repair['condition_notes'] ?? '',
        'financial_summary' => $financialHtml,
        'warranty' => $warrantyText,
        'device_identifiers' => implode(' · ', array_filter([
          !empty($repair['imei']) ? 'IMEI: ' . to_persian_digits($repair['imei']) : '',
          !empty($repair['serial_number']) ? 'سریال: ' . to_persian_digits($repair['serial_number']) : '',
        ])) ?: 'ثبت نشده',
        'signatures_html' => '<div class="repair-doc-signatures"><div class="repair-doc-signature"><strong>امضای مشتری</strong><span class="repair-signature-placeholder"></span></div><div class="repair-doc-signature"><strong>امضای فروشنده / مرکز تعمیر</strong>' . $sellerSignatureHtml . '</div></div>',
        'receipt_terms_html' => !empty($settings['receipt_terms']) ? '<h2>قوانین و شرایط پذیرش تعمیرات</h2><div>' . $settings['receipt_terms'] . '</div>' : '',
        'receipt_union_html' => $unionHtml,
        'footer' => $settings['receipt_footer'] ?? '',
      ],
      ['logo_html', 'qr_html', 'financial_summary', 'signatures_html', 'receipt_terms_html', 'receipt_union_html']
    );
  ?>
  <?php ob_start(); ?>
  <main class="receipt">
    <?= $receiptMarkup ?>
  </main>
  <?php $receiptMarkup = ob_get_clean(); ?>
  <div class="receipt-page">
    <section class="receipt-copy"><article class="contract-print-page contract-print-body repair-contract-page"><?php require dirname(__DIR__, 4) . '/views/contracts/letterhead.php'; ?><?= $receiptMarkup ?></article></section>
    <?php if ($copyMode === 'duplicate'): ?><section class="receipt-copy"><article class="contract-print-page contract-print-body repair-contract-page"><?php require dirname(__DIR__, 4) . '/views/contracts/letterhead.php'; ?><?= str_replace('نسخه مشتری', 'نسخه مرکز تعمیر', $receiptMarkup) ?></article></section><?php endif; ?>
  </div>
  <script src="<?= e(asset_url('plugins/PromaRepair/assets/js/qrcode-generator.js')) ?>"></script>
  <script>
  document.querySelectorAll('[data-repair-qr]').forEach((node) => { try { const qr=qrcode(0,'M'); qr.addData(node.dataset.repairQr,'Byte'); qr.make(); node.innerHTML=qr.createSvgTag({cellSize:3,margin:1,scalable:true}); } catch (_) { node.textContent='QR'; } });
  </script>
</body>
</html>
