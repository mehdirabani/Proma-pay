<?php

declare(strict_types=1);

$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8138';
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

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$token = bin2hex(random_bytes(8));
$relativePath = 'storage/secure_uploads/file-manager/qa/v138-' . $token . '.txt';
$absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$fileIds = [];

try {
    if (!is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }
    file_put_contents($absolutePath, 'file registry integration test');
    $fileId = FileRecord::registerStored($relativePath, ['name' => 'qa-contract.txt'], [
        'uploader_user_id' => 1,
        'uploader_role' => 'admin',
        'category' => 'contracts',
        'visibility' => 'private',
        'source' => 'integration_test',
    ]);
    $assert(is_int($fileId) && $fileId > 0, 'File registry did not create a file record.');
    $fileIds[] = $fileId;

    $file = FileRecord::find($fileId);
    $assert(($file['storage_path'] ?? '') === $relativePath, 'File record storage path is incorrect.');
    $assert(($file['status'] ?? '') === 'active', 'New file should be active.');
    $assert(FileRecord::relatePath($relativePath, 'contract', 1, 'contract_attachment', 1), 'File relation was not created.');
    $assert(count(FileRecord::relations($fileId)) === 1, 'File relation was not persisted.');

    $updated = FileRecord::updateMetadata((string) $file['file_uuid'], [
        'display_name' => 'نسخه تست قرارداد',
        'category' => 'legal',
        'description' => 'تست ویرایش اطلاعات فایل',
        'tags' => 'test, contract',
        'visibility' => 'internal',
    ], 1);
    $assert(($updated['display_name'] ?? '') === 'نسخه تست قرارداد', 'File metadata update failed.');
    $assert(($updated['visibility'] ?? '') === 'internal', 'File visibility update failed.');

    FileRecord::archive((string) $file['file_uuid'], 1, 'integration archive');
    $assert((FileRecord::find($fileId)['status'] ?? '') === 'archived', 'File archive failed.');
    FileRecord::restore((string) $file['file_uuid'], 1, 'integration restore');
    $assert((FileRecord::find($fileId)['status'] ?? '') === 'active', 'File restore failed.');
    FileRecord::softDelete((string) $file['file_uuid'], 1, 'integration soft delete');
    $assert((FileRecord::find($fileId)['status'] ?? '') === 'deleted', 'File soft delete failed.');
    FileRecord::restore((string) $file['file_uuid'], 1, 'integration restore from deleted');
    $assert((FileRecord::find($fileId)['status'] ?? '') === 'active', 'Deleted file could not be restored.');

    $auditCount = (int) $pdo->query('SELECT COUNT(*) FROM file_audit_logs WHERE file_id = ' . $fileId)->fetchColumn();
    $assert($auditCount >= 5, 'File audit history is incomplete.');

    echo "INTEGRATION_V138_FILE_REGISTRY_OK\n";
} finally {
    if ($fileIds) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $pdo->prepare('DELETE FROM file_audit_logs WHERE file_id IN (' . $placeholders . ')')->execute($fileIds);
        $pdo->prepare('DELETE FROM file_relations WHERE file_id IN (' . $placeholders . ')')->execute($fileIds);
        $pdo->prepare('DELETE FROM files WHERE id IN (' . $placeholders . ')')->execute($fileIds);
    }
    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}
