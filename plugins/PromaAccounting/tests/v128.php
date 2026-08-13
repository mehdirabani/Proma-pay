<?php
$root = dirname(__DIR__);
$manifest = json_decode(file_get_contents($root . '/plugin.json'), true);
if (version_compare((string) ($manifest['version'] ?? ''), '1.2.8', '<')) { fwrite(STDERR, "version mismatch\n"); exit(1); }
$required = ['migrations/2026_07_28_customer_referrals.sql'];
foreach ($required as $file) if (!is_file($root . '/' . $file)) { fwrite(STDERR, "missing $file\n"); exit(1); }
$sql = file_get_contents($root . '/migrations/2026_07_28_customer_referrals.sql');
foreach (['customer_referral_codes','customer_referrals','customer_referral_commissions','customer_referral_payouts'] as $table) if (strpos($sql, $table) === false) { fwrite(STDERR, "missing table $table\n"); exit(1); }
foreach (($manifest['routes'] ?? []) as $route) if (($route['path'] ?? '') === 'plugin/accounting/referrals') { fwrite(STDERR, "removed route still present\n"); exit(1); }
echo "PROMA_ACCOUNTING_V128_OK\n";
