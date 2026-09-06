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

$document = $read('models/ContractDocument.php');
$contract = $read('models/Contract.php');
$controller = $read('controllers/ContractsController.php');
$migration = $read('database/migrations/2026_09_06_contract_guarantor_snapshots.sql');

$assert(strpos($document, 'function guarantorsForDocument') !== false, 'Canonical guarantor document read-model is missing.');
$assert(strpos($document, 'FROM contract_guarantors cg') !== false, 'Existing contract guarantors are absent from the document read-model.');
$assert(strpos($document, 'contract_guarantor_people') !== false, 'New contract-bound guarantors are absent from the document read-model.');
$assert(strpos($document, "'{{guarantors_section}}' => self::guarantorsSection(\$guarantors)") !== false, 'Document rendering still bypasses the canonical guarantor read-model.');
$assert(strpos($document, "'{{signature_section}}' => self::signatureSection(\$guarantors)") !== false, 'Signature rendering still bypasses the canonical guarantor read-model.');
$assert(strpos($document, 'function viewModel') !== false, 'Shared preview/print document view-model is missing.');
$assert(strpos($contract, 'ContractDocument::snapshotLinkedGuarantors') !== false, 'Linked guarantor snapshot is not captured during contract save.');
$assert(strpos($controller, 'ContractDocument::viewModel') !== false, 'Preview/print routes do not use the shared document view-model.');
$assert(strpos($controller, "'guarantors' => \$contractDocument['guarantors']") !== false, 'Contract details use a different guarantor read-model than print.');
$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS contract_guarantor_snapshots') !== false, 'Guarantor snapshot migration is missing.');
$assert(strpos($migration, 'INSERT IGNORE INTO contract_guarantor_snapshots') !== false, 'Legacy guarantor snapshots are not backfilled.');

echo "STATIC_V159_GUARANTOR_DOCUMENT_OK\n";
