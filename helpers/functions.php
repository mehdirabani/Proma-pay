<?php

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_) {
            if ($key !== $expectedKey++) {
                return false;
            }
        }
        return true;
    }
}

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

function app_version_info()
{
    static $version;
    if ($version === null) {
        $path = __DIR__ . '/../config/version.php';
        $version = is_file($path) ? (require $path) : [];
    }
    return is_array($version) ? $version : [];
}

function app_version($default = '1.0.0')
{
    return trim((string) (app_version_info()['application'] ?? $default));
}

function app_version_display($default = 'V1.0.0')
{
    $value = trim((string) (app_version_info()['display'] ?? ''));
    return $value !== '' ? $value : 'V' . ltrim(app_version($default), 'vV');
}

function plugin_api_version($default = '1.0')
{
    return trim((string) (app_version_info()['plugin_api'] ?? $default));
}

function setting_enabled($key, $default = true)
{
    try {
        $value = Settings::get($key, $default ? '1' : '0');
    } catch (Throwable $e) {
        $value = $default ? '1' : '0';
    }
    return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
}

function ecommerce_is_enabled()
{
    return setting_enabled('ecommerce_enabled', true);
}

function landing_is_enabled()
{
    return ecommerce_is_enabled() && setting_enabled('landing_enabled', true);
}

function app_version_label()
{
    return 'v' . ltrim(app_version(), 'vV');
}

function plugin_is_active($pluginId)
{
    try {
        return class_exists('PluginManager') && PluginManager::isActive($pluginId);
    } catch (Throwable $e) {
        return false;
    }
}

function avatar_catalog()
{
    return [
        'avatar-1' => ['label' => 'آواتار ۱', 'file' => 'assets/images/avatars/avatar-1.png'],
        'avatar-2' => ['label' => 'آواتار ۲', 'file' => 'assets/images/avatars/avatar-2.png'],
        'avatar-3' => ['label' => 'آواتار ۳', 'file' => 'assets/images/avatars/avatar-3.png'],
        'avatar-4' => ['label' => 'آواتار ۴', 'file' => 'assets/images/avatars/avatar-4.png'],
        'avatar-5' => ['label' => 'آواتار ۵', 'file' => 'assets/images/avatars/avatar-5.png'],
        'avatar-6' => ['label' => 'آواتار ۶', 'file' => 'assets/images/avatars/avatar-6.png'],
    ];
}

function avatar_options()
{
    return array_keys(avatar_catalog());
}

function normalize_avatar_key($value)
{
    $value = trim((string) $value);
    return in_array($value, avatar_options(), true) ? $value : 'avatar-1';
}

function avatar_key_for($value = null, $seed = null)
{
    $value = trim((string) $value);
    if (in_array($value, avatar_options(), true)) {
        return $value;
    }
    $seed = trim((string) $seed);
    $hash = $seed === '' ? 0 : abs((int) crc32($seed));
    $options = avatar_options();
    return $options[$hash % count($options)] ?? $options[0];
}

function avatar_suggestion_for($fullName, $role = '')
{
    $firstName = trim(preg_split('/\s+/u', (string) $fullName)[0] ?? '');
    $categories = ['female', 'male', 'neutral', 'unknown'];
    $seed = $firstName !== '' ? $firstName : ((string) $role ?: 'neutral');
    $index = abs((int) crc32($seed)) % count($categories);
    $category = $categories[$index];
    $options = avatar_options();
    $key = $options[abs((int) crc32($category . '|' . $seed)) % count($options)] ?? ($options[0] ?? 'avatar-1');
    return [
        'key' => $key,
        'category' => $category,
        'source' => $firstName !== '' ? 'local_name_map' : 'fallback',
        'reason' => $firstName !== '' ? 'پیشنهاد محلی بر اساس نام کوچک؛ بدون ارسال اطلاعات به سرویس خارجی.' : 'نام کوچک موجود نبود؛ آواتار خنثی انتخاب شد.',
    ];
}

