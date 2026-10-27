<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) throw new RuntimeException('Missing file: ' . $path);
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$version = require $root . '/config/version.php';
$engine = $read('helpers/FinancialReconciliationEngine.php');
$center = $read('helpers/FinancialReconciliationCenter.php');
$installments = $read('helpers/InstallmentReconciliationService.php');
$controller = $read('controllers/FinancialReconciliationController.php');
$migration = $read('database/migrations/2026_10_27_v704_financial_reconciliation_center.sql');
$issueTypeMigration = $read('database/migrations/2026_10_27_v704_financial_reconciliation_issue_type.sql');
$installSchema = $read('database/proma-pay-install.sql');
$detailView = $read('views/financial-reconciliation/show.php');
$gate = $read('tools/release-gate.php');

$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '7.0.4', '>='), 'The reconciliation feature must remain available in Core 7.0.4 and later.');
foreach (['financial_reconciliation_runs', 'financial_reconciliation_discrepancies', 'financial_reconciliation_history', 'financial_reconciliation_proposals'] as $table) {
    $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table), 'Additive migration is missing ' . $table . '.');
    $assert(str_contains($installSchema, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), 'Fresh-install schema is missing ' . $table . '.');
}
$assert(str_contains($migration, 'issue_type VARCHAR(80) NOT NULL') && str_contains($installSchema, '`issue_type` varchar(80) NOT NULL'), 'Discrepancy records must persist their exact finding kind.');
$assert(str_contains($issueTypeMigration, 'information_schema.COLUMNS') && str_contains($issueTypeMigration, 'ADD COLUMN issue_type'), 'Existing center installations need an idempotent issue-type migration.');
$assert(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER)\s+/i', $engine), 'The reconciliation engine must remain read-only.');
$assert(str_contains($engine, 'InstallmentReconciliationService::scan'), 'The engine must reuse the established installment reconciliation service.');
$assert(str_contains($engine, "'coverage_is_proven_for_missing_events' => false"), 'A first observed ledger event must not be treated as proof of complete coverage.');
$assert(str_contains($engine, 'contract_source_scan_limit_reached') && str_contains($engine, "'complete' => !\$truncatedSources"), 'A bounded but incomplete source scan must be identified.');
$assert(str_contains($installments, 'public static function apply(array $issues)') && str_contains($installments, 'throw new LogicException'), 'Legacy stale issue snapshots must never be accepted as repair commands.');
$assert(str_contains($installments, 'public static function applyApprovedMaterializationRefresh') && str_contains($installments, 'Auth::role() !== \'admin\''), 'Installment repair must use the secured canonical service API.');
$assert(str_contains($installments, 'Model::db()->inTransaction()') && str_contains($installments, 'hash_equals($expectedFingerprint'), 'Approved repair must require a transaction and fresh source fingerprint.');
$assert(str_contains($center, 'InstallmentReconciliationService::applyApprovedMaterializationRefresh') && !preg_match('/UPDATE\s+installments\b/i', $center), 'The workflow must delegate installment repair to the existing service.');
$assert(str_contains($center, '(int) $proposal[\'proposed_by\'] === $actorId'), 'Proposal author must not approve their own repair.');
$assert(str_contains($center, "\$result['idempotent_replay'] = true"), 'Repeating an applied proposal must be idempotent.');
$assert(str_contains($center, 'AuditLog::record') && str_contains($center, 'reconciliation.reverified'), 'Resolution must record an audit trail and reverification in the same transaction.');
$assert(str_contains($center, 'if (empty($inspection[\'complete\']))') && str_contains($center, "'partial'"), 'Incomplete scans must be kept partial.');
$assert(!preg_match('/UPDATE\s+(?:payments|payment_groups|payment_allocations|financial_ledger_events|financial_ledger_lines)\b/i', $center), 'The discrepancy workflow must not directly rewrite payment or ledger sources.');
$assert(str_contains($controller, 'requireRole') && str_contains($controller, 'onlyPost') && str_contains($controller, 'FinancialReconciliationCenter::'), 'The controller must enforce role checks, POST/CSRF protection, and service boundaries.');
$assert(str_contains($detailView, 'گروه پرداخت') && str_contains($detailView, 'وضعیت ذخیره‌شدهٔ قسط'), 'The case detail must expose payment group and raw installment evidence.');
$assert(str_contains($detailView, "(\$item['status'] ?? '') === 'under_review'"), 'Installment correction proposals must only be offered after a case enters review.');
$assert(str_contains($gate, "tests/static_financial_reconciliation_v704.php"), 'The reconciliation safety checks must run through the release gate.');

echo "STATIC_FINANCIAL_RECONCILIATION_V704_OK\n";
