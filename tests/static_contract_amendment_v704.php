<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$contract = $read('models/Contract.php');
$installment = $read('models/Installment.php');
$contractDocument = $read('models/ContractDocument.php');
$service = $read('helpers/ContractAmendmentService.php');
$change = $read('helpers/InstallmentChangeService.php');
$import = $read('helpers/PreparedExcelImportService.php');
$controller = $read('controllers/ContractsController.php');
$view = $read('views/contracts/_amendment_workspace.php');
$js = $read('assets/js/app.js');
$migration = $read('database/migrations/2026_10_27_v704_contract_amendment_versioning.sql');

$updateStart = strpos($contract, 'public static function updateContract(');
$updateEnd = strpos($contract, 'public static function bulkUpdate(', $updateStart ?: 0);
$assert($updateStart !== false && $updateEnd !== false, 'Contract update boundary is missing.');
$updateBody = substr($contract, $updateStart, $updateEnd - $updateStart);
$assert(strpos($updateBody, 'financialTermsChanged') !== false, 'Direct contract edits do not reject changed protected terms.');
$assert(strpos($updateBody, 'syncInstallmentSchedule(') === false, 'Legacy contract update still invokes schedule synchronization.');

$syncStart = strpos($contract, 'protected static function syncInstallmentSchedule(');
$syncEnd = strpos($contract, 'public static function applyApprovedAmendmentSchedule(', $syncStart ?: 0);
$assert($syncStart !== false && $syncEnd !== false, 'Guarded schedule synchronization is missing.');
$syncBody = substr($contract, $syncStart, $syncEnd - $syncStart);
$assert(strpos($syncBody, 'approvedScheduleSyncAmendmentId') !== false && strpos($syncBody, "\$amendment['status'] ?? '') !== 'approved'") !== false, 'syncInstallmentSchedule does not require an approved amendment context.');
$assert(strpos($syncBody, 'has_history') !== false && strpos($syncBody, "SET status = 'cancelled'") !== false, 'Protected installments are not preserved and superseded logically.');
$assert(stripos($syncBody, 'DELETE FROM installments') === false, 'syncInstallmentSchedule physically deletes installment history.');

$assert(strpos($service, "'pending_approval'") !== false && strpos($service, 'requested_by') !== false && strpos($service, '(int) $amendment[\'requested_by\'] === (int) $actorId') !== false, 'Maker-checker approval boundary is missing.');
$assert(strpos($service, 'preview_fingerprint') !== false && strpos($service, 'automatic_interest_recalculation') !== false, 'Preview is not version-bound or still implies automatic interest recalculation.');
$assert(strpos($service, 'AuditLog::record') !== false && strpos($service, 'Model::begin()') !== false, 'Audit and amendment transitions are not transactionally owned.');
$assert(strpos($service, 'function reconstructVersion(') !== false && strpos($service, 'function compareVersions(') !== false && strpos($service, 'function reconstructAsOf(') !== false, 'Verified reconstruction, comparison, or as-of contract history is missing.');
$assert(strpos($service, 'legacy_baseline_incomplete_history') !== false && strpos($service, 'created_state_approval_unrecorded') !== false, 'Legacy/creation provenance does not avoid fictional approval claims.');
$assert(strpos($change, 'ContractAmendmentService::requestInstallmentChange') !== false && strpos($change, 'Model::execute(\'UPDATE installments') === false, 'Single-installment change service can still apply financial edits directly.');
$assert(strpos($import, 'ContractAmendmentService::isAvailable()') !== false && strpos($import, 'isset($existingContractIds') !== false, 'Excel import can bypass the amendment boundary for existing contracts.');
$assert(strpos($installment, 'افزودن قسط سفارشی در گردش اصلاحیهٔ فعلی پشتیبانی نمی‌شود') !== false, 'Custom installment creation bypasses the amendment boundary.');
$assert(strpos($installment, 'قسط لغوشده، از جمله قسط جایگزین‌شده') !== false, 'Bulk installment action can reactivate superseded history.');
$assert(strpos($controller, 'public function requestAmendment(') !== false && strpos($controller, 'public function previewAmendment(') !== false && strpos($controller, 'public function applyAmendment(') !== false, 'Amendment request, preview, or application route is missing.');
$assert(strpos($view, 'data-contract-amendment-form') !== false && strpos($view, 'data-amendment-preview-fingerprint') !== false, 'Contract amendment workspace is not version-bound.');
$assert(strpos($service, 'ContractDocument::recordAmendmentDraftVersion') !== false && strpos($contractDocument, "'amendment_draft'") !== false, 'Applied financial versions are not linked to an internal addendum draft.');
$assert(strpos($contractDocument, 'is_published, is_finalized, generated_by') !== false && strpos($contractDocument, 'پیش‌نویس الحاقیه تا ثبت مستند رضایت طرف قرارداد قابل انتشار نیست') !== false, 'Amendment addendum is not protected from premature publication/finalization.');
$assert(strpos($contractDocument, 'hasUnconsentedAmendment') !== false && strpos($controller, 'نسخه پیشین پس از اصلاحیه داخلی') !== false, 'Legacy document routes do not preserve the previous version with an explicit internal-amendment warning.');
$assert(strpos($view, '<script>') === false && strpos($js, 'initContractAmendmentWorkspace') !== false, 'Amendment form behavior is not loaded through the shared CSP-safe script.');
foreach (['contract_financial_versions', 'contract_amendments', 'contract_amendment_history', 'base_fingerprint', 'content_hash'] as $needle) {
    $assert(strpos($migration, $needle) !== false, 'Contract amendment migration is missing ' . $needle . '.');
}
$assert(!preg_match('/\b(drop\s+table|truncate\s+table|delete\s+from)\b/i', $migration), 'Contract amendment migration contains destructive SQL.');

echo "STATIC_CONTRACT_AMENDMENT_V704_OK\n";
