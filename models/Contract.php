<?php

class Contract extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        // Contract schema is provisioned by installation and migrations, never by a page request.
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        self::syncCompletionStatuses();
        $params = [];
        $where = self::listWhere($filters, $params);
        $sql = "SELECT c.*, u.full_name AS customer_name, u.mobile, u.national_id, u.secondary_phone, u.avatar_key,
                op.full_name AS operator_name,
                (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.status != 'closed') AS legal_case_count
                FROM contracts c
                JOIN users u ON u.id = c.customer_id
                LEFT JOIN users op ON op.id = c.assigned_operator_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(100, (int) $filters['limit']));
        }
        return self::fetchAll($sql, $params);
    }

    public static function paginated(array $filters = [])
    {
        self::ensureSchema();
        self::syncCompletionStatuses();
        $params = [];
        $where = self::listWhere($filters, $params);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = self::fetch(
            "SELECT COUNT(*) AS total
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             {$whereSql}",
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(6, min(60, (int) ($filters['per_page'] ?? 24)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT c.*, u.full_name AS customer_name, u.mobile, u.national_id, u.secondary_phone, u.avatar_key,
             op.full_name AS operator_name,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id AND lc.status != 'closed') AS legal_case_count
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             LEFT JOIN users op ON op.id = c.assigned_operator_id
             {$whereSql}
             ORDER BY c.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    protected static function listWhere(array $filters, array &$params)
    {
        $where = [];
        if (!empty($filters['customer_id'])) {
            $where[] = 'c.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['guarantor_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM contract_guarantors cg WHERE cg.contract_id = c.id AND cg.guarantor_id = ?)';
            $params[] = (int) $filters['guarantor_id'];
        }
        if (!empty($filters['operator_id'])) {
            $where[] = 'c.assigned_operator_id = ?';
            $params[] = (int) $filters['operator_id'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        return $where;
    }

    public static function find($id)
    {
        self::ensureSchema();
        self::syncCompletionStatuses((int) $id);
        return self::fetch(
            "SELECT c.*, u.full_name AS customer_name, u.father_name AS customer_father_name,
             u.issued_from AS customer_issued_from, u.mobile, u.national_id, u.secondary_phone,
             u.address AS customer_address
             FROM contracts c JOIN users u ON u.id = c.customer_id WHERE c.id = ?",
            [(int) $id]
        );
    }

    public static function guarantors($contractId)
    {
        return self::fetchAll(
            'SELECT u.* FROM contract_guarantors cg JOIN users u ON u.id = cg.guarantor_id WHERE cg.contract_id = ? ORDER BY u.full_name',
            [(int) $contractId]
        );
    }

    public static function guarantorPeople($contractId)
    {
        if (class_exists('ContractDocument')) {
            ContractDocument::ensureSchema();
        }
        return self::fetchAll(
            'SELECT full_name, mobile, relationship FROM contract_guarantor_people WHERE contract_id = ? AND mobile IS NOT NULL AND mobile != "" ORDER BY full_name',
            [(int) $contractId]
        );
    }

    public static function guarantorContacts($contractId)
    {
        $contacts = [];
        foreach (self::guarantors((int) $contractId) as $user) {
            if (!empty($user['mobile'])) {
                $contacts[] = ['full_name' => $user['full_name'], 'mobile' => $user['mobile'], 'relationship' => 'ضامن'];
            }
            if (!empty($user['secondary_phone'])) {
                $contacts[] = ['full_name' => $user['full_name'], 'mobile' => $user['secondary_phone'], 'relationship' => 'شماره دوم ضامن'];
            }
        }
        foreach (self::guarantorPeople((int) $contractId) as $person) {
            $contacts[] = $person;
        }
        return $contacts;
    }

    public static function search($query, $limit = 12, array $filters = [])
    {
        self::ensureSchema();
        $query = trim(to_english_digits((string) $query));
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }
        $needle = '%' . $query . '%';
        $params = ['cancelled', $needle, $needle, $needle, $needle, $needle];
        $where = [
            'c.status != ?',
            '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)',
        ];
        if (!empty($filters['eligible_legal'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM installments i
                WHERE i.contract_id = c.id
                AND i.status NOT IN ('paid', 'cancelled')
                AND i.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
        }
        if (!empty($filters['without_open_legal_case'])) {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM legal_cases lc
                WHERE lc.contract_id = c.id AND lc.status != 'closed'
            )";
        }
        $limit = max(1, min(25, (int) $limit));
        return self::fetchAll(
            "SELECT c.id, c.contract_number, c.status, c.assigned_operator_id,
                    u.full_name AS customer_name, u.mobile, u.national_id,
                    op.full_name AS operator_name
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             LEFT JOIN users op ON op.id = c.assigned_operator_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.id DESC
             LIMIT {$limit}",
            $params
        );
    }

    public static function installmentStats($contractId)
    {
        return self::fetch(
            "SELECT COUNT(*) AS total,
             SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
             SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
             SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN 1 ELSE 0 END) AS active_remaining,
             SUM(CASE WHEN status NOT IN ('paid', 'cancelled') AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue,
             COALESCE(SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN GREATEST(base_amount - paid_amount, 0) ELSE 0 END),0) AS outstanding
             FROM installments
             WHERE contract_id = ?",
            [(int) $contractId]
        ) ?: ['total' => 0, 'paid' => 0, 'cancelled' => 0, 'active_remaining' => 0, 'overdue' => 0, 'outstanding' => 0];
    }

    public static function cancellationSummary($contractId)
    {
        $contractId = (int) $contractId;
        $installments = self::fetch(
            "SELECT COUNT(*) AS total_installments,
                    SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN 1 ELSE 0 END) AS active_installments,
                    SUM(CASE WHEN status = 'paid' OR paid_amount > 0 THEN 1 ELSE 0 END) AS paid_installments,
                    SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('paid', 'cancelled') AND GREATEST(base_amount - paid_amount, 0) > 0 THEN 1 ELSE 0 END) AS overdue_installments,
                    COALESCE(SUM(CASE WHEN status NOT IN ('paid', 'cancelled') THEN GREATEST(base_amount - paid_amount, 0) ELSE 0 END), 0) AS outstanding_amount
             FROM installments
             WHERE contract_id = ?",
            [$contractId]
        ) ?: [];
        $payments = self::fetch(
            "SELECT COUNT(*) AS confirmed_payment_count, COALESCE(SUM(amount), 0) AS confirmed_payment_amount
             FROM payments
             WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0",
            [$contractId]
        ) ?: [];
        $legalCaseCount = (int) (self::fetch('SELECT COUNT(*) AS total FROM legal_cases WHERE contract_id = ?', [$contractId])['total'] ?? 0);
        $documentCount = self::contractDependencyCount('generated_contract_documents', $contractId)
            + self::contractDependencyCount('contract_document_versions', $contractId);
        $accountingCount = self::contractDependencyCount('plugin_accounting_sales', $contractId)
            + self::contractDependencyCount('plugin_accounting_commissions', $contractId)
            + self::contractDependencyCount('accounting_ledger_entries', $contractId);
        return [
            'total_installments' => (int) ($installments['total_installments'] ?? 0),
            'active_installments' => (int) ($installments['active_installments'] ?? 0),
            'paid_installments' => (int) ($installments['paid_installments'] ?? 0),
            'overdue_installments' => (int) ($installments['overdue_installments'] ?? 0),
            'outstanding_amount' => normalize_money($installments['outstanding_amount'] ?? 0),
            'confirmed_payment_count' => (int) ($payments['confirmed_payment_count'] ?? 0),
            'confirmed_payment_amount' => normalize_money($payments['confirmed_payment_amount'] ?? 0),
            'legal_case_count' => $legalCaseCount,
            'document_count' => $documentCount,
            'accounting_relation_count' => $accountingCount,
        ];
    }

    public static function createWithInstallments(array $data, array $guarantorIds = [], array $items = [], array $guarantee = [], array $guarantorPeople = [])
    {
        self::ensureSchema();
        self::begin();
        try {
            self::validateFinancialData($data);
            $customer = self::fetch("SELECT id FROM users WHERE id = ? AND role = 'customer' LIMIT 1", [(int) ($data['customer_id'] ?? 0)]);
            if (!$customer) {
                throw new InvalidArgumentException('مشتری انتخاب‌شده معتبر نیست.');
            }
            $settings = Settings::allKeyed();
            $prefix = trim((string) ($settings['contract_prefix'] ?? 'PR')) ?: 'PR';
            $format = trim((string) ($settings['contract_number_format'] ?? '')) ?: ($prefix . '-{SERIAL:6}');
            $serial = max((int) $settings['contract_next_serial'], self::maxSerial() + 1);
            $contractNumber = self::uniqueNumber($format, $prefix, $serial);

            self::execute(
                'INSERT INTO contracts
                 (customer_id, contract_number, prefix, serial, principal_amount, down_payment_amount, monthly_interest_rate, interest_type, months, start_date, first_due_date, status, assigned_operator_id, notes, created_at)
                 VALUES (:customer_id, :contract_number, :prefix, :serial, :principal_amount, :down_payment_amount, :monthly_interest_rate, :interest_type, :months, :start_date, :first_due_date, :status, :assigned_operator_id, :notes, NOW())',
                [
                    'customer_id' => (int) $data['customer_id'],
                    'contract_number' => $contractNumber,
                    'prefix' => $prefix,
                    'serial' => $serial,
                    'principal_amount' => normalize_money($data['principal_amount']),
                    'down_payment_amount' => normalize_money($data['down_payment_amount'] ?? 0),
                    'monthly_interest_rate' => MoneyMath::rateLabel(MoneyMath::rateUnits($data['monthly_interest_rate'] ?? 0)),
                    'interest_type' => $data['interest_type'] === 'compound' ? 'compound' : 'simple',
                    'months' => (int) to_english_digits($data['months']),
                    'start_date' => $data['start_date'],
                    'first_due_date' => $data['first_due_date'],
                    'status' => 'active',
                    'assigned_operator_id' => $data['assigned_operator_id'] ?? null,
                    'notes' => $data['notes'] ?? '',
                ]
            );
            $contractId = (int) self::lastInsertId();
            self::syncGuarantors($contractId, $guarantorIds, (int) $data['customer_id']);
            ContractDocument::saveItems($contractId, $items);
            ContractDocument::saveGuarantee($contractId, $guarantee);
            ContractDocument::saveGuarantorPeople($contractId, $guarantorPeople);
            self::generateInstallments($contractId, $data);
            Payment::syncDownPayment($contractId, $data['created_by'] ?? null, normalize_money($data['down_payment_amount'] ?? 0), $data['start_date']);
            ContractDocument::generate($contractId, $data['created_by'] ?? null);
            Settings::set('contract_next_serial', (string) ($serial + 1));
            ContractDocument::log($contractId, 'create_contract', null, [
                'contract' => $data,
                'items' => $items,
                'guarantee' => $guarantee,
                'guarantor_people' => $guarantorPeople,
            ], 'ثبت قرارداد', $data['created_by'] ?? null);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('contract.created', [
                    'contract_id' => $contractId,
                    'customer_id' => (int) $data['customer_id'],
                    'actor_user_id' => !empty($data['created_by']) ? (int) $data['created_by'] : null,
                    'seller_user_id' => !empty($data['seller_user_id']) ? (int) $data['seller_user_id'] : null,
                ], 'contract', $contractId);
            }
            if (!empty($data['request_uuid'])) {
                if (ContractRequest::complete((string) $data['request_uuid'], $contractId) !== 1) {
                    throw new RuntimeException('ثبت شناسه یکتای قرارداد کامل نشد. لطفاً دوباره تلاش کنید.');
                }
            }
            self::commit();
            if (class_exists('SystemOutbox')) {
                SystemOutbox::processPending(25);
            }
            return $contractId;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function updateContract($id, array $data, array $guarantorIds = [], array $items = [], array $guarantee = [], array $guarantorPeople = [])
    {
        self::ensureSchema();
        $current = self::find((int) $id);
        if (!$current) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        if (($current['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قرارداد لغو شده قابل ویرایش نیست.');
        }
        self::begin();
        try {
            self::validateFinancialData($data);
            $old = [
                'contract' => self::find((int) $id),
                'items' => ContractDocument::items((int) $id),
                'guarantees' => ContractDocument::guarantees((int) $id),
                'guarantor_people' => ContractDocument::guarantorPeople((int) $id),
            ];
            self::execute(
                'UPDATE contracts SET customer_id = :customer_id, principal_amount = :principal_amount,
                 down_payment_amount = :down_payment_amount,
                 monthly_interest_rate = :monthly_interest_rate, interest_type = :interest_type,
                 months = :months, start_date = :start_date, first_due_date = :first_due_date,
                 assigned_operator_id = :assigned_operator_id, notes = :notes WHERE id = :id',
                [
                    'id' => (int) $id,
                    'customer_id' => (int) $data['customer_id'],
                    'principal_amount' => normalize_money($data['principal_amount']),
                    'down_payment_amount' => normalize_money($data['down_payment_amount'] ?? 0),
                    'monthly_interest_rate' => MoneyMath::rateLabel(MoneyMath::rateUnits($data['monthly_interest_rate'] ?? 0)),
                    'interest_type' => $data['interest_type'] === 'compound' ? 'compound' : 'simple',
                    'months' => (int) to_english_digits($data['months']),
                    'start_date' => $data['start_date'],
                    'first_due_date' => $data['first_due_date'],
                    'assigned_operator_id' => $data['assigned_operator_id'] ?? null,
                    'notes' => $data['notes'] ?? '',
                ]
            );
            self::syncGuarantors($id, $guarantorIds, (int) $data['customer_id']);
            ContractDocument::saveItems((int) $id, $items);
            ContractDocument::saveGuarantee((int) $id, $guarantee);
            ContractDocument::saveGuarantorPeople((int) $id, $guarantorPeople);
            self::syncInstallmentSchedule((int) $id, $data);
            Payment::syncDownPayment((int) $id, $data['updated_by'] ?? null, normalize_money($data['down_payment_amount'] ?? 0), $data['start_date']);
            if (($current['status'] ?? '') === 'closed') {
                self::execute(
                    "UPDATE contracts SET status = 'active', updated_at = NOW()
                     WHERE id = ? AND status IN ('closed', 'completed')
                     AND EXISTS (SELECT 1 FROM installments WHERE contract_id = ? AND status NOT IN ('paid', 'cancelled'))",
                    [(int) $id, (int) $id]
                );
            }
            self::syncCompletionStatuses((int) $id);
            ContractDocument::generate((int) $id, $data['updated_by'] ?? null);
            ContractDocument::log((int) $id, 'update_contract', $old, [
                'contract' => $data,
                'items' => $items,
                'guarantee' => $guarantee,
                'guarantor_people' => $guarantorPeople,
            ], $data['change_reason'] ?? 'ویرایش قرارداد', $data['updated_by'] ?? null);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('contract.updated', [
                    'contract_id' => (int) $id,
                    'customer_id' => (int) $data['customer_id'],
                    'actor_user_id' => !empty($data['updated_by']) ? (int) $data['updated_by'] : null,
                    'seller_user_id' => !empty($data['seller_user_id']) ? (int) $data['seller_user_id'] : null,
                ], 'contract', (int) $id);
            }
            self::commit();
            if (class_exists('SystemOutbox')) {
                SystemOutbox::processPending(25);
            }
            return true;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function bulkUpdate(array $data, $adminId = null)
    {
        self::ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['contract_ids'] ?? [])))));
        if (!$ids) {
            throw new InvalidArgumentException('حداقل یک قرارداد را انتخاب کنید.');
        }
        $ids = array_slice($ids, 0, 500);
        $mode = $data['bulk_mode'] ?? 'assign_operator';
        if (!in_array($mode, ['assign_operator', 'replace_operator'], true)) {
            throw new InvalidArgumentException('نوع عملیات دسته‌جمعی معتبر نیست.');
        }
        $newOperatorId = !empty($data['assigned_operator_id']) ? (int) $data['assigned_operator_id'] : null;
        if (!$newOperatorId) {
            throw new InvalidArgumentException('اپراتور مقصد را انتخاب کنید.');
        }
        self::assertActiveOperator($newOperatorId);
        $fromOperatorId = !empty($data['from_operator_id']) ? (int) $data['from_operator_id'] : null;
        if ($mode === 'replace_operator') {
            if (!$fromOperatorId) {
                throw new InvalidArgumentException('برای جایگزینی، اپراتور قبلی را انتخاب کنید.');
            }
            self::assertActiveOperator($fromOperatorId);
        }
        $status = in_array($data['status'] ?? '', ['active', 'referred', 'completed', 'closed'], true) ? $data['status'] : null;
        $reason = trim((string) ($data['change_reason'] ?? 'ویرایش دسته‌جمعی قراردادها'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $extraWhere = '';
        if ($mode === 'replace_operator') {
            $extraWhere = ' AND assigned_operator_id = ?';
            $params[] = $fromOperatorId;
        }
        $before = self::fetchAll("SELECT id, assigned_operator_id, status FROM contracts WHERE id IN ({$placeholders}) AND status != 'cancelled'{$extraWhere}", $params);
        if (!$before) {
            return ['updated' => 0];
        }
        $targetIds = array_map('intval', array_column($before, 'id'));
        $targetPlaceholders = implode(',', array_fill(0, count($targetIds), '?'));
        $sets = ['assigned_operator_id = ?'];
        $updateParams = [$newOperatorId];
        if ($status) {
            $sets[] = 'status = ?';
            $updateParams[] = $status;
        }
        $sets[] = 'updated_at = NOW()';
        array_push($updateParams, ...$targetIds);

        self::begin();
        try {
            self::execute(
                'UPDATE contracts SET ' . implode(', ', $sets) . " WHERE id IN ({$targetPlaceholders})",
                $updateParams
            );
            foreach ($before as $old) {
                ContractDocument::log(
                    (int) $old['id'],
                    'bulk_update_contract',
                    $old,
                    [
                        'assigned_operator_id' => $newOperatorId,
                        'status' => $status ?: ($old['status'] ?? null),
                        'mode' => $mode,
                    ],
                    $reason,
                    $adminId
                );
            }
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        return ['updated' => count($targetIds)];
    }

    protected static function assertActiveOperator($operatorId)
    {
        $user = User::find((int) $operatorId);
        if (!$user || ($user['role'] ?? '') !== 'operator' || ($user['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('اپراتور انتخاب‌شده معتبر یا فعال نیست.');
        }
    }

    public static function deleteContract($id)
    {
        throw new InvalidArgumentException('برای حذف قرارداد باید شناسه مدیر و علت حذف ثبت شود.');
    }

    public static function deletionPreview($id)
    {
        $contractId = (int) $id;
        $contract = self::fetch('SELECT * FROM contracts WHERE id = ? LIMIT 1', [$contractId]);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        $payments = self::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'paid' AND COALESCE(is_corrected, 0) = 0 THEN 1 ELSE 0 END) AS effective,
                    COALESCE(SUM(CASE WHEN status = 'paid' AND COALESCE(is_corrected, 0) = 0 THEN amount ELSE 0 END), 0) AS effective_amount,
                    SUM(CASE WHEN method = 'zibal' AND status IN ('paid','pending') THEN 1 ELSE 0 END) AS gateway_count
             FROM payments WHERE contract_id = ?",
            [$contractId]
        ) ?: [];
        $installments = self::fetch('SELECT COUNT(*) AS total, SUM(CASE WHEN status = \'paid\' OR paid_amount > 0 THEN 1 ELSE 0 END) AS paid FROM installments WHERE contract_id = ?', [$contractId]) ?: [];
        $legal = (int) (self::fetch('SELECT COUNT(*) AS total FROM legal_cases WHERE contract_id = ?', [$contractId])['total'] ?? 0);
        $dependencyCounts = [
            'payment_correction_count' => self::contractDependencyCount('payment_corrections', $contractId),
            'payment_receipt_count' => self::contractDependencyCount('payment_receipts', $contractId),
            'payment_group_count' => self::contractDependencyCount('payment_groups', $contractId),
            'payment_allocation_count' => self::contractDependencyCount('payment_allocations', $contractId),
            'generated_document_count' => self::contractDependencyCount('generated_contract_documents', $contractId),
            'document_version_count' => self::contractDependencyCount('contract_document_versions', $contractId),
            'bulk_operation_count' => self::contractDependencyCount('installment_bulk_operations', $contractId),
            'operator_call_count' => self::contractDependencyCount('operator_calls', $contractId),
            'accounting_sale_count' => self::contractDependencyCount('plugin_accounting_sales', $contractId),
            'accounting_commission_count' => self::contractDependencyCount('plugin_accounting_commissions', $contractId),
            'accounting_ledger_count' => self::contractDependencyCount('accounting_ledger_entries', $contractId),
        ];
        $blockingDependencies = [];
        foreach ([
            'payment_count' => (int) ($payments['total'] ?? 0),
            'payment_correction_count' => $dependencyCounts['payment_correction_count'],
            'payment_receipt_count' => $dependencyCounts['payment_receipt_count'],
            'payment_group_count' => $dependencyCounts['payment_group_count'],
            'payment_allocation_count' => $dependencyCounts['payment_allocation_count'],
            'legal_case_count' => $legal,
            'generated_document_count' => $dependencyCounts['generated_document_count'],
            'document_version_count' => $dependencyCounts['document_version_count'],
            'operator_call_count' => $dependencyCounts['operator_call_count'],
            'accounting_sale_count' => $dependencyCounts['accounting_sale_count'],
            'accounting_commission_count' => $dependencyCounts['accounting_commission_count'],
            'accounting_ledger_count' => $dependencyCounts['accounting_ledger_count'],
        ] as $key => $count) {
            if ($count > 0) {
                $blockingDependencies[] = $key;
            }
        }
        return [
            'contract' => $contract,
            'payment_count' => (int) ($payments['total'] ?? 0),
            'effective_payment_count' => (int) ($payments['effective'] ?? 0),
            'effective_payment_amount' => (string) ($payments['effective_amount'] ?? '0'),
            'gateway_payment_count' => (int) ($payments['gateway_count'] ?? 0),
            'installment_count' => (int) ($installments['total'] ?? 0),
            'paid_installment_count' => (int) ($installments['paid'] ?? 0),
            'legal_case_count' => $legal,
            'dependencies' => $dependencyCounts,
            'blocking_dependencies' => $blockingDependencies,
            'eligible_for_permanent_delete' => !$blockingDependencies,
        ];
    }

    protected static function contractDependencyCount($table, $contractId)
    {
        $allowedTables = [
            'payment_corrections', 'payment_receipts', 'payment_groups', 'payment_allocations',
            'generated_contract_documents', 'contract_document_versions', 'installment_bulk_operations',
            'operator_calls', 'plugin_accounting_sales', 'plugin_accounting_commissions', 'accounting_ledger_entries',
        ];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException('جدول وابستگی قرارداد معتبر نیست.');
        }
        $exists = self::fetch(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        );
        if (!$exists) {
            return 0;
        }
        return (int) (self::fetch('SELECT COUNT(*) AS total FROM `' . $table . '` WHERE contract_id = ?', [(int) $contractId])['total'] ?? 0);
    }

    public static function deleteContractSafely($id, $adminId, $reason, $typedContractNumber)
    {
        $contractId = (int) $id;
        $reason = trim((string) $reason);
        if ($contractId <= 0 || $reason === '') {
            throw new InvalidArgumentException('علت حذف قرارداد الزامی است.');
        }
        self::begin();
        try {
            $contract = self::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract) {
                throw new InvalidArgumentException('قرارداد پیدا نشد.');
            }
            if (!hash_equals((string) ($contract['contract_number'] ?? ''), trim((string) $typedContractNumber))) {
                throw new InvalidArgumentException('شماره قرارداد را دقیقاً مطابق قرارداد وارد کنید.');
            }
            $summary = self::deletionPreview($contractId);
            if (empty($summary['eligible_for_permanent_delete'])) {
                throw new InvalidArgumentException('این قرارداد دارای سابقه مالی، حقوقی، سند یا وابستگی عملیاتی است و قابل حذف نیست. از گزینه لغو یا بایگانی استفاده کنید.');
            }
            $snapshot = [
                'contract' => $contract,
                'installments' => self::fetchAll('SELECT * FROM installments WHERE contract_id = ?', [$contractId]),
                'payments' => self::fetchAll('SELECT * FROM payments WHERE contract_id = ?', [$contractId]),
                'documents' => self::fetchAll('SELECT * FROM generated_contract_documents WHERE contract_id = ?', [$contractId]),
                'document_versions' => self::fetchAll('SELECT * FROM contract_document_versions WHERE contract_id = ?', [$contractId]),
                'change_logs' => self::fetchAll('SELECT * FROM contract_change_logs WHERE contract_id = ?', [$contractId]),
                'deletion_preview' => $summary,
            ];
            self::execute(
                'INSERT INTO contract_deletion_archives (contract_id, contract_number, customer_id, deletion_reason, gateway_warning, corrected_payment_count, snapshot_json, deleted_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$contractId, $contract['contract_number'], (int) $contract['customer_id'], $reason, null, 0, json_encode($snapshot, JSON_UNESCAPED_UNICODE), (int) $adminId]
            );
            AuditLog::record('contract', 'deleted', 'contract', $contractId, [
                'actor_user_id' => $adminId,
                'customer_id' => (int) $contract['customer_id'],
                'contract_id' => $contractId,
                'description' => 'حذف دائمی قرارداد آزمایشی یا اشتباهی پس از آرشیو',
                'old_values' => ['contract_number' => $contract['contract_number']],
                'new_values' => ['reason' => $reason, 'eligibility' => $summary['blocking_dependencies']],
            ]);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('contract.deleted', [
                    'contract_id' => $contractId,
                    'customer_id' => (int) $contract['customer_id'],
                    'actor_user_id' => (int) $adminId,
                    'deleted' => true,
                ], 'contract', $contractId);
            }
            if (self::contractDependencyCount('installment_bulk_operations', $contractId) > 0) {
                self::execute('DELETE FROM installment_bulk_operations WHERE contract_id = ?', [$contractId]);
            }
            self::execute('DELETE FROM contracts WHERE id = ?', [$contractId]);
            self::commit();
            if (class_exists('SystemOutbox')) {
                try {
                    SystemOutbox::processPending(20);
                } catch (Throwable $outboxError) {
                    ErrorHandler::log('contract_delete_outbox', $outboxError, 500);
                }
            }
            return ['deleted' => true];
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function cancel($id, $reason, $adminId, $correctContractPayments = false)
    {
        self::ensureSchema();
        $contractId = (int) $id;
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new InvalidArgumentException('علت لغو قرارداد الزامی است.');
        }

        self::begin();
        try {
            $contract = self::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract) {
                throw new InvalidArgumentException('قرارداد پیدا نشد.');
            }
            if (($contract['status'] ?? '') === 'cancelled') {
                self::commit();
                return ['corrected_payments' => 0, 'already_cancelled' => true];
            }
            if (in_array(($contract['status'] ?? ''), ['completed', 'closed'], true)) {
                throw new InvalidArgumentException('قرارداد تسویه‌شده از مسیر لغو عادی قابل لغو نیست.');
            }

            $correctedPayments = 0;
            if ($correctContractPayments) {
                $correctedPayments = Payment::correctForContract(
                    $contractId,
                    'اصلاحیه مالی هنگام لغو قرارداد ' . ($contract['contract_number'] ?? '') . ': ' . $reason,
                    $adminId
                );
            }
            $summary = self::cancellationSummary($contractId);
            if ((int) $summary['confirmed_payment_count'] > 0 || normalize_money($summary['confirmed_payment_amount'] ?? 0) > 0 || (int) $summary['paid_installments'] > 0) {
                throw new InvalidArgumentException(
                    'این قرارداد دارای پرداخت مؤثر است. مبلغ پرداختی ' . money_toman($summary['confirmed_payment_amount']) . ' است؛ برای ادامه باید گزینه اصلاحیه مالی همین قرارداد را فعال کنید.'
                );
            }

            $installments = self::fetchAll(
                "SELECT id FROM installments
                 WHERE contract_id = ? AND status != 'cancelled'
                 FOR UPDATE",
                [$contractId]
            );
            $oldStatus = $contract['status'];
            $cancelledInstallmentIds = array_map('intval', array_column($installments, 'id'));
            $cancellationMetadata = json_encode([
                'previous_status' => $oldStatus,
                'cancelled_installment_ids' => $cancelledInstallmentIds,
                'financial_summary_before_cancellation' => $summary,
                'corrected_payment_count' => $correctedPayments,
            ], JSON_UNESCAPED_UNICODE);
            $updatedRows = self::execute(
                'UPDATE contracts SET status = ?, cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?, previous_status = ?, cancellation_metadata_json = ?, updated_at = NOW() WHERE id = ?',
                ['cancelled', (int) $adminId, $reason, $oldStatus, $cancellationMetadata, $contractId]
            );
            if ($updatedRows < 1) {
                $fresh = self::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1', [$contractId]);
                if (!$fresh || ($fresh['status'] ?? '') !== 'cancelled') {
                    throw new RuntimeException('وضعیت لغو قرارداد در پایگاه داده تغییر نکرد.');
                }
            }
            if ($cancelledInstallmentIds) {
                self::execute(
                    "UPDATE installments
                     SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?, updated_at = NOW()
                     WHERE contract_id = ? AND status != 'cancelled'",
                    [(int) $adminId, $reason, $contractId]
                );
            }

            ContractDocument::log(
                $contractId,
                'cancel_contract',
                ['status' => $oldStatus, 'installment_ids' => $cancelledInstallmentIds],
                ['status' => 'cancelled', 'cancelled_installment_ids' => $cancelledInstallmentIds],
                $reason,
                $adminId
            );
            AuditLog::record('contract', 'cancelled', 'contract', $contractId, [
                'actor_user_id' => $adminId,
                'customer_id' => $contract['customer_id'],
                'contract_id' => $contractId,
                'description' => 'لغو قرارداد و اقساط فعال',
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => 'cancelled',
                    'cancelled_installment_ids' => $cancelledInstallmentIds,
                    'cancellation_reason' => $reason,
                ],
            ]);
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('contract.cancelled', [
                    'contract_id' => $contractId,
                    'customer_id' => (int) $contract['customer_id'],
                    'actor_user_id' => (int) $adminId,
                ], 'contract', $contractId);
                SystemOutbox::safeEnqueueNotification(
                    (int) $contract['customer_id'],
                    'قرارداد لغو شد',
                    'قرارداد شماره ' . ($contract['contract_number'] ?? '') . ' لغو شد. برای اطلاعات بیشتر با مجموعه تماس بگیرید.',
                    'contract',
                    url('contracts/show/' . $contractId),
                    'contract',
                    $contractId
                );
            }
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }

        if (class_exists('SystemOutbox')) {
            try {
                SystemOutbox::processPending(20);
            } catch (Throwable $outboxError) {
                ErrorHandler::log('contract_cancel_outbox', $outboxError, 500);
            }
        }
        return ['corrected_payments' => $correctedPayments ?? 0];
    }

    public static function syncCompletionStatuses($contractId = null)
    {
        if ($contractId !== null && (int) $contractId > 0) {
            $contractId = (int) $contractId;
            $contract = self::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1', [$contractId]);
            if (!$contract || ($contract['status'] ?? '') === 'cancelled') {
                return;
            }
            $stats = self::fetch(
                "SELECT COUNT(*) AS total_installments,
                        SUM(CASE WHEN status NOT IN ('paid', 'cancelled') OR GREATEST(COALESCE(remaining_amount, base_amount - paid_amount), 0) > 0 THEN 1 ELSE 0 END) AS open_installments
                 FROM installments WHERE contract_id = ?",
                [$contractId]
            ) ?: [];
            $total = (int) ($stats['total_installments'] ?? 0);
            $open = (int) ($stats['open_installments'] ?? 0);
            $oldStatus = (string) ($contract['status'] ?? '');
            $newStatus = $oldStatus;
            if ($total > 0 && $open === 0) {
                $newStatus = 'completed';
                self::execute("UPDATE contracts SET status = 'completed', updated_at = NOW() WHERE id = ? AND status != 'cancelled'", [$contractId]);
            } elseif ($open > 0 && in_array(($contract['status'] ?? ''), ['completed', 'closed'], true)) {
                $newStatus = 'active';
                self::execute("UPDATE contracts SET status = 'active', updated_at = NOW() WHERE id = ? AND status != 'cancelled'", [$contractId]);
            }
            if ($newStatus !== $oldStatus && class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('contract.' . $newStatus, [
                    'contract_id' => $contractId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ], 'contract', $contractId);
            }
            return;
        }

        self::execute(
            "UPDATE contracts c
             SET status = 'completed', updated_at = NOW()
             WHERE c.status IN ('active', 'referred', 'processing', 'pending', 'closed')
             AND EXISTS (SELECT 1 FROM installments i1 WHERE i1.contract_id = c.id)
             AND NOT EXISTS (
                SELECT 1 FROM installments i2
                WHERE i2.contract_id = c.id
                  AND (i2.status NOT IN ('paid', 'cancelled') OR GREATEST(COALESCE(i2.remaining_amount, i2.base_amount - i2.paid_amount), 0) > 0)
             )"
        );
        self::execute(
            "UPDATE contracts c
             SET status = 'active', updated_at = NOW()
             WHERE c.status IN ('completed', 'closed')
             AND EXISTS (
                SELECT 1 FROM installments i
                WHERE i.contract_id = c.id
                  AND (i.status NOT IN ('paid', 'cancelled') OR GREATEST(COALESCE(i.remaining_amount, i.base_amount - i.paid_amount), 0) > 0)
             )"
        );
    }

    public static function generateInstallments($contractId, array $data)
    {
        $financedAmount = self::financedAmount($data);
        $amount = FinanceHelper::installmentAmount(
            $financedAmount,
            (int) to_english_digits($data['months']),
            MoneyMath::rateLabel(MoneyMath::rateUnits($data['monthly_interest_rate'] ?? 0)),
            $data['interest_type'] === 'compound' ? 'compound' : 'simple'
        );
        $months = max(1, (int) to_english_digits($data['months']));
        $firstDue = $data['first_due_date'];
        for ($i = 1; $i <= $months; $i++) {
            $dueDate = FinanceHelper::addMonths($firstDue, $i - 1);
            self::execute(
                'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, is_custom, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?, 0, NOW())',
                [$contractId, $i, $dueDate, $amount, $amount, $amount <= 0 ? 'paid' : ($dueDate < date('Y-m-d') ? 'overdue' : 'pending')]
            );
        }
    }

    protected static function syncInstallmentSchedule($contractId, array $data)
    {
        $contractId = (int) $contractId;
        $amount = FinanceHelper::installmentAmount(
            self::financedAmount($data),
            max(1, (int) to_english_digits($data['months'] ?? 1)),
            MoneyMath::rateLabel(MoneyMath::rateUnits($data['monthly_interest_rate'] ?? 0)),
            ($data['interest_type'] ?? 'simple') === 'compound' ? 'compound' : 'simple'
        );
        $months = max(1, (int) to_english_digits($data['months'] ?? 1));
        $firstDue = $data['first_due_date'];
        $rows = self::fetchAll(
            'SELECT * FROM installments WHERE contract_id = ? AND COALESCE(is_custom, 0) = 0 ORDER BY installment_number ASC',
            [$contractId]
        );
        $byNumber = [];
        foreach ($rows as $row) {
            $byNumber[(int) $row['installment_number']] = $row;
        }

        for ($number = 1; $number <= $months; $number++) {
            $dueDate = FinanceHelper::addMonths($firstDue, $number - 1);
            $existing = $byNumber[$number] ?? null;
            if (!$existing) {
                self::execute(
                    'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, is_custom, created_at)
                     VALUES (?, ?, ?, ?, 0, ?, ?, 0, NOW())',
                    [$contractId, $number, $dueDate, $amount, $amount, $amount <= 0 ? 'paid' : ($dueDate < date('Y-m-d') ? 'overdue' : 'pending')]
                );
                continue;
            }

            $paid = normalize_money($existing['paid_amount'] ?? 0);
            $remaining = max(0, $amount - $paid);
            $status = $remaining <= 0
                ? 'paid'
                : FinanceHelper::status($amount, $paid, $dueDate);
            self::execute(
                'UPDATE installments
                 SET due_date = ?, base_amount = ?, remaining_amount = ?, status = ?, updated_at = NOW()
                 WHERE id = ?',
                [$dueDate, $amount, $remaining, $status, (int) $existing['id']]
            );
        }

        foreach ($rows as $row) {
            $number = (int) $row['installment_number'];
            if ($number <= $months || normalize_money($row['paid_amount'] ?? 0) > 0 || ($row['status'] ?? '') === 'paid') {
                continue;
            }
            self::execute('DELETE FROM installments WHERE id = ? AND COALESCE(is_custom, 0) = 0', [(int) $row['id']]);
        }
    }

    public static function financedAmount(array $data)
    {
        return max(0, normalize_money($data['principal_amount'] ?? 0) - normalize_money($data['down_payment_amount'] ?? 0));
    }

    public static function validateFinancialData(array $data)
    {
        $principalRaw = to_english_digits((string) ($data['principal_amount'] ?? ''));
        $downPaymentRaw = to_english_digits((string) ($data['down_payment_amount'] ?? ''));
        $principal = normalize_money($data['principal_amount'] ?? 0);
        $downPayment = normalize_money($data['down_payment_amount'] ?? 0);
        $months = (int) to_english_digits($data['months'] ?? 0);
        if (strpos($principalRaw, '-') !== false || strpos($downPaymentRaw, '-') !== false) {
            throw new InvalidArgumentException('مبلغ قرارداد و پیش‌پرداخت نمی‌تواند منفی باشد.');
        }
        if ($principal <= 0) {
            throw new InvalidArgumentException('مبلغ اصل قرارداد معتبر نیست.');
        }
        if ($downPayment < 0) {
            throw new InvalidArgumentException('مبلغ پیش‌پرداخت نمی‌تواند منفی باشد.');
        }
        if ($downPayment > $principal) {
            throw new InvalidArgumentException('مبلغ پیش‌پرداخت نمی‌تواند بیشتر از مبلغ اصل قرارداد باشد.');
        }
        if ($months <= 0) {
            throw new InvalidArgumentException('تعداد اقساط معتبر نیست.');
        }
    }

    protected static function maxSerial($prefix = null)
    {
        if ($prefix !== null) {
            $row = self::fetch('SELECT MAX(serial) AS max_serial FROM contracts WHERE prefix = ?', [$prefix]);
            return (int) ($row['max_serial'] ?? 0);
        }
        $row = self::fetch('SELECT MAX(serial) AS max_serial FROM contracts');
        return (int) ($row['max_serial'] ?? 0);
    }

    protected static function uniqueNumber($format, $prefix, &$serial)
    {
        do {
            $number = self::formatContractNumber($format, $prefix, $serial);
            $exists = self::fetch('SELECT id FROM contracts WHERE contract_number = ?', [$number]);
            if ($exists) {
                $serial++;
            }
        } while ($exists);
        return $number;
    }

    protected static function formatContractNumber($format, $prefix, $serial)
    {
        $format = trim((string) $format);
        if ($format === '') {
            $format = '{PREFIX}-{SERIAL:6}';
        }
        if (preg_match('/^0+$/', $format)) {
            return str_pad((string) $serial, strlen($format), '0', STR_PAD_LEFT);
        }
        $number = preg_replace_callback('/\{SERIAL(?::(\d+))?\}/i', function ($matches) use ($serial) {
            $width = isset($matches[1]) ? max(1, (int) $matches[1]) : 0;
            return $width ? str_pad((string) $serial, $width, '0', STR_PAD_LEFT) : (string) $serial;
        }, $format);
        $number = str_replace(['{PREFIX}', '{prefix}'], $prefix, $number);
        if ($number === $format && preg_match('/^(.*?)(\d+)$/', $format, $matches)) {
            return $matches[1] . str_pad((string) $serial, strlen($matches[2]), '0', STR_PAD_LEFT);
        }
        if ($number === $format && strpos($format, '{') === false) {
            return $format . str_pad((string) $serial, 6, '0', STR_PAD_LEFT);
        }
        return $number;
    }

    protected static function syncGuarantors($contractId, array $guarantorIds, $customerId = null)
    {
        self::execute('DELETE FROM contract_guarantors WHERE contract_id = ?', [(int) $contractId]);
        $guarantorIds = array_unique(array_filter(array_map('intval', $guarantorIds)));
        foreach ($guarantorIds as $guarantorId) {
            if ($customerId && (int) $guarantorId === (int) $customerId) {
                continue;
            }
            $guarantor = self::fetch(
                "SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LIMIT 1",
                [(int) $guarantorId]
            );
            if (!$guarantor) {
                throw new InvalidArgumentException('ضامن انتخاب‌شده معتبر نیست.');
            }
            self::execute('INSERT INTO contract_guarantors (contract_id, guarantor_id) VALUES (?, ?)', [(int) $contractId, $guarantorId]);
        }
    }
}
