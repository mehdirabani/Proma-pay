<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$fail = static function (string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); };

$settings = $read('src/Services/SettingsService.php');
$dispatch = $read('src/Services/DispatchService.php');
$service = $read('src/Services/GeneralMessageService.php');
$view = $read('views/settings-center.php');
$manifest = json_decode($read('plugin.json'), true);

foreach (['ippanel','smsir','telegram'] as $provider) {
    if (strpos($settings, "provider_{$provider}_capabilities") === false) $fail("missing {$provider} capability persistence");
}
if (strpos($dispatch, 'provider_capability_disabled') === false) $fail('dispatch does not enforce configured capabilities');
if (strpos($service, "'custom_message'") === false || strpos($service, '100') === false) $fail('general messaging safeguards missing');
if (strpos($view, 'ارسال پیام عمومی') === false || strpos($view, 'messages/send') === false) $fail('general messaging UI missing');
$routes = array_column($manifest['routes'] ?? [], null, 'path');
if (($routes['plugin/sign-connect/messages/send']['permission'] ?? '') !== 'plugin.proma-sign-connect.proma_connect.dispatch') $fail('general messaging route permission missing');

echo "PASS: provider capabilities and queued general messaging\n";
