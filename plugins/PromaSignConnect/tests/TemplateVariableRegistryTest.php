<?php
require_once dirname(__DIR__) . '/src/Services/TemplateVariableRegistry.php';

use Proma\Plugins\SignConnect\Services\TemplateVariableRegistry;

$definitions = TemplateVariableRegistry::definitions();
$errors = [];
foreach (['customer_name','contract_number','otp','expiry_minutes','signature_url','payment_amount'] as $key) {
    if (!isset($definitions[$key])) $errors[] = 'missing:' . $key;
}
foreach (['password','api_key','national_id','session_id','hmac'] as $forbidden) {
    if (isset($definitions[$forbidden])) $errors[] = 'forbidden:' . $forbidden;
}
foreach ($definitions as $key => $definition) {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) $errors[] = 'invalid-key:' . $key;
    if (empty($definition['title']) || !array_key_exists('sample', $definition)) $errors[] = 'invalid-definition:' . $key;
}
echo $errors ? "FAIL\n" . implode("\n", $errors) . "\n" : "PASS: template variable registry\n";
exit($errors ? 1 : 0);

