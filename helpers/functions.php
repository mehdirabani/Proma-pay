<?php

function app_config($key = null, $default = null)
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/settings.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url($route = '', array $params = [])
{
    $base = app_base_url();
    $query = array_merge(['route' => trim($route, '/')], $params);
    if ($query['route'] === '') {
        unset($query['route']);
    }
    $suffix = http_build_query($query);
    return $base . '/index.php' . ($suffix ? '?' . $suffix : '');
}

function app_base_url()
{
    $configured = rtrim(app_config('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($script), '/');
    return ($dir === '' || $dir === '.') ? '' : $dir;
}

function asset_url($path)
{
    $version = app_config('asset_version', '1');
    return app_base_url() . '/' . ltrim($path, '/') . '?v=' . rawurlencode($version);
}

function template_asset_url($path)
{
    return asset_url('html/RTL/assets/' . ltrim($path, '/'));
}

function redirect($route, array $params = [])
{
    header('Location: ' . url($route, $params));
    exit;
}

function redirect_raw($target)
{
    header('Location: ' . $target);
    exit;
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash($key)
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $value = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function set_flash($key, $value)
{
    $_SESSION['_flash'][$key] = $value;
}

function to_english_digits($value)
{
    $map = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];
    return strtr((string) $value, $map);
}

function to_persian_digits($value)
{
    return strtr((string) $value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

function normalize_money($value)
{
    $value = to_english_digits($value);
    $value = str_replace([',', '،', ' ', 'تومان'], '', $value);
    return max(0, (float) $value);
}

function money_toman($value)
{
    return to_persian_digits(number_format(ceil((float) $value), 0)) . ' تومان';
}

function percent_label($value)
{
    return to_persian_digits(rtrim(rtrim(number_format((float) $value, 2), '0'), '.')) . '٪';
}

function role_label($role)
{
    return app_config('roles', [])[$role] ?? 'نامشخص';
}

function department_label($department)
{
    return app_config('departments', [])[$department ?: ''] ?? 'بدون واحد';
}

function is_staff_role($role)
{
    return in_array($role, ['admin', 'operator', 'lawyer'], true);
}

function legal_stage_label($stage)
{
    return app_config('legal_stages', [])[$stage ?: ''] ?? ($stage ?: 'ثبت اولیه');
}

function status_label($status)
{
    return app_config('statuses', [])[$status] ?? 'نامشخص';
}

function payment_method_label($method)
{
    return app_config('payment_methods', [])[$method] ?? 'نامشخص';
}

function payment_type_label($type)
{
    return app_config('payment_types', [])[$type ?: 'installment'] ?? 'پرداخت قسط';
}

function badge_class($status)
{
    $map = [
        'paid' => 'success',
        'corrected' => 'muted',
        'active' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'overdue' => 'danger',
        'failed' => 'danger',
        'inactive' => 'muted',
        'open' => 'info',
        'closed' => 'success',
        'approved' => 'success',
        'confirmed' => 'success',
        'applied' => 'success',
        'previewed' => 'warning',
        'uploaded' => 'info',
        'raw' => 'muted',
        'referred' => 'warning',
        'rejected' => 'danger',
    ];
    return $map[$status] ?? 'muted';
}

function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int) ($days / 12053));
    $days %= 12053;
    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian($jy, $jm, $jd)
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int) ($jy / 33)) * 8) + (int) ((($jy % 33) + 3) / 4) + $jd;
    $days += ($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186);
    $gy = 400 * (int) ($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int) (--$days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

function jdate($date)
{
    if (!$date) {
        return '';
    }
    $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if (!$timestamp) {
        return '';
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $timestamp), (int) date('n', $timestamp), (int) date('j', $timestamp));
    return to_persian_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
}

function parse_jalali_date($value)
{
    $value = trim(to_english_digits($value));
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $matches)) {
        return null;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function selected($actual, $expected)
{
    return (string) $actual === (string) $expected ? ' selected' : '';
}

function checked($actual, $expected = true)
{
    return (string) $actual === (string) $expected ? ' checked' : '';
}
