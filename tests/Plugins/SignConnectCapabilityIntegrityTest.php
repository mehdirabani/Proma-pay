<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pluginRoot = $root . '/plugins/PromaSignConnect';
$manifest = json_decode((string) file_get_contents($pluginRoot . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$settings = (string) file_get_contents($pluginRoot . '/src/Services/SettingsService.php');
$view = (string) file_get_contents($pluginRoot . '/views/settings-center.php');
$routes = array_column($manifest['routes'] ?? [], 'path');
$permissions = array_column($manifest['permissions'] ?? [], 'key');
$forbiddenRoutes = ['plugin/sign-connect/otp/request', 'plugin/sign-connect/otp/verify'];
$forbiddenPermissions = [
    'plugin.proma-sign-connect.proma_sign.requests.create',
    'plugin.proma-sign-connect.proma_sign.requests.cancel',
    'plugin.proma-sign-connect.proma_sign.documents.view',
    'plugin.proma-sign-connect.proma_sign.documents.download',
    'plugin.proma-sign-connect.proma_sign.integrity.verify',
    'plugin.proma-sign-connect.proma_sign.revoke',
    'plugin.proma-sign-connect.proma_connect.reports.view',
    'plugin.proma-sign-connect.proma_connect.webhooks.manage',
];
foreach ($forbiddenRoutes as $path) {
    if (in_array($path, $routes, true)) throw new RuntimeException('Unintegrated public OTP endpoint remains in the release manifest: ' . $path);
}
foreach ($forbiddenPermissions as $key) {
    if (in_array($key, $permissions, true)) throw new RuntimeException('Manifest advertises an unimplemented capability: ' . $key);
}
if (array_key_exists('otp_login_enabled', $manifest['settings_schema'] ?? []) || str_contains($settings, 'otp_login_enabled') || str_contains($view, 'otp_login_enabled')) {
    throw new RuntimeException('The incomplete OTP login feature must not be enabled or advertised.');
}
$readme = (string) file_get_contents($pluginRoot . '/docs/README.md');
$otpNote = (string) file_get_contents($pluginRoot . '/docs/OPTIONAL_OTP_LOGIN.md');
$testReport = (string) file_get_contents($pluginRoot . '/docs/TEST_REPORT.md');
$bugRegister = (string) file_get_contents($pluginRoot . '/docs/BUG_REGISTER.md');
if (!str_contains($readme, 'ورود مشتری با OTP در این نسخه پشتیبانی نمی‌شود')
    || !str_contains($otpNote, 'پیاده‌سازی نشده و از سطح دسترسی عمومی حذف شده')
    || strpos($testReport, '# گزارش آزمون تاریخی Proma Sign & Connect 1.2.1') !== 0
    || !str_contains($bugRegister, 'Mitigated by de-scoping; not implemented')) {
    throw new RuntimeException('SignConnect docs must not contradict its current OTP login capability.');
}
if (version_compare((string) ($manifest['version'] ?? '0.0.0'), '1.2.4', '<')) throw new RuntimeException('SignConnect patch version was not updated for its manifest/UI changes.');

echo "SIGNCONNECT_CAPABILITY_INTEGRITY_OK\n";
