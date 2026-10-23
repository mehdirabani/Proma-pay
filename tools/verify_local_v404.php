<?php
declare(strict_types=1);
$app = 'C:/laragon/www/proma';
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.50'; require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== 'proma_pay_restore_20261007') throw new RuntimeException('Unexpected local DB');
$zip = new ZipArchive(); $zip->open(dirname(__DIR__) . '/dist/candidates/archive/v4/v4.0.4/updates/PromaPay-Update-v4.0.4-from-v4.0.2.zip');
$manifest = json_decode($zip->getFromName('proma-update.json'), true, 512, JSON_THROW_ON_ERROR); $zip->close();
foreach ($manifest['files'] as $file) {
    $target = $file['target'] ?? $file['source'];
    if (!is_file($app . '/' . $target) || !hash_equals($file['sha256'], hash_file('sha256', $app . '/' . $target))) throw new RuntimeException('Local file differs from final 404: ' . $target);
}
SchemaGuard::requireColumns('payment_requests', ['contract_id','payment_group_id','response_code','error_message','updated_at']);
SchemaGuard::requireColumns('system_outbox', ['event_type','max_attempts','next_attempt_at']);
SchemaGuard::requireColumns('users', ['customer_password_mode']);
echo "LOCAL_V404_FINAL_FILES_AND_PAYMENT_SCHEMA_VERIFIED\n";
