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
$summary = ['users'=>12,'sales'=>146,'commission'=>5300000,'ledger'=>12800000];
$accounts = [['id'=>1,'full_name'=>'مهدی ربانی','role'=>'admin','account_number'=>'ACC-00000001','balance'=>4200000,'mobile'=>'09120000000']];
$rules = [['name'=>'فروش حضوری','user_name'=>'مهدی ربانی','commission_type'=>'percentage','commission_value'=>'2','calculation_basis'=>'financed_amount','calculation_timing'=>'after_full_settlement','requires_approval'=>1]];
$pagination = [];
$step = max(1, min(10, (int) ($_GET['step'] ?? 5)));

?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/components/forms.css"><link rel="stylesheet" href="/plugins/PromaAccounting/assets/css/accounting.css"><title>Accounting UI QA</title></head><body class="<?= $dark ? 'dark-only' : '' ?>" style="margin:0;background:<?= $dark ? '#171a20' : '#f5f7fa' ?>"><main style="max-width:1440px;margin:auto"><?php include dirname(__DIR__, 2) . '/views/' . (in_array($view, ['settings','rules','dashboard','setup','help'], true) ? $view : 'settings') . '.php'; ?></main><script src="/plugins/PromaAccounting/assets/js/accounting.js"></script></body></html>
