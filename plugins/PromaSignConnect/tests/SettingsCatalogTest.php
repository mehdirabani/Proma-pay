<?php
$root = dirname(__DIR__);
$errors = [];
$migration = file_get_contents($root . '/migrations/2026_07_30_settings_catalog.sql');
$view = file_get_contents($root . '/views/settings-center.php');
$manifest = json_decode(file_get_contents($root . '/plugin.json'), true);

foreach ([
    'account_login_otp' => 'کد ورود یکبار مصرف',
    'signature_requested' => 'درخواست امضای قرارداد',
    'installment_overdue' => 'معوق شدن قسط',
    'payment_completed' => 'ثبت پرداخت موفق',
    'legal_case_created' => 'تشکیل پرونده حقوقی',
] as $key => $title) {
    if (strpos($migration, "'{$key}','{$title}'") === false) $errors[] = 'catalog:' . $key;
}
foreach (['ippanel','smsir','bale','telegram','events','templates','deliveries','health','retention','audit'] as $section) {
    if (strpos($view, "'{$section}'") === false && strpos($view, "section==='{$section}'") === false) {
        $errors[] = 'section:' . $section;
    }
}
if (strpos($view, 'زیرساخت این بخش فعال است') !== false) $errors[] = 'legacy-placeholder';
if (!in_array('migrations/2026_07_30_settings_catalog.sql', $manifest['migrations'] ?? [], true)) {
    $errors[] = 'manifest-migration';
}
foreach (['proma_connect_event_catalog','proma_connect_provider_event_mappings','proma_connect_template_versions'] as $table) {
    if (strpos($migration, $table) === false) $errors[] = 'table:' . $table;
}

echo $errors ? "FAIL\n" . implode("\n", $errors) . "\n" : "PASS: settings catalog structure\n";
exit($errors ? 1 : 0);

