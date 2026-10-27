<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$schema = $read('database/proma-pay-install.sql');
$migration = $read('database/migrations/2026_10_26_v704_secure_customer_archiving.sql');
$users = $read('models/User.php');
$contracts = $read('models/Contract.php');
$preparedImport = $read('helpers/PreparedExcelImportService.php');
$customersController = $read('controllers/CustomersController.php');
$router = $read('core/Router.php');
$auth = $read('core/Auth.php');
$customersIndex = $read('views/customers/index.php');
$customersShow = $read('views/customers/show.php');
$reset = $read('helpers/SystemResetService.php');

$protectedConstraints = [
    'fk_contract_customer', 'fk_legal_customer', 'fk_payment_receipts_customer',
    'fk_identity_documents_user', 'fk_call_customer', 'fk_guarantor_user',
];
foreach ($protectedConstraints as $constraint) {
    $assert((bool) preg_match('/CONSTRAINT `' . preg_quote($constraint, '/') . '`[^\n]*ON DELETE RESTRICT/', $schema), 'Fresh install does not protect ' . $constraint . '.');
    $assert(strpos($migration, "CONSTRAINT " . $constraint) !== false && strpos($migration, "'" . $constraint . "'") !== false, 'Upgrade migration does not replace ' . $constraint . ' conditionally.');
}
foreach (['archived_at', 'archived_by', 'archive_reason', 'restored_at', 'idx_users_role_archived'] as $field) {
    $assert(strpos($schema, '`' . $field . '`') !== false, 'Fresh schema is missing ' . $field . '.');
    $assert(strpos($migration, $field) !== false, 'Upgrade migration is missing ' . $field . '.');
}
$assert(strpos($migration, 'DELETE FROM') === false && strpos($migration, 'SET FOREIGN_KEY_CHECKS') === false, 'Archive migration must not mutate customer rows or bypass foreign keys.');

$assert(strpos($users, 'public static function archiveCustomer(') !== false && strpos($users, 'public static function restoreCustomer(') !== false, 'Customer lifecycle model methods are missing.');
$assert(strpos($users, 'SELECT role, status FROM users WHERE id = ? FOR UPDATE') !== false, 'Lifecycle authorization must lock the active administrator row.');
$assert(strpos($users, "archived_at = NOW()") !== false && strpos($users, "archived_at = NULL, restored_at = NOW()") !== false, 'Archive and restore must preserve distinct lifecycle fields.');
$assert(strpos($users, 'physical_delete_forbidden') !== false && strpos($users, 'DELETE FROM users WHERE id = ?') !== false, 'Direct model deletion must explicitly reject customers.');
$assert(strpos($users, 'customer_merged') !== false && strpos($users, 'FOR UPDATE') !== false, 'Customer merge must preserve audit and row-lock guarantees.');
$assert(strpos($users, "role != 'customer' OR archived_at IS NULL") !== false, 'Default user search must omit archived customers.');
$assert(strpos($auth, "status = 'active' AND archived_at IS NULL") !== false && strpos($auth, 'archived_at') !== false && strpos($auth, 'self::logout()') !== false, 'Archived customers must not authenticate or retain a session.');
$assert(strpos($contracts, "status = 'active' AND archived_at IS NULL") !== false
    && strpos($contracts, 'FOR UPDATE') !== false
    && strpos($contracts, "AND archived_at IS NULL LIMIT 1") !== false,
    'Contract creation and guarantor assignment must lock and reject archived customers.');
$assert(strpos($preparedImport, "status = 'active' AND archived_at IS NULL") !== false && strpos($preparedImport, 'LIMIT 1 FOR UPDATE') !== false, 'Excel contract import must lock and reject inactive or archived customers.');
$assert(strpos($customersController, 'public function archive(') !== false && strpos($customersController, 'public function restore(') !== false, 'Archive/restore controller actions are missing.');
$assert(strpos($customersController, '$this->onlyPost();') !== false, 'Lifecycle controller actions must enforce POST and CSRF.');
$assert(strpos($customersController, 'return $this->archive($id);') !== false, 'Legacy delete action must safely route to archive.');
$assert(strpos($router, "'customers/retire' => 'customers/delete'") !== false, 'Legacy retire route alias must remain mapped to the safe action.');
$assert(strpos($customersIndex, 'archive_reason') !== false && strpos($customersIndex, 'customers/restore/') !== false && strpos($customersIndex, "'archived'") !== false, 'Customer list must expose reason, archive filter, and restore.');
$assert(strpos($customersShow, 'customerLifecycleHistory') !== false && strpos($customersShow, 'پرونده بایگانی‌شده') !== false, 'Customer 360 must display archive state and audit history.');
$assert(strpos($reset, 'assertNoProtectedHistory();') !== false && strpos($reset, 'SET FOREIGN_KEY_CHECKS=0') !== false && strpos($reset, 'KEY_COLUMN_USAGE') !== false && strpos($reset, 'history.customer_id') !== false, 'System reset must check protected history and customer references before its FK bypass.');

echo "STATIC_CUSTOMER_ARCHIVE_V704_OK\n";
