<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
$safeDsn = preg_match('/(?:^|;)dbname=proma_qa_customer_archive_[a-f0-9]{10}(?:;|$)/i', $dsn) === 1;
if (!$safeDsn || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "Refusing mutation: use an isolated proma_qa_customer_archive_<10-hex> database and PROMA_TEST_ALLOW_DESTRUCTIVE=1.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.74';
require dirname(__DIR__) . '/bootstrap.php';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), $pdoOptions);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);
$runner = new ReflectionMethod(ScriptUpdateService::class, 'runUpdateMigration');
$schemaSetup = new ReflectionMethod(ScriptUpdateService::class, 'ensureMigrationSchema');
$schemaSetup->invoke(null);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$pass = [];
$record = static function (string $id) use (&$pass): void { $pass[] = $id; };
$count = static function (string $table) use ($pdo): int {
    if (!preg_match('/\A[a-z_]+\z/', $table)) {
        throw new InvalidArgumentException('Invalid QA table.');
    }
    return (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
};
$suffix = substr(hash('sha256', (string) hrtime(true) . random_bytes(8)), 0, 10);
$digits = static function (string $seed): string {
    return str_pad((string) (hexdec(substr(hash('sha256', $seed), 0, 8)) % 10000000000), 10, '0', STR_PAD_LEFT);
};
$makeUser = static function (string $role, string $label) use ($suffix, $digits): int {
    $mobile = '09' . substr($digits($label . '-mobile-' . $suffix), 0, 9);
    return User::create([
        'role' => $role,
        'username' => 'qa-archive-' . $suffix . '-' . $label,
        'full_name' => 'آزمون بایگانی ' . $label,
        'national_id' => $digits($label . '-nid-' . $suffix),
        'mobile' => $mobile,
        'email' => 'qa-archive-' . $suffix . '-' . $label . '@example.test',
        'password' => $role === 'customer' ? substr($mobile, -4) : 'QaArchive#704',
        'status' => 'active',
    ]);
};
$assertUser = static function (int $userId, string $message) use ($assert): array {
    $user = Model::fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
    $assert((bool) $user, $message);
    return $user;
};
$archive = static function (int $customerId, int $adminId, string $reason = 'آزمون حفظ سوابق') use ($assert): void {
    $assert(User::archiveCustomer($customerId, $adminId, $reason) === true, 'Archive did not report success.');
};
$expectRejected = static function (callable $callback, string $message) use ($assert): Throwable {
    try {
        $callback();
    } catch (Throwable $e) {
        $assert($e instanceof InvalidArgumentException || $e instanceof LogicException || $e instanceof PDOException, $message . ': unexpected ' . get_class($e));
        return $e;
    }
    throw new RuntimeException($message . ': operation was accepted.');
};

// Seed linked historical rows before the upgrade migration is applied.
$legacySuffix = substr(hash('sha256', random_bytes(16)), 0, 10);
$legacyUserInsert = $pdo->prepare('INSERT INTO users (role, username, full_name, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
$legacyUserInsert->execute(['customer', 'qa-archive-migration-' . $legacySuffix, 'مشتری سابقه مهاجرت', password_hash('qa-migration-fixture', PASSWORD_DEFAULT), 'active']);
$legacyCustomerId = (int) $pdo->lastInsertId();
$legacyContractInsert = $pdo->prepare(
    "INSERT INTO contracts (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, interest_type, months, start_date, first_due_date, status, created_at)
     VALUES (?, ?, ?, 1, 2000, 0, 'simple', 2, '2025-01-01', '2025-02-01', 'active', NOW())"
);
$legacyContractInsert->execute([$legacyCustomerId, 'QA-ARCHIVE-MIGRATION-' . $legacySuffix, 'QAMIG-' . $legacySuffix]);
$legacyContractId = (int) $pdo->lastInsertId();
$legacyInstallmentInsert = $pdo->prepare(
    "INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, created_at)
     VALUES (?, 1, '2025-02-01', 2000, 1000, 1000, 'partial', NOW())"
);
$legacyInstallmentInsert->execute([$legacyContractId]);
$legacyInstallmentId = (int) $pdo->lastInsertId();
$legacyPaymentInsert = $pdo->prepare(
    "INSERT INTO payments (installment_id, contract_id, user_id, amount, method, status, payment_type, created_at)
     VALUES (?, ?, ?, '1000.00', 'manual', 'paid', 'installment', NOW())"
);
$legacyPaymentInsert->execute([$legacyInstallmentId, $legacyContractId, $legacyCustomerId]);
$legacyPaymentId = (int) $pdo->lastInsertId();
$legacyCaseInsert = $pdo->prepare(
    "INSERT INTO legal_cases (customer_id, contract_id, status, stage, notes, created_at, updated_at)
     VALUES (?, ?, 'open', 'notice', 'migration-preservation-fixture', NOW(), NOW())"
);
$legacyCaseInsert->execute([$legacyCustomerId, $legacyContractId]);
$legacyCaseId = (int) $pdo->lastInsertId();
$legacySnapshot = Model::fetch(
    'SELECT u.id AS user_id, c.id AS contract_id, i.id AS installment_id, i.base_amount, i.paid_amount, i.remaining_amount,
            p.id AS payment_id, p.amount AS payment_amount, lc.id AS legal_case_id, lc.customer_id AS legal_customer_id
     FROM users u
     JOIN contracts c ON c.customer_id = u.id
     JOIN installments i ON i.contract_id = c.id
     JOIN payments p ON p.installment_id = i.id AND p.contract_id = c.id
     JOIN legal_cases lc ON lc.contract_id = c.id AND lc.customer_id = u.id
     WHERE u.id = ? AND c.id = ? AND i.id = ? AND p.id = ? AND lc.id = ?',
    [$legacyCustomerId, $legacyContractId, $legacyInstallmentId, $legacyPaymentId, $legacyCaseId]
);
$assert((bool) $legacySnapshot, 'Unable to create linked legacy migration fixtures.');

$beforeMigration = [];
foreach (['users', 'contracts', 'installments', 'payments', 'legal_cases'] as $table) {
    $beforeMigration[$table] = $count($table);
}
$migrationName = 'database/migrations/2026_10_26_v704_secure_customer_archiving.sql';
$migrationSql = (string) file_get_contents(dirname(__DIR__) . '/' . $migrationName);
$runner->invoke(null, $migrationName, $migrationSql);
foreach ($beforeMigration as $table => $rows) {
    $assert($count($table) === $rows, 'Migration changed historical row count in ' . $table . '.');
}
$legacyAfterMigration = Model::fetch(
    'SELECT u.id AS user_id, c.id AS contract_id, i.id AS installment_id, i.base_amount, i.paid_amount, i.remaining_amount,
            p.id AS payment_id, p.amount AS payment_amount, lc.id AS legal_case_id, lc.customer_id AS legal_customer_id
     FROM users u
     JOIN contracts c ON c.customer_id = u.id
     JOIN installments i ON i.contract_id = c.id
     JOIN payments p ON p.installment_id = i.id AND p.contract_id = c.id
     JOIN legal_cases lc ON lc.contract_id = c.id AND lc.customer_id = u.id
     WHERE u.id = ? AND c.id = ? AND i.id = ? AND p.id = ? AND lc.id = ?',
    [$legacyCustomerId, $legacyContractId, $legacyInstallmentId, $legacyPaymentId, $legacyCaseId]
);
$assert($legacyAfterMigration === $legacySnapshot, 'Migration changed a linked financial/legal history identifier or amount.');
$columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['archived_at', 'archived_by', 'archive_reason', 'restored_at'] as $column) {
    $assert(in_array($column, $columns, true), 'Migration missed users.' . $column . '.');
}
$migrationStatus = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
$assert(($migrationStatus['status'] ?? '') === 'success', 'Updater did not record migration success.');
$rules = $pdo->query("SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME IN ('fk_contract_customer','fk_legal_customer','fk_payment_receipts_customer','fk_identity_documents_user','fk_call_customer','fk_guarantor_user')")->fetchAll();
$ruleMap = [];
foreach ($rules as $rule) $ruleMap[$rule['CONSTRAINT_NAME']] = $rule['DELETE_RULE'];
foreach (['fk_contract_customer', 'fk_legal_customer', 'fk_payment_receipts_customer', 'fk_identity_documents_user', 'fk_call_customer', 'fk_guarantor_user'] as $constraint) {
    $assert(($ruleMap[$constraint] ?? '') === 'RESTRICT', $constraint . ' is not RESTRICT after migration.');
}

// Simulate a prior attempt that committed the first FK DDL and then stopped.
// Retrying must skip the already-restricted FK and safely replace the rest.
foreach ([
    ['legal_cases', 'fk_legal_customer', 'customer_id'],
    ['payment_receipts', 'fk_payment_receipts_customer', 'customer_id'],
    ['identity_documents', 'fk_identity_documents_user', 'user_id'],
    ['operator_calls', 'fk_call_customer', 'customer_id'],
    ['contract_guarantors', 'fk_guarantor_user', 'guarantor_id'],
] as [$table, $constraint, $column]) {
    $pdo->exec('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $constraint . '`');
    $pdo->exec('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $constraint . '` FOREIGN KEY (`' . $column . '`) REFERENCES users (id) ON DELETE CASCADE');
}
Model::execute("UPDATE migrations SET status = 'failed' WHERE migration_name = ?", [$migrationName]);
$partialBefore = [];
foreach (array_keys($beforeMigration) as $table) $partialBefore[$table] = $count($table);
$runner->invoke(null, $migrationName, $migrationSql);
foreach ($partialBefore as $table => $rows) $assert($count($table) === $rows, 'Retry changed historical row count in ' . $table . '.');
$retryRules = $pdo->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME IN ('fk_contract_customer','fk_legal_customer','fk_payment_receipts_customer','fk_identity_documents_user','fk_call_customer','fk_guarantor_user')")->fetchAll();
foreach ($retryRules as $rule) $assert(($rule['DELETE_RULE'] ?? '') === 'RESTRICT', 'Migration retry left ' . ($rule['CONSTRAINT_NAME'] ?? 'an FK') . ' unprotected.');
$legacyAfterRetry = Model::fetch(
    'SELECT u.id AS user_id, c.id AS contract_id, i.id AS installment_id, i.base_amount, i.paid_amount, i.remaining_amount,
            p.id AS payment_id, p.amount AS payment_amount, lc.id AS legal_case_id, lc.customer_id AS legal_customer_id
     FROM users u
     JOIN contracts c ON c.customer_id = u.id
     JOIN installments i ON i.contract_id = c.id
     JOIN payments p ON p.installment_id = i.id AND p.contract_id = c.id
     JOIN legal_cases lc ON lc.contract_id = c.id AND lc.customer_id = u.id
     WHERE u.id = ? AND c.id = ? AND i.id = ? AND p.id = ? AND lc.id = ?',
    [$legacyCustomerId, $legacyContractId, $legacyInstallmentId, $legacyPaymentId, $legacyCaseId]
);
$assert($legacyAfterRetry === $legacySnapshot, 'Migration retry changed a linked financial/legal history identifier or amount.');
$migrationStatus = Model::fetch('SELECT status FROM migrations WHERE migration_name = ? LIMIT 1', [$migrationName]);
$assert(($migrationStatus['status'] ?? '') === 'success', 'Updater did not record retry success.');
$record('T12 migration validates historical rows and preserves counts');
$record('T12 migration retry safely resumes after partially committed DDL');

$adminId = $makeUser('admin', 'admin');
$_SESSION['user_id'] = $adminId;
$_SESSION['role'] = 'admin';
Settings::saveMany(['monthly_penalty_rate' => '0', 'monthly_reward_rate' => '0']);

// T01: a customer without contracts is logically archived without changing status or id.
$emptyCustomerId = $makeUser('customer', 'empty');
$emptyBefore = $assertUser($emptyCustomerId, 'Empty customer was not created.');
$archive($emptyCustomerId, $adminId, 'مشتری بدون سابقه');
$emptyAfter = $assertUser($emptyCustomerId, 'Archive removed the empty customer row.');
$assert(($emptyAfter['status'] ?? '') === ($emptyBefore['status'] ?? '') && !empty($emptyAfter['archived_at']), 'Archive changed status or omitted archived_at.');
$record('T01 empty customer archive preserves row and status');

// Shared fixture for active, settled, paid, overdue, and legal history.
$customerId = $makeUser('customer', 'history');
$activeContractId = Contract::createWithInstallments([
    'customer_id' => $customerId, 'principal_amount' => 4000000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2,
    'start_date' => '2025-01-01', 'first_due_date' => '2025-02-01', 'created_by' => $adminId,
]);
$settledContractId = Contract::createWithInstallments([
    'customer_id' => $customerId, 'principal_amount' => 2000000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 2,
    'start_date' => '2025-01-01', 'first_due_date' => '2025-02-01', 'created_by' => $adminId,
]);
Model::execute("UPDATE contracts SET status = 'completed' WHERE id = ?", [$settledContractId]);
$firstInstallment = Model::fetch('SELECT id FROM installments WHERE contract_id = ? ORDER BY installment_number LIMIT 1', [$activeContractId]);
$assert(!empty($firstInstallment['id']), 'Active contract fixture has no installment.');
$pdo->prepare(
    'INSERT INTO payments (installment_id, contract_id, user_id, amount, method, status, description, payment_date, payment_type, paid_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
)->execute([(int) $firstInstallment['id'], $activeContractId, $customerId, '125000.00', 'manual', 'paid', 'archive-preservation-fixture', '2025-02-01', 'installment', '2025-02-01 12:00:00']);
$caseStatement = $pdo->prepare(
    "INSERT INTO legal_cases (customer_id, contract_id, status, stage, notes, created_at, updated_at)
     VALUES (?, ?, 'open', 'notice', 'archive-preservation-fixture', NOW(), NOW())"
);
$caseStatement->execute([$customerId, $activeContractId]);
$legalCaseId = (int) $pdo->lastInsertId();
$beforeContracts = Model::fetchAll('SELECT id, customer_id, status FROM contracts WHERE customer_id = ? ORDER BY id', [$customerId]);
$beforePayments = Model::fetchAll('SELECT id, amount FROM payments WHERE contract_id IN (?, ?) ORDER BY id', [$activeContractId, $settledContractId]);
$beforeLegal = Model::fetch('SELECT id, customer_id, contract_id, status FROM legal_cases WHERE id = ?', [$legalCaseId]);
$beforeMoney = (string) Model::fetch('SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id IN (?, ?)', [$activeContractId, $settledContractId])['total'];
$openBefore = (int) Model::fetch("SELECT COUNT(*) AS total FROM installments WHERE contract_id = ? AND status <> 'paid' AND due_date < CURRENT_DATE", [$activeContractId])['total'];
$assert($openBefore > 0, 'Fixture must include overdue installments.');

// T02-T05, T14, T17: archiving preserves all contract, payment, and legal identifiers and amounts.
$archive($customerId, $adminId, 'آزمون سوابق مالی و حقوقی');
$afterContracts = Model::fetchAll('SELECT id, customer_id, status FROM contracts WHERE customer_id = ? ORDER BY id', [$customerId]);
$afterPayments = Model::fetchAll('SELECT id, amount FROM payments WHERE contract_id IN (?, ?) ORDER BY id', [$activeContractId, $settledContractId]);
$afterLegal = Model::fetch('SELECT id, customer_id, contract_id, status FROM legal_cases WHERE id = ?', [$legalCaseId]);
$afterMoney = (string) Model::fetch('SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id IN (?, ?)', [$activeContractId, $settledContractId])['total'];
$openAfter = (int) Model::fetch("SELECT COUNT(*) AS total FROM installments WHERE contract_id = ? AND status <> 'paid' AND due_date < CURRENT_DATE", [$activeContractId])['total'];
$collectionTasksCreated = CollectionCommandCenter::runStageAutomation($adminId);
$collectionTask = Model::fetch(
    "SELECT id, contract_id, customer_id, stage, status FROM collection_tasks
     WHERE contract_id = ? AND customer_id = ? AND status = 'open' ORDER BY id LIMIT 1",
    [$activeContractId, $customerId]
);
$assert($afterContracts === $beforeContracts && count($afterContracts) === 2, 'Active or settled contracts changed during archive.');
$assert($afterPayments === $beforePayments && count($afterPayments) === 1 && $afterMoney === $beforeMoney, 'Payments or payment amounts changed during archive.');
$assert($afterLegal === $beforeLegal, 'Legal case relationship changed during archive.');
$assert($openAfter === $openBefore, 'Overdue installments changed during archive.');
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM installments WHERE contract_id IN (?, ?)', [$activeContractId, $settledContractId])['total'] > 0, 'Installments disappeared after archive.');
$assert($collectionTasksCreated > 0 && $collectionTask && (int) $collectionTask['contract_id'] === $activeContractId
    && (int) $collectionTask['customer_id'] === $customerId && ($collectionTask['status'] ?? '') === 'open',
    'Overdue collection automation did not create a live task for the archived customer.');
$record('T02 active contract preserved');
$record('T03 settled contract preserved');
$record('T04 payment identifiers and amounts preserved');
$record('T05 legal case and relationship preserved');
$record('T14 archived financial records remain queryable');
$record('T17 overdue balance remains visible and collection automation creates an open task');

// T06/T15: the model forbids customer hard-delete and MySQL RESTRICT prevents direct deletion.
$expectRejected(static function () use ($customerId): void { User::deleteUser($customerId); }, 'Customer model hard-delete must fail');
try {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$customerId]);
    throw new RuntimeException('Database allowed direct hard-delete for a customer with history.');
} catch (PDOException $e) {
    $assert((string) $e->getCode() === '23000', 'Direct DELETE failed for a reason other than its FK guard.');
}
$assertUser($customerId, 'FK rejection removed customer row.');
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$customerId])['total'] === 2, 'FK rejection damaged contracts.');
$resetGuard = new ReflectionMethod(SystemResetService::class, 'assertNoProtectedHistory');
$resetGuard->setAccessible(true);
try {
    $resetGuard->invoke(null);
    throw new RuntimeException('System reset guard accepted protected customer history.');
} catch (ReflectionException $e) {
    throw $e;
} catch (RuntimeException $e) {
    $assert(strpos($e->getMessage(), 'بازنشانی متوقف شد') !== false, 'System reset failed for an unrelated reason.');
}
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$customerId])['total'] === 2, 'System reset guard changed preserved contracts.');
$record('T06 direct model deletion rejected');
$record('T15 direct database deletion blocked and full-reset guard preserves history');