function avatar_asset_url($value)
{
    $key = avatar_key_for($value);
    return asset_url(avatar_catalog()[$key]['file']);
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

function render_pagination(array $pagination, callable $urlBuilder)
{
    $pages = max(1, (int) ($pagination['pages'] ?? 1));
    if ($pages <= 1) {
        return '';
    }
    $current = max(1, min($pages, (int) ($pagination['page'] ?? 1)));
    $start = max(1, $current - 2);
    $end = min($pages, $current + 2);

    ob_start();
    ?>
    <nav class="proma-pagination" aria-label="صفحه‌بندی">
      <?php if ($current > 1): ?>
        <a class="tab-link" href="<?= e($urlBuilder($current - 1)) ?>">قبلی</a>
      <?php endif; ?>
      <?php if ($start > 1): ?>
        <a class="tab-link" href="<?= e($urlBuilder(1)) ?>"><?= to_persian_digits(1) ?></a>
        <?php if ($start > 2): ?><span class="proma-pagination-ellipsis">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($page = $start; $page <= $end; $page++): ?>
        <a class="tab-link <?= $page === $current ? 'active' : '' ?>" href="<?= e($urlBuilder($page)) ?>"><?= to_persian_digits($page) ?></a>
      <?php endfor; ?>
      <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="proma-pagination-ellipsis">…</span><?php endif; ?>
        <a class="tab-link" href="<?= e($urlBuilder($pages)) ?>"><?= to_persian_digits($pages) ?></a>
      <?php endif; ?>
      <?php if ($current < $pages): ?>
        <a class="tab-link" href="<?= e($urlBuilder($current + 1)) ?>">بعدی</a>
      <?php endif; ?>
    </nav>
    <?php
    return trim(ob_get_clean());
}

function is_https_request()
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto !== '' && strpos($forwardedProto, 'https') !== false) {
        return true;
    }

    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
        return true;
    }

    $frontEndHttps = strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''));
    if ($frontEndHttps === 'on' || $frontEndHttps === '1') {
        return true;
    }

    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return $cfVisitor !== '' && stripos($cfVisitor, '"scheme":"https"') !== false;
}

function app_base_url()
{
    $configured = rtrim(app_config('base_url', ''), '/');
    if ($configured !== '') {
        if (preg_match('#^https?://#i', $configured)) {
            return $configured;
        }
        return '/' . trim($configured, '/');
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Windows PHP may return a backslash for dirname('/index.php'). Normalize
    // the directory again so generated redirects stay valid on every OS.
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    return ($dir === '' || $dir === '.') ? '' : $dir;
}

function app_origin()
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    return (is_https_request() ? 'https' : 'http') . '://' . $host;
}

