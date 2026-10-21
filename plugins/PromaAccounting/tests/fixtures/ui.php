<?php

declare(strict_types=1);

function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url($route, array $params = []) { return '/index.php?' . http_build_query(array_merge(['route' => $route], $params)); }
function csrf_field() { return '<input type="hidden" name="_token" value="fixture">'; }
function money_toman($value) { return number_format((int) $value) . ' تومان'; }
function to_persian_digits($value) { return strtr((string) $value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function role_label($role) { return ['admin' => 'مدیر', 'operator' => 'اپراتور', 'lawyer' => 'وکیل'][$role] ?? $role; }
function render_pagination() { return ''; }
function jdatetime($value) { return '۱۴۰۵/۰۴/۲۲'; }

$view = $_GET['view'] ?? 'settings';
$dark = ($_GET['dark'] ?? '') === '1';
$settings = [
    'default_commission_type' => 'percentage', 'default_commission_value' => '2',
    'default_calculation_basis' => 'financed_amount', 'minimum_commission_enabled' => '1',
    'minimum_commission' => '250000', 'maximum_commission_enabled' => '1', 'maximum_commission' => '800000',
    'rounding_method' => 'nearest', 'rounding_unit' => '1000',
    'default_calculation_timing' => 'after_full_settlement', 'require_commission_approval' => '1',
    'automatic_ledger_posting' => '1', 'setup_step' => '5',
];
$staff = [['id'=>1,'full_name'=>'مهدی ربانی','role'=>'admin','mobile'=>'09120000000']];
$previewInput = [];
$preview = ['rule_name'=>'قانون پیش‌فرض سامانه','rule_source'=>'default','basis_amount'=>15000000,'raw_commission'=>300000,'minimum_adjustment'=>0,'maximum_adjustment'=>0,'rounding_adjustment'=>0,'final_commission'=>300000];
$summary = ['users'=>12,'sales'=>146,'commission'=>5300000,'ledger'=>12800000,'positive_balances'=>18400000,'negative_balances'=>-3200000,'pending_commissions'=>2100000,'payable_commissions'=>3200000,'payments_this_month'=>4700000,'receipts_this_month'=>6100000];
$accounts = [
    ['id'=>1,'full_name'=>'مهدی ربانی','username'=>'mehdi','role'=>'admin','account_number'=>'ACC-00000001','balance'=>4200000,'mobile'=>'09120000000','total_commission'=>2800000,'total_increase'=>7600000,'total_decrease'=>3400000,'last_transaction_at'=>'2026-07-13 10:30:00','account_status'=>'active'],
    ['id'=>2,'full_name'=>'سمیرا احمدی','username'=>'samira','role'=>'operator','account_number'=>'ACC-00000002','balance'=>-850000,'mobile'=>'09121111111','total_commission'=>1200000,'total_increase'=>2100000,'total_decrease'=>2950000,'last_transaction_at'=>'2026-07-12 12:10:00','account_status'=>'active'],
];
$series = ['labels'=>['۱۴۰۵/۰۲/۰۱','۱۴۰۵/۰۳/۰۱','۱۴۰۵/۰۴/۰۱','۱۴۰۵/۰۵/۰۱','۱۴۰۵/۰۶/۰۱','۱۴۰۵/۰۷/۰۱'],'receipts'=>[1200000,1800000,2400000,2100000,3300000,4100000],'payments'=>[900000,1100000,1750000,1500000,2600000,3000000]];
$recentEntries = [
    ['id'=>10,'full_name'=>'مهدی ربانی','role'=>'admin','mobile'=>'09120000000','entry_number'=>'LED-0010','entry_type'=>'commission','direction'=>'increase','amount'=>550000,'balance_before'=>3650000,'balance_after'=>4200000,'description'=>'کمیسیون قرارداد PR-1500','created_at'=>'2026-07-13 10:30:00'],
    ['id'=>9,'full_name'=>'سمیرا احمدی','role'=>'operator','mobile'=>'09121111111','entry_number'=>'LED-0009','entry_type'=>'payment','direction'=>'decrease','amount'=>700000,'balance_before'=>-150000,'balance_after'=>-850000,'description'=>'پرداخت هفتگی','created_at'=>'2026-07-12 12:10:00'],
];
$topSellers = [
    ['id'=>1,'full_name'=>'مهدی ربانی','role'=>'admin','mobile'=>'09120000000','sales_count'=>18,'financed_total'=>420000000],
    ['id'=>2,'full_name'=>'سمیرا احمدی','role'=>'operator','mobile'=>'09121111111','sales_count'=>12,'financed_total'=>285000000],
];
$accountUser = $accounts[0];
$currentBalance = $balance = 4200000;
$accountSummary = ['total_increase'=>7600000,'total_decrease'=>3400000,'total_commission'=>2800000,'paid_to_user'=>2200000,'received_from_user'=>900000];
$entries = $recentEntries;
$sales = [['id'=>1,'contract_number'=>'PR-1500','customer_name'=>'عاطفه شفیق','seller_name'=>'مهدی ربانی','seller_user_id'=>1,'financed_amount'=>25000000,'sales_channel'=>'فروش حضوری','status'=>'active']];
$commissions = [['id'=>1,'contract_number'=>'PR-1500','seller_name'=>'مهدی ربانی','rule_source'=>'seller','basis_type'=>'financed_amount','basis_amount'=>25000000,'raw_commission'=>500000,'calculated_amount'=>500000,'status'=>'pending']];
$rules = [['name'=>'فروش حضوری','user_name'=>'مهدی ربانی','commission_type'=>'percentage','commission_value'=>'2','calculation_basis'=>'financed_amount','calculation_timing'=>'after_full_settlement','minimum_amount'=>250000,'maximum_amount'=>800000,'requires_approval'=>1,'is_active'=>1]];
$pagination = [];
$step = max(1, min(10, (int) ($_GET['step'] ?? 5)));
$preview = array_merge($preview, ['total'=>24,'cancelled'=>3]);

?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/components/forms.css"><link rel="stylesheet" href="/plugins/PromaAccounting/assets/css/accounting.css"><link rel="stylesheet" href="/plugins/PromaAccounting/assets/css/accounting-responsive.css"><title>Accounting UI QA</title></head><body class="<?= $dark ? 'dark-only' : '' ?>" style="margin:0;background:<?= $dark ? '#171a20' : '#f5f7fa' ?>"><main style="max-width:1440px;margin:auto"><?php include dirname(__DIR__, 2) . '/views/' . (in_array($view, ['settings','rules','dashboard','setup','help','accounts','ledger','sales','commissions','backfill'], true) ? $view : 'settings') . '.php'; ?></main><script src="/html/RTL/assets/js/icons/feather-icon/feather.min.js"></script><script src="/assets/vendor/chart.umd.min.js"></script><script src="/plugins/PromaAccounting/assets/js/accounting.js"></script><script>if(window.feather){window.feather.replace();}</script></body></html>
