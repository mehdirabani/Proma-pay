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
    // فروشگاه داخلی از نسخه 1.5.9 بازنشسته است. داده‌های `ecommerce_*`
    // صرفاً برای بایگانی خواندنی حفظ می‌شوند و فروش جدید فقط از اتصال خارجی
    // (مانند WooCommerce) وارد سامانه خواهد شد.
    return false;
}

function landing_is_enabled()
{
    return false;
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

function user_avatar_asset_url(array $user)
{
    if (!empty($user['id']) && !empty($user['avatar_path'])) {
        return url('profile/avatarFile/' . (int) $user['id'], [
            'v' => max(1, (int) ($user['avatar_version'] ?? 1)),
        ]);
    }
    return avatar_asset_url(avatar_key_for($user['avatar_key'] ?? null, $user['id'] ?? ($user['full_name'] ?? '')));
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
    if (class_exists('Auth', false)) {
        $released = Auth::takeReleasedFlash($key);
        if ($released !== null) {
            return $released;
        }
    }
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $value = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function set_flash($key, $value)
{
    $releaseAfterWrite = class_exists('Auth', false) && Auth::sessionWasReleased();
    if ($releaseAfterWrite && !Auth::ensureSessionWritable()) {
        return;
    }
    $_SESSION['_flash'][$key] = $value;
    if ($releaseAfterWrite) {
        Auth::commitSessionWrite();
    }
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
    $current = normalize_money($item['effective_penalty_payable'] ?? $item['penalty'] ?? $item['calculated_penalty'] ?? 0);
    $normal = normalize_money($item['normal_penalty_accrued'] ?? $item['normal_penalty'] ?? $current);
    $legal = normalize_money($item['legal_penalty_accrued'] ?? $item['legal_penalty'] ?? 0);
    $projected = normalize_money($item['projected_legal_penalty'] ?? 0);
    $mode = (string) ($item['penalty_mode'] ?? 'normal');

    if (!empty($item['show_projected_legal_penalty']) && $projected > 0) {
        return '<span class="proma-penalty-display proma-penalty-display--discounted" title="فقط برآورد مقایسه‌ای؛ در بدهی و پرداخت لحاظ نشده است">'
            . '<small>جریمه عادی قابل پرداخت</small>'
            . '<strong>' . money_toman($current) . '</strong>'
            . '<del>جریمه حقوقی احتمالی: ' . money_toman($projected) . '</del>'
            . '<small><span class="badge muted">فعلاً اعمال نشده</span> در مبلغ قابل پرداخت امروز محاسبه نشده است.</small>'
            . '</span>';
    }

    if ($mode === 'legal' || $legal > 0) {
        return '<span class="proma-penalty-display proma-penalty-display--legal">'
            . '<strong>' . money_toman($current) . '</strong>'
            . '<small>جریمه عادی تا تاریخ ارجاع: ' . money_toman($normal) . '</small>'
            . '<small>جریمه حقوقی پس از ارجاع: ' . money_toman($legal) . '</small>'
            . '</span>';
    }

    $label = $normal > 0 ? 'جریمه عادی قابل پرداخت' : '';
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
        'validating' => 'warning',
        'activating' => 'warning',
        'deactivating' => 'warning',
        'installing' => 'warning',
        'updating' => 'warning',
        'uninstalling' => 'warning',
        'error' => 'danger',
        'recovery_required' => 'danger',
        'installation_failed' => 'danger',
        'migration_failed' => 'danger',
        'health_failed' => 'danger',
        'repair_required' => 'danger',
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

function normalize_iran_phone($value)
{
    $digits = preg_replace('/\D+/', '', to_english_digits((string) $value));
    if (strpos($digits, '0098') === 0) {
        $digits = substr($digits, 4);
    } elseif (strpos($digits, '98') === 0) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }
    return preg_match('/^9\d{9}$/', $digits) ? '+98' . $digits : '';
}

function format_iran_phone($value, $persianDigits = true)
{
    $canonical = normalize_iran_phone($value);
    if ($canonical === '') {
        return trim((string) $value);
    }
    $local = '0' . substr($canonical, 3);
    $formatted = substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7, 4);
    return $persianDigits ? to_persian_digits($formatted) : $formatted;
}

function proma_icon($name, $title = '', $class = '')
{
    $icons = [
        'archive' => '<path d="M3 7h18v13H3z"/><path d="M2 3h20v4H2z"/><path d="M10 12h4"/>',
        'award' => '<circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/>',
        'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22.5z"/><path d="M4 4.5v18"/>',
        'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'close' => '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'more' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.5 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.6 1.9Z"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'slash' => '<path d="m4 4 16 16"/><circle cx="12" cy="12" r="9"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/><path d="M10 11v5M14 11v5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/>',
    ];
    $name = isset($icons[$name]) ? $name : 'file';
    $attributes = 'class="proma-icon ' . e(trim($class)) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
    if ($title !== '') {
        $attributes = 'class="proma-icon ' . e(trim($class)) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="' . e($title) . '" focusable="false"';
    }
    return '<svg ' . $attributes . '>' . $icons[$name] . '</svg>';
}
