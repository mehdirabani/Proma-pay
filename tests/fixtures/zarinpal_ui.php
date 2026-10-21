<?php

$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__, 2) . '/bootstrap.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$screen = (string) ($_GET['screen'] ?? 'settings');
if ($screen === 'callback') {
    $title = 'پرداخت موفق زرین‌پال';
    $systemName = 'پرما پی';
    $result = [
        'status' => 'success',
        'message' => 'پرداخت با موفقیت انجام شد.',
        'transaction' => [
            'internal_amount_toman' => 2450000,
            'contract_number' => 'PR-1405-0012',
            'installment_number' => 4,
            'ref_id' => '725901184',
            'card_pan_masked' => '621986******8080',
            'verified_at' => '2026-07-13 11:21:00',
        ],
    ];
    require dirname(__DIR__, 2) . '/plugins/PromaZarinpal/views/callback/result.php';
    exit;
} elseif ($screen === 'transactions') {
    $_GET = ['screen' => 'transactions', 'page' => 1];
    $filters = ['q' => '', 'status' => '', 'environment' => ''];
    $pagination = [
        'page' => 1,
        'pages' => 3,
        'total' => 27,
        'items' => [
            ['id' => 184, 'local_order_id' => 'ZP-20260713-0184', 'customer_name' => 'مهدی ربانی', 'contract_number' => 'PR-1405-0012', 'internal_amount_toman' => 2450000, 'gateway_currency' => 'IRT', 'gateway_amount' => 2450000, 'environment' => 'production', 'status' => 'paid', 'error_code' => '', 'authority' => 'A0000000000000000000000000042184', 'ref_id' => '725901184', 'created_at' => '2026-07-13 11:20:00', 'verified_at' => '2026-07-13 11:21:00', 'retry_count' => 0],
            ['id' => 183, 'local_order_id' => 'ZP-20260713-0183', 'customer_name' => 'فاطمه شاکری', 'contract_number' => 'PR-1405-0011', 'internal_amount_toman' => 8900000, 'gateway_currency' => 'IRR', 'gateway_amount' => 89000000, 'environment' => 'sandbox', 'status' => 'verification_failed', 'error_code' => '-51', 'authority' => 'A0000000000000000000000000042183', 'ref_id' => '', 'created_at' => '2026-07-13 10:45:00', 'verified_at' => null, 'retry_count' => 1],
            ['id' => 182, 'local_order_id' => 'ZP-20260713-0182', 'customer_name' => 'رضا توکلی', 'contract_number' => 'PR-1405-0009', 'internal_amount_toman' => 1200000, 'gateway_currency' => 'IRT', 'gateway_amount' => 1200000, 'environment' => 'production', 'status' => 'pending', 'error_code' => '', 'authority' => 'A0000000000000000000000000042182', 'ref_id' => '', 'created_at' => '2026-07-13 09:08:00', 'verified_at' => null, 'retry_count' => 0],
        ],
    ];
    ob_start();
    require dirname(__DIR__, 2) . '/plugins/PromaZarinpal/views/transactions/index.php';
    $content = ob_get_clean();
} elseif ($screen === 'sandbox') {
    $settings = [
        'enabled' => '1', 'environment' => 'sandbox',
        'sandbox_merchant_id_configured' => '1', 'sandbox_financial_effects' => '0',
    ];
    $confirmationCode = '482731';
    $endpoints = [
        'request' => 'https://sandbox.zarinpal.com/pg/v4/payment/request.json',
        'verify' => 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json',
        'start' => 'https://sandbox.zarinpal.com/pg/StartPay/{authority}',
    ];
    ob_start();
    require dirname(__DIR__, 2) . '/plugins/PromaZarinpal/views/sandbox/index.php';
    $content = ob_get_clean();
} else {
    $settings = [
        'enabled' => '1', 'environment' => 'sandbox', 'currency' => 'IRT',
        'production_merchant_id' => '', 'production_merchant_id_configured' => '1',
        'sandbox_merchant_id' => '', 'sandbox_merchant_id_configured' => '1',
        'gateway_title' => 'زرین‌پال',
        'description_template' => 'پرداخت {{installment_number}} قرارداد {{contract_number}}',
        'minimum_amount_toman' => '1000', 'maximum_amount_toman' => '1000000000',
        'send_mobile' => '1', 'send_email' => '0', 'send_order_id' => '1',
        'is_default' => '1', 'allow_customer_selection' => '1',
        'callback_base_url' => 'https://proma.example.com',
        'connect_timeout' => '10', 'response_timeout' => '30',
        'technical_logging' => '1', 'show_technical_errors_admin' => '1',
        'sandbox_financial_effects' => '0',
    ];
    $callbackUrl = 'https://proma.example.com/index.php?route=plugin/zarinpal/callback';
    ob_start();
    require dirname(__DIR__, 2) . '/plugins/PromaZarinpal/views/settings/index.php';
    $content = ob_get_clean();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="../../html/RTL/assets/css/vendors/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="../../html/RTL/assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/app.css">
  <link rel="stylesheet" href="../../assets/css/components/forms.css">
  <link rel="stylesheet" href="../../assets/css/components/layout.css">
  <link rel="stylesheet" href="../../plugins/PromaZarinpal/assets/css/zarinpal.css">
  <style>body{background:#f6f7fb;padding:24px}.fixture{margin:auto;max-width:1440px}</style>
</head>
<body><main class="fixture proma-page-content"><?= $content ?></main>
<script src="../../html/RTL/assets/js/icons/feather-icon/feather.min.js"></script>
<script>if(window.feather){window.feather.replace();}document.addEventListener('click',function(event){var open=event.target.closest('[data-open-modal]');if(open){document.getElementById(open.dataset.openModal)?.classList.add('open');}if(event.target.closest('[data-close-modal]')){event.target.closest('.modal')?.classList.remove('open');}});</script>
</body></html>
