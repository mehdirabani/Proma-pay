<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8139';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';

require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generated_contract_documents'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['template_version_id', 'template_status', 'rendered_title', 'rendered_header', 'rendered_body'] as $column) {
    $assert(in_array($column, $columns, true), 'Generated contract document column is missing: ' . $column);
}

$contractId = (int) $pdo->query("SELECT id FROM contracts WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
$assert($contractId > 0, 'An active QA contract is required.');

$documentBefore = $pdo->prepare('SELECT * FROM generated_contract_documents WHERE contract_id = ? LIMIT 1');
$documentBefore->execute([$contractId]);
$documentBefore = $documentBefore->fetch();
$versionCutoff = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM contract_document_versions')->fetchColumn();
$logCutoff = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM contract_change_logs')->fetchColumn();

try {
    $body = ContractDocument::generate($contractId, 1);
    $assert($body !== '', 'Contract document generation returned an empty body.');
    $document = ContractDocument::document($contractId);
    $assert(is_array($document), 'Generated contract document was not persisted in the transaction.');
    $assert(array_key_exists('rendered_title', $document), 'Generated contract document does not expose rendered_title.');
    $assert(array_key_exists('rendered_header', $document), 'Generated contract document does not expose rendered_header.');
} finally {
    $pdo->prepare('DELETE FROM contract_document_versions WHERE contract_id = ? AND id > ?')->execute([$contractId, $versionCutoff]);
    $pdo->prepare('DELETE FROM contract_change_logs WHERE contract_id = ? AND id > ?')->execute([$contractId, $logCutoff]);

    if ($documentBefore) {
        $pdo->prepare(
            'UPDATE generated_contract_documents
             SET template_version_id = ?, template_status = ?, rendered_title = ?, rendered_header = ?, rendered_body = ?, generated_by = ?, created_at = ?, updated_at = ?
             WHERE id = ?'
        )->execute([
            $documentBefore['template_version_id'],
            $documentBefore['template_status'],
            $documentBefore['rendered_title'],
            $documentBefore['rendered_header'],
            $documentBefore['rendered_body'],
            $documentBefore['generated_by'],
            $documentBefore['created_at'],
            $documentBefore['updated_at'],
            $documentBefore['id'],
        ]);
    } else {
        $pdo->prepare('DELETE FROM generated_contract_documents WHERE contract_id = ?')->execute([$contractId]);
    }
}

echo "INTEGRATION_V139_CONTRACT_PRINT_SCHEMA_OK\n";
