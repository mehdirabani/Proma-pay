<?php

declare(strict_types=1);

/** Real-MariaDB test for scoped customer-file listing without path leakage. */
$dsn = getenv('PROMA_TEST_DB_DSN') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.204';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$suffix = substr(hash('sha256', (string) hrtime(true)), 0, 10);
$digits = static function (string $seed): string { return substr(preg_replace('/\D/', '7', hash('sha256', $seed)), 0, 10); };
$customerMobile = '0915' . substr($digits('customer-mobile-' . $suffix), 0, 7);
$otherMobile = '0935' . substr($digits('other-mobile-' . $suffix), 0, 7);
$customerId = User::create(['role' => 'customer', 'username' => $digits('customer-user-' . $suffix), 'full_name' => 'مشتری مدارک آزمون', 'national_id' => $digits('customer-national-' . $suffix), 'mobile' => $customerMobile, 'email' => 'qa-doc-customer-' . $suffix . '@example.test', 'password' => substr($customerMobile, -4), 'status' => 'active']);
$otherCustomerId = User::create(['role' => 'customer', 'username' => $digits('other-user-' . $suffix), 'full_name' => 'مشتری دیگر آزمون', 'national_id' => $digits('other-national-' . $suffix), 'mobile' => $otherMobile, 'email' => 'qa-doc-other-' . $suffix . '@example.test', 'password' => substr($otherMobile, -4), 'status' => 'active']);

$insertFile = static function (int $userId, string $suffix, string $category, string $relationType): string {
    $uuid = bin2hex(random_bytes(16));
    $path = 'storage/secure_uploads/qa-documents/' . $uuid . '.pdf';
    Model::execute(
        'INSERT INTO files (file_uuid, original_name, stored_name, display_name, extension, mime_type, size_bytes, storage_disk, storage_path, category, status, visibility, uploader_user_id, uploader_role, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
        [$uuid, 'contract-proof-' . $suffix . '.pdf', basename($path), 'آزمون مدارک ' . $suffix, 'pdf', 'application/pdf', 120, 'private', $path, $category, 'active', 'private', $userId, 'admin']
    );
    FileRecord::addRelation((int) Model::lastInsertId(), 'user', $userId, $relationType, $userId);
    return $uuid;
};

$visibleUuid = $insertFile($customerId, $suffix, 'contract_document', 'attachment');
$identityUuid = $insertFile($customerId, $suffix . '-identity', 'identity_document', 'identity_document');
$avatarUuid = $insertFile($customerId, $suffix . '-avatar', 'avatar', 'avatar');
$otherUuid = $insertFile($otherCustomerId, $suffix . '-other', 'contract_document', 'attachment');

$visibleFiles = FileRecord::forCustomer($customerId);
$visibleUuids = array_column($visibleFiles, 'file_uuid');
if (!in_array($visibleUuid, $visibleUuids, true)) throw new RuntimeException('Customer-related document is missing from the scoped list.');
if (in_array($identityUuid, $visibleUuids, true) || in_array($avatarUuid, $visibleUuids, true) || in_array($otherUuid, $visibleUuids, true)) {
    throw new RuntimeException('Customer document list leaked an identity duplicate, avatar, or another customer file.');
}
foreach ($visibleFiles as $file) {
    if (array_key_exists('storage_path', $file)) throw new RuntimeException('Customer document read model exposed a storage path.');
}

echo "INTEGRATION_CUSTOMER_DOCUMENT_QUERY_V204_OK\n";