// T13: duplicate archive is a rejected, audited operation; T08: restoration retains status and history.
$archiveSuccessBefore = (int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_action = 'customer_archived' AND related_id = ?", [$customerId])['total'];
$expectRejected(static function () use ($customerId, $adminId): void { User::archiveCustomer($customerId, $adminId, 'duplicate request'); }, 'Repeated archive must fail');
$archiveSuccessAfter = (int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_action = 'customer_archived' AND related_id = ?", [$customerId])['total'];
$assert($archiveSuccessAfter === $archiveSuccessBefore, 'Duplicate archive created another success event.');
$assert((int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_action = 'customer_archive_rejected' AND related_id = ?", [$customerId])['total'] > 0, 'Rejected duplicate archive was not audited.');
$record('T13 repeated archive rejected and recorded');
$assert(User::restoreCustomer($customerId, $adminId) === true, 'Restore did not report success.');
$restored = $assertUser($customerId, 'Restore removed customer.');
$assert(empty($restored['archived_at']) && ($restored['status'] ?? '') === 'active' && !empty($restored['restored_at']), 'Restore failed to clear archive state or preserve status.');
$assert((bool) Model::fetch('SELECT id FROM contracts WHERE id = ? AND customer_id = ?', [$activeContractId, $customerId]), 'Restore changed historical contract identity.');
$record('T08 restore preserves status and relationship identifiers');

// T09: authorization denial at the model boundary is audited.
$operatorId = $makeUser('operator', 'operator');
$deniedCustomerId = $makeUser('customer', 'denied');
$expectRejected(static function () use ($deniedCustomerId, $operatorId): void { User::archiveCustomer($deniedCustomerId, $operatorId, 'unauthorized'); }, 'Non-admin archive must fail');
$deniedAudit = Model::fetch("SELECT id FROM audit_logs WHERE event_action = 'customer_archive_rejected' AND related_id = ? AND new_values LIKE '%unauthorized_actor%' LIMIT 1", [$deniedCustomerId]);
$assert((bool) $deniedAudit, 'Unauthorized archive attempt was not audited.');
$assert(empty($assertUser($deniedCustomerId, 'Unauthorized archive removed customer.')['archived_at']), 'Unauthorized actor changed customer state.');
$record('T09 non-admin rejected and audited');

// T10: race the real archive and contract services from two PHP workers.
$raceCustomerId = $makeUser('customer', 'race');
$raceScript = tempnam(sys_get_temp_dir(), 'proma-v704-race-');
$startFile = tempnam(sys_get_temp_dir(), 'proma-v704-start-');
$raceChildSource = <<<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '198.51.100.75';
$root = $argv[5];
require $root . '/bootstrap.php';
$childPdo = new PDO((string) getenv('PROMA_TEST_DB_DSN'), getenv('PROMA_TEST_DB_USER') ?: 'root', (string) getenv('PROMA_TEST_DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
(new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $childPdo);
while (trim((string) @file_get_contents($argv[4])) !== 'go') usleep(10000);
try {
    if ($argv[1] === 'archive') {
        User::archiveCustomer((int) $argv[2], (int) $argv[3], 'concurrent archive fixture');
        fwrite(STDOUT, "ARCHIVED\n");
    } else {
        try {
            $contractId = Contract::createWithInstallments([
                'customer_id' => (int) $argv[2], 'principal_amount' => 1000000, 'down_payment_amount' => 0,
                'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1,
                'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01',
            ]);
            fwrite(STDOUT, 'CONTRACT_CREATED:' . (int) $contractId . "\n");
        } catch (InvalidArgumentException $e) {
            fwrite(STDOUT, "CONTRACT_REJECTED\n");
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
PHP;
if ($raceScript === false || $startFile === false || file_put_contents($raceScript, $raceChildSource) === false) {
    throw new RuntimeException('Unable to create temporary concurrency workers.');
}
$pipesArchive = [];
$pipesContract = [];
$archiveProcess = proc_open([PHP_BINARY, $raceScript, 'archive', (string) $raceCustomerId, (string) $adminId, $startFile, dirname(__DIR__)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesArchive);
$contractProcess = proc_open([PHP_BINARY, $raceScript, 'contract', (string) $raceCustomerId, (string) $adminId, $startFile, dirname(__DIR__)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesContract);
if (!is_resource($archiveProcess) || !is_resource($contractProcess)) {
    throw new RuntimeException('Unable to start both lifecycle concurrency workers.');
}
fclose($pipesArchive[0]);
fclose($pipesContract[0]);
file_put_contents($startFile, 'go');
$archiveOutput = stream_get_contents($pipesArchive[1]);
$archiveError = stream_get_contents($pipesArchive[2]);
$contractOutput = stream_get_contents($pipesContract[1]);
$contractError = stream_get_contents($pipesContract[2]);
fclose($pipesArchive[1]); fclose($pipesArchive[2]);
fclose($pipesContract[1]); fclose($pipesContract[2]);
$archiveExit = proc_close($archiveProcess);
$contractExit = proc_close($contractProcess);
@unlink($raceScript);
@unlink($startFile);
$assert($archiveExit === 0 && trim($archiveOutput) === 'ARCHIVED', 'Concurrent archive worker failed: ' . trim($archiveError . ' ' . $archiveOutput));
$assert($contractExit === 0 && (trim($contractOutput) === 'CONTRACT_REJECTED' || preg_match('/\ACONTRACT_CREATED:[1-9][0-9]*\z/', trim($contractOutput)) === 1), 'Concurrent contract worker failed: ' . trim($contractError . ' ' . $contractOutput));
$raceUser = $assertUser($raceCustomerId, 'Concurrent archive removed the customer.');
$assert(!empty($raceUser['archived_at']), 'Concurrent archive did not finish in the archived state.');
$raceContractCount = (int) Model::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$raceCustomerId])['total'];
$assert(($contractOutput === "CONTRACT_REJECTED\n" && $raceContractCount === 0)
    || (preg_match('/\ACONTRACT_CREATED:([1-9][0-9]*)\n?\z/', $contractOutput, $contractMatch) === 1 && $raceContractCount === 1
        && (int) Model::fetch('SELECT id FROM contracts WHERE customer_id = ?', [$raceCustomerId])['id'] === (int) $contractMatch[1]),
    'Concurrent lifecycle actions produced an inconsistent contract/archive state.');
$contractsBeforeArchivedCreate = $raceContractCount;
$expectRejected(static function () use ($raceCustomerId): void {
    Contract::createWithInstallments([
        'customer_id' => $raceCustomerId, 'principal_amount' => 1000000, 'down_payment_amount' => 0,
        'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1,
        'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01',
    ]);
}, 'Contract creation for an archived customer must fail');
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$raceCustomerId])['total'] === $contractsBeforeArchivedCreate, 'Archived contract attempt inserted a contract.');
$importInsert = new ReflectionMethod(PreparedExcelImportService::class, 'insertContract');
$importInsert->setAccessible(true);
Model::begin();
try {
    $expectRejected(static function () use ($importInsert, $raceCustomerId): void {
        $importInsert->invoke(null, [
            'customer_id' => $raceCustomerId, 'contract_number' => 'QA-ARCHIVE-IMPORT-' . $raceCustomerId,
            'principal_amount' => 1000000, 'months' => 1, 'start_date' => '2026-10-01',
            'first_due_date' => '2026-11-01', 'status' => 'active', 'notes' => 'archive guard',
        ]);
    }, 'Prepared Excel import must reject an archived customer');
} finally {
    if (Model::db()->inTransaction()) Model::rollBack();
}
$assert((int) Model::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$raceCustomerId])['total'] === $contractsBeforeArchivedCreate, 'Excel import created a contract for an archived customer.');
$record('T10 concurrent archive/contract services serialize to a consistent result');
$record('T16 contract service and Excel import reject an archived customer');

// T11: an AuditLog insert failure rolls the archive state back with the transaction.
$auditFailCustomerId = $makeUser('customer', 'auditfail');
$pdo->exec("CREATE TRIGGER qa_v704_fail_customer_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'qa audit failure'");
try {
    try {
        User::archiveCustomer($auditFailCustomerId, $adminId, 'audit failure rollback');
        throw new RuntimeException('Archive succeeded although AuditLog insertion failed.');
    } catch (PDOException $e) {
        $assert(strpos($e->getMessage(), 'qa audit failure') !== false, 'Audit failure test saw an unrelated database error.');
    }
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS qa_v704_fail_customer_audit');
}
$assert(empty($assertUser($auditFailCustomerId, 'Audit failure removed customer.')['archived_at']), 'Archive state committed without its audit event.');
$record('T11 failed AuditLog insert rolls back archive state');

// T18: merge retains the contract key, reparents it, and records the merge snapshot and audit.
$mergeKeepId = $makeUser('customer', 'mergekeep');
$mergeFromId = $makeUser('customer', 'mergefrom');
$mergeContractId = Contract::createWithInstallments([
    'customer_id' => $mergeFromId, 'principal_amount' => 1500000, 'down_payment_amount' => 0,
    'monthly_interest_rate' => 0, 'interest_type' => 'simple', 'months' => 1,
    'start_date' => '2026-10-01', 'first_due_date' => '2026-11-01', 'created_by' => $adminId,
]);
$mergeLogBefore = $count('customer_merge_logs');
$assert(User::mergeCustomers($mergeKeepId, $mergeFromId, $adminId) === true, 'Customer merge failed.');
$mergeContract = Model::fetch('SELECT id, customer_id FROM contracts WHERE id = ?', [$mergeContractId]);
$mergedUser = $assertUser($mergeFromId, 'Merged customer row was removed.');
$assert((int) ($mergeContract['customer_id'] ?? 0) === $mergeKeepId && ($mergedUser['status'] ?? '') === 'inactive', 'Merge lost or misattributed contract history.');
$assert($count('customer_merge_logs') === $mergeLogBefore + 1, 'Merge snapshot log was not preserved.');
$assert((int) Model::fetch("SELECT COUNT(*) AS total FROM audit_logs WHERE event_action = 'customer_merged' AND related_id = ?", [$mergeFromId])['total'] === 1, 'Merge audit event missing.');
$record('T18 merge preserves traceable history and records snapshot/audit');

echo 'INTEGRATION_CUSTOMER_ARCHIVE_V704_OK ' . implode(', ', $pass) . "\n";