function detected_base_url()
{
    $origin = app_origin();
    if ($origin === '') {
        return app_base_url();
    }
    return rtrim($origin . app_base_url(), '/');
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

function configured_social_links(array $settings)
{
    $items = [
        'instagram' => ['label' => 'اینستاگرام', 'setting' => 'social_instagram_url', 'icon' => 'instagram', 'class' => 'instagram'],
        'telegram' => ['label' => 'تلگرام', 'setting' => 'social_telegram_url', 'icon' => 'send', 'class' => 'telegram'],
        'whatsapp' => ['label' => 'واتساپ', 'setting' => 'social_whatsapp_url', 'icon' => 'message-circle', 'class' => 'whatsapp'],
        'website' => ['label' => 'وب‌سایت', 'setting' => 'social_website_url', 'icon' => 'globe', 'class' => 'website'],
        'facebook' => ['label' => 'فیسبوک', 'setting' => 'social_facebook_url', 'icon' => 'facebook', 'class' => 'facebook'],
        'x' => ['label' => 'ایکس', 'setting' => 'social_x_url', 'icon' => 'twitter', 'class' => 'x'],
        'youtube' => ['label' => 'یوتیوب', 'setting' => 'social_youtube_url', 'icon' => 'youtube', 'class' => 'youtube'],
        'linkedin' => ['label' => 'لینکدین', 'setting' => 'social_linkedin_url', 'icon' => 'linkedin', 'class' => 'linkedin'],
    ];
    $links = [];
    foreach ($items as $item) {
        $target = trim((string) ($settings[$item['setting']] ?? ''));
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            continue;
        }
        $item['url'] = $target;
        $links[] = $item;
    }
    return $links;
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

function is_ajax_request()
{
    if ((string) ($_GET['ajax'] ?? '') === '1') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
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
    return MoneyMath::amount($value);
}

function money_toman($value)
{
    return to_persian_digits(number_format(normalize_money($value), 0)) . ' تومان';
}

function penalty_display_html(array $item)
{
    $current = normalize_money($item['penalty'] ?? $item['calculated_penalty'] ?? 0);
    $normal = normalize_money($item['normal_penalty'] ?? $current);
    $legal = normalize_money($item['legal_penalty'] ?? $current);
    $mode = (string) ($item['penalty_mode'] ?? 'normal');

    if ($mode !== 'legal' && $legal > $current) {
        return '<span class="proma-penalty-display proma-penalty-display--discounted">'
            . '<del title="جریمه حقوقی در صورت ورود پرونده به شکایت">' . money_toman($legal) . '</del>'
            . '<strong>' . money_toman($current) . '</strong>'
            . '<small>جریمه قابل اعمال</small>'
            . '</span>';
    }

    $label = $mode === 'legal' ? 'جریمه حقوقی' : ($normal > 0 ? 'جریمه عادی' : '');
    return '<span class="proma-penalty-display' . ($mode === 'legal' ? ' proma-penalty-display--legal' : '') . '">'
        . '<strong>' . money_toman($current) . '</strong>'
        . ($label !== '' ? '<small>' . e($label) . '</small>' : '')
        . '</span>';
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

function current_user_can_view_users()
{
    $user = Auth::user();
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if (($user['role'] ?? '') === 'operator') {
        return false;
    }
    return is_staff_role($user['role'] ?? '') && (int) ($user['is_department_manager'] ?? 0) === 1;
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
        'new' => 'info',
        'draft' => 'muted',
        'processing' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'reviewing' => 'warning',
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
        'discovered' => 'info',
        'update_available' => 'warning',
        'removed' => 'muted',
        'installed' => 'info',
        'uninstalled' => 'muted',
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

function jdatetime($dateTime)
{
    if (!$dateTime) {
        return '';
    }
    $timestamp = is_numeric($dateTime) ? (int) $dateTime : strtotime((string) $dateTime);
    if (!$timestamp) {
        return '';
    }
    return jdate(date('Y-m-d', $timestamp)) . ' ' . to_persian_digits(date('H:i', $timestamp));
}

function relative_time($dateTime)
{
    if (!$dateTime) {
        return '';
    }
    $timestamp = is_numeric($dateTime) ? (int) $dateTime : strtotime((string) $dateTime);
    if (!$timestamp) {
        return '';
    }
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'همین حالا';
    }
    if ($diff < 3600) {
        return to_persian_digits((int) floor($diff / 60)) . ' دقیقه پیش';
    }
    if ($diff < 86400) {
        return to_persian_digits((int) floor($diff / 3600)) . ' ساعت پیش';
    }
    if ($diff < 604800) {
        return to_persian_digits((int) floor($diff / 86400)) . ' روز پیش';
    }
    return jdate(date('Y-m-d', $timestamp));
}

function normalize_time($value)
{
    $value = trim(to_english_digits((string) $value));
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
        return null;
    }
    return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
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

function normalize_card_number($value)
{
    return preg_replace('/\D+/', '', to_english_digits((string) $value));
}

function format_card_number($value)
{
    $value = normalize_card_number($value);
    if ($value === '') {
        return '';
    }
    return trim(chunk_split($value, 4, ' '));
}

function normalize_account_number($value)
{
    return preg_replace('/\D+/', '', to_english_digits((string) $value));
}

function format_account_number($value)
{
    $value = normalize_account_number($value);
    if ($value === '') {
        return '';
    }
    return trim(chunk_split($value, 4, ' '));
}

function normalize_sheba($value)
{
    $value = strtoupper(trim(to_english_digits((string) $value)));
    $value = preg_replace('/[^0-9A-Z]/', '', $value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/^IR/', '', $value);
    $value = preg_replace('/\D+/', '', $value);
    return $value === '' ? '' : 'IR' . $value;
}

function format_sheba($value)
{
    $value = normalize_sheba($value);
    if ($value === '') {
        return '';
    }
    $digits = substr($value, 2);
    return 'IR ' . trim(chunk_split($digits, 4, ' '));
}

function payment_card_logo_mark($bankName, $logoText = '')
{
    $logoText = trim((string) $logoText);
    if ($logoText !== '') {
        return function_exists('mb_substr') ? mb_substr($logoText, 0, 4, 'UTF-8') : substr($logoText, 0, 4);
    }
    $bankName = trim((string) $bankName);
    if ($bankName === '') {
        return 'PR';
    }
    if (preg_match_all('/\p{L}+/u', $bankName, $matches) && !empty($matches[0])) {
        $mark = '';
        foreach ($matches[0] as $word) {
            $mark .= function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
            if ((function_exists('mb_strlen') ? mb_strlen($mark, 'UTF-8') : strlen($mark)) >= 2) {
                break;
            }
        }
        if ($mark !== '') {
            return $mark;
        }
    }
    return function_exists('mb_substr') ? (mb_substr($bankName, 0, 2, 'UTF-8') ?: 'PR') : (substr($bankName, 0, 2) ?: 'PR');
}

function sanitize_hex_color($value, $fallback = '#7366ff')
{
    $value = trim((string) $value);
    if (preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
        return strtolower($value);
    }
    return $fallback;
}
