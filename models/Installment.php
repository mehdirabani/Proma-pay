<?php

class Installment extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        // Installment schema is provisioned by installation and migrations, never by a page request.
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];
        if (!empty($filters['contract_id'])) {
            $where[] = 'i.contract_id = ?';
            $params[] = (int) $filters['contract_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'c.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        self::appendAdvancedFilters($filters, $where, $params);
        $orderBy = !empty($filters['custom_last'])
            ? 'COALESCE(i.is_custom, 0) ASC, i.installment_number ASC, i.due_date ASC, i.id ASC'
            : 'i.due_date ASC, i.id ASC';
        $sql = "SELECT i.*, c.contract_number, c.customer_id, c.status AS contract_status, c.legal_status AS contract_legal_status,
                u.full_name AS customer_name, u.mobile, u.national_id,
                (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
                (SELECT MIN(DATE(lc.created_at)) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_started_at
                FROM installments i
                JOIN contracts c ON c.id = i.contract_id
                JOIN users u ON u.id = c.customer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . $orderBy;
        $rows = self::fetchAll($sql, $params);
        return self::withPreview($rows);
    }

    public static function filtered(array $filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = [];
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        self::appendAdvancedFilters($filters, $where, $params);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = self::fetch(
            "SELECT COUNT(*) AS total
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             {$whereSql}",
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT i.*, c.contract_number, c.customer_id, c.assigned_operator_id, c.status AS contract_status, c.legal_status AS contract_legal_status,
             u.full_name AS customer_name, u.mobile, u.national_id,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
             (SELECT MIN(DATE(lc.created_at)) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_started_at
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             {$whereSql}
             ORDER BY i.due_date ASC, i.id ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'items' => self::withPreview($rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    protected static function appendAdvancedFilters(array $filters, array &$where, array &$params)
    {
        foreach ([
            'customer_name' => 'u.full_name',
            'contract_number' => 'c.contract_number',
            'mobile' => 'u.mobile',
            'national_id' => 'u.national_id',
        ] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} LIKE ?";
                $params[] = '%' . to_english_digits($filters[$key]) . '%';
            }
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['due_from'])) {
            $where[] = 'i.due_date >= ?';
            $params[] = $filters['due_from'];
        }
        if (!empty($filters['due_to'])) {
            $where[] = 'i.due_date <= ?';
            $params[] = $filters['due_to'];
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $where[] = 'i.base_amount >= ?';
            $params[] = normalize_money($filters['amount_min']);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $where[] = 'i.base_amount <= ?';
            $params[] = normalize_money($filters['amount_max']);
        }
        if (!empty($filters['payment_state'])) {
            if ($filters['payment_state'] === 'paid') {
                $where[] = "i.status = 'paid'";
            } elseif ($filters['payment_state'] === 'unpaid') {
            $where[] = "i.status NOT IN ('paid', 'cancelled')";
            } elseif ($filters['payment_state'] === 'overdue') {
                $where[] = "i.status NOT IN ('paid', 'cancelled') AND i.due_date < CURDATE()";
            } elseif ($filters['payment_state'] === 'custom') {
                $where[] = 'COALESCE(i.is_custom, 0) = 1';
            }
        }
    }

    public static function find($id)
    {
        self::ensureSchema();
        $row = self::fetch(
            "SELECT i.*, c.contract_number, c.customer_id, c.status AS contract_status, c.legal_status AS contract_legal_status,
             u.full_name AS customer_name, u.mobile, u.national_id,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
             (SELECT MIN(DATE(lc.created_at)) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_started_at
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE i.id = ?",
            [(int) $id]
        );
        if (!$row) {
            return null;
        }
        $preview = FinanceHelper::preview($row, Payment::forInstallment($id), Settings::allKeyed());
        return array_merge($row, $preview);
    }

    public static function overdue($bucket = null, $search = null, $operatorId = null, $limit = null, $sort = 'oldest')
    {
        self::ensureSchema();
        [$where, $params] = self::overdueWhere($bucket, $search, $operatorId);
        $rows = self::fetchAll(
            "SELECT i.*, c.contract_number, c.customer_id, c.assigned_operator_id, u.full_name AS customer_name,
             c.status AS contract_status, c.legal_status AS contract_legal_status,
             u.mobile, u.secondary_phone, u.national_id,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
             (SELECT MIN(DATE(lc.created_at)) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_started_at
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE {$where}
             ORDER BY " . self::overdueOrderBy($sort) . ""
             . ($limit ? ' LIMIT ' . max(1, min(100, (int) $limit)) : ''),
            $params
        );
        return self::withPreview($rows);
    }

    public static function overduePaginated($bucket = null, $search = null, $operatorId = null, array $options = [])
    {
        self::ensureSchema();
        [$where, $params] = self::overdueWhere($bucket, $search, $operatorId);
        $sort = self::overdueSort($options['sort'] ?? 'oldest');
        $count = self::fetch(
            "SELECT COUNT(*) AS total
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE {$where}",
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(10, min(100, (int) ($options['per_page'] ?? 40)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT i.*, c.contract_number, c.customer_id, c.assigned_operator_id, u.full_name AS customer_name,
             c.status AS contract_status, c.legal_status AS contract_legal_status,
             u.mobile, u.secondary_phone, u.national_id,
             (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count,
             (SELECT MIN(DATE(lc.created_at)) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_started_at
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE {$where}
             ORDER BY " . self::overdueOrderBy($sort) . "
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'items' => self::withPreview($rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    protected static function overdueWhere($bucket = null, $search = null, $operatorId = null)
    {
        $today = date('Y-m-d');
        $where = "c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date < ?";
        $params = [$today];
        if ($bucket === 'today') {
            $where = "c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date = ?";
            $params = [$today];
        } elseif ($bucket === '1-7') {
            $where .= ' AND DATEDIFF(?, i.due_date) BETWEEN 1 AND 7';
            $params[] = $today;
        } elseif ($bucket === '8-30') {
            $where .= ' AND DATEDIFF(?, i.due_date) BETWEEN 8 AND 30';
            $params[] = $today;
        } elseif ($bucket === '30+') {
            $where .= ' AND DATEDIFF(?, i.due_date) > 30';
            $params[] = $today;
        }
        if ($search) {
            $needle = '%' . to_english_digits($search) . '%';
            $where .= ' AND (c.contract_number LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        if ($operatorId) {
            $where .= ' AND c.assigned_operator_id = ?';
            $params[] = (int) $operatorId;
        }
        return [$where, $params];
    }

    protected static function overdueSort($sort)
    {
        $allowed = ['oldest', 'newest', 'amount_desc', 'amount_asc', 'name_asc', 'name_desc'];
        return in_array($sort, $allowed, true) ? $sort : 'oldest';
    }

    protected static function overdueOrderBy($sort)
    {
        switch (self::overdueSort($sort)) {
            case 'newest': return 'i.due_date DESC, i.id DESC';
            case 'amount_desc': return '(GREATEST(i.base_amount - i.paid_amount, 0) + COALESCE(i.penalty, 0)) DESC, i.id DESC';
            case 'amount_asc': return '(GREATEST(i.base_amount - i.paid_amount, 0) + COALESCE(i.penalty, 0)) ASC, i.id ASC';
            case 'name_asc': return 'u.full_name ASC, i.due_date ASC, i.id ASC';
            case 'name_desc': return 'u.full_name DESC, i.due_date DESC, i.id DESC';
            default: return 'i.due_date ASC, i.id ASC';
        }
    }

    public static function createCustom($contractId, $dueDate, $amount, $notes = '', $guaranteeSerial = '', $title = '')
    {
        self::ensureSchema();
        $contract = self::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1', [(int) $contractId]);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        if (in_array(($contract['status'] ?? ''), ['cancelled', 'completed', 'closed'], true)) {
            throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده قسط جدید قابل ثبت نیست.');
        }
        $number = (int) self::fetch('SELECT COALESCE(MAX(installment_number), 0) + 1 AS n FROM installments WHERE contract_id = ?', [$contractId])['n'];
        $amount = normalize_money($amount);
        self::execute(
            'INSERT INTO installments (contract_id, installment_number, due_date, base_amount, paid_amount, remaining_amount, status, notes, guarantee_serial, is_custom, custom_title, custom_description, created_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 1, ?, ?, NOW())',
            [(int) $contractId, $number, $dueDate, $amount, $amount, $dueDate < date('Y-m-d') ? 'overdue' : 'pending', trim((string) $notes), trim(to_english_digits($guaranteeSerial)) ?: null, trim((string) $title) ?: null, trim((string) $notes) ?: null]
        );
    }

    public static function updateInstallment($id, array $data)
    {
        self::ensureSchema();
        $row = self::find((int) $id);
        if (!$row) {
            throw new InvalidArgumentException('قسط پیدا نشد.');
        }
        if (($row['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قسط لغو شده قابل ویرایش نیست.');
        }
        $dueDate = parse_jalali_date($data['due_date'] ?? '') ?: ($data['due_date'] ?? null);
        if (!$dueDate) {
            throw new InvalidArgumentException('تاریخ سررسید معتبر نیست.');
        }
        $amount = normalize_money($data['base_amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ قسط معتبر نیست.');
        }
        $paid = min(normalize_money($row['paid_amount'] ?? 0), $amount);
        $status = FinanceHelper::status($amount, $paid, $dueDate);
        self::execute(
            'UPDATE installments SET due_date = ?, base_amount = ?, paid_amount = ?, remaining_amount = ?, status = ?, notes = ?, guarantee_serial = ?, custom_title = ?, custom_description = ? WHERE id = ?',
            [
                $dueDate,
                $amount,
                $paid,
                max(0, $amount - $paid),
                $status,
                trim((string) ($data['notes'] ?? $row['notes'] ?? '')) ?: null,
                trim(to_english_digits($data['guarantee_serial'] ?? $row['guarantee_serial'] ?? '')) ?: null,
                trim((string) ($data['custom_title'] ?? $row['custom_title'] ?? '')) ?: null,
                trim((string) ($data['custom_description'] ?? $data['notes'] ?? $row['custom_description'] ?? '')) ?: null,
                (int) $id,
            ]
        );
    }

    public static function deleteCustom($id)
    {
        self::ensureSchema();
        $row = self::fetch('SELECT * FROM installments WHERE id = ?', [(int) $id]);
        if (!$row) {
            throw new InvalidArgumentException('قسط پیدا نشد.');
        }
        if ((int) ($row['is_custom'] ?? 0) !== 1) {
            throw new InvalidArgumentException('فقط قسط دلخواه قابل حذف است.');
        }
        self::execute('DELETE FROM installments WHERE id = ?', [(int) $id]);
        return (int) $row['contract_id'];
    }

    public static function bulkAction($contractId, array $ids, $action, $reason, $userId)
    {
        $contractId = (int) $contractId;
        $reason = trim((string) $reason);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($contractId <= 0 || !$ids || $reason === '') {
            throw new InvalidArgumentException('اقساط و علت عملیات دسته‌جمعی را کامل کنید.');
        }
        if (!in_array($action, ['cancel', 'restore_pending', 'recalculate'], true)) {
            throw new InvalidArgumentException('عملیات دسته‌جمعی اقساط معتبر نیست.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        self::begin();
        try {
            $contract = self::fetch('SELECT status FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract || in_array(($contract['status'] ?? ''), ['cancelled', 'completed', 'closed'], true)) {
                throw new InvalidArgumentException('برای قرارداد لغو یا تسویه‌شده عملیات دسته‌جمعی قسط قابل انجام نیست.');
            }
            $rows = self::fetchAll("SELECT * FROM installments WHERE contract_id = ? AND id IN ({$placeholders}) FOR UPDATE", array_merge([$contractId], $ids));
            if (count($rows) !== count($ids)) {
                throw new InvalidArgumentException('یکی از اقساط انتخاب‌شده به این قرارداد تعلق ندارد.');
            }
            $old = $rows;
            $updated = 0;
            foreach ($rows as $row) {
                if ($action === 'cancel') {
                    $effective = (int) (self::fetch("SELECT COUNT(*) AS total FROM payments WHERE installment_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0", [(int) $row['id']])['total'] ?? 0);
                    if ($effective > 0 || normalize_money($row['paid_amount'] ?? 0) > 0) {
                        throw new InvalidArgumentException('قسط دارای پرداخت مؤثر است و بدون اصلاحیه قابل لغو نیست.');
                    }
                    self::execute('UPDATE installments SET status = \'cancelled\', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?, updated_at = NOW() WHERE id = ?', [(int) $userId, $reason, (int) $row['id']]);
                } elseif ($action === 'restore_pending') {
                    if (($row['status'] ?? '') === 'paid' || normalize_money($row['paid_amount'] ?? 0) > 0) {
                        throw new InvalidArgumentException('قسط پرداخت‌شده قابل بازگردانی مستقیم نیست.');
                    }
                    $status = ($row['due_date'] ?? '') < date('Y-m-d') ? 'overdue' : 'pending';
                    self::execute('UPDATE installments SET status = ?, cancelled_at = NULL, cancelled_by = NULL, cancellation_reason = NULL, updated_at = NOW() WHERE id = ?', [$status, (int) $row['id']]);
                } else {
                    if (($row['status'] ?? '') === 'cancelled') {
                        continue;
                    }
                    $baseAmount = normalize_money($row['base_amount'] ?? 0);
                    $paidAmount = normalize_money($row['paid_amount'] ?? 0);
                    $status = FinanceHelper::status($baseAmount, $paidAmount, $row['due_date']);
                    self::execute('UPDATE installments SET status = ?, remaining_amount = ?, updated_at = NOW() WHERE id = ?', [$status, max(0, $baseAmount - $paidAmount), (int) $row['id']]);
                }
                $updated++;
            }
            $new = self::fetchAll("SELECT * FROM installments WHERE contract_id = ? AND id IN ({$placeholders}) ORDER BY installment_number", array_merge([$contractId], $ids));
            $number = 'BIO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            self::execute('INSERT INTO installment_bulk_operations (operation_number, contract_id, operation_type, installment_ids_json, old_snapshot_json, new_snapshot_json, reason, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())', [$number, $contractId, $action, json_encode($ids), json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($new, JSON_UNESCAPED_UNICODE), $reason, (int) $userId]);
            $operationId = (int) self::lastInsertId();
            if (class_exists('AuditLog')) {
                AuditLog::record('installment', 'bulk_updated', 'installment_bulk_operation', $operationId, ['actor_user_id' => $userId, 'contract_id' => $contractId, 'new_values' => ['action' => $action, 'ids' => $ids, 'reason' => $reason]]);
            }
            if (class_exists('SystemOutbox')) {
                SystemOutbox::safeEnqueuePluginHook('installment.bulk_updated', [
                    'contract_id' => $contractId,
                    'installment_ids' => $ids,
                    'actor_user_id' => $userId,
                    'action' => $action,
                ], 'installment_bulk_operation', $operationId);
            }
            Contract::syncCompletionStatuses($contractId);
            self::commit();
            if (class_exists('SystemOutbox')) {
                SystemOutbox::processPending(20);
            }
            return ['updated' => $updated, 'operation_id' => $operationId];
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function adjust($id, $penalty, $reward)
    {
        $row = self::fetch('SELECT status FROM installments WHERE id = ?', [(int) $id]);
        if (!$row || ($row['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قسط لغو شده قابل اصلاح نیست.');
        }
        self::execute(
            'UPDATE installments SET manual_penalty_adjustment = ?, manual_reward_adjustment = ? WHERE id = ?',
            [normalize_money($penalty), normalize_money($reward), (int) $id]
        );
        self::refreshStatus($id);
    }

    public static function discountPenalty($id, $type, $value, $createdBy)
    {
        $installment = self::find($id);
        if (!$installment) {
            return false;
        }
        if (($installment['status'] ?? '') === 'cancelled') {
            return false;
        }
        $rateUnits = $type === 'percent' ? MoneyMath::rateUnits($value) : 0;
        $value = $type === 'percent' ? MoneyMath::rateLabel($rateUnits) : normalize_money($value);
        $discount = $type === 'percent'
            ? MoneyMath::ceilMulDiv(normalize_money($installment['penalty'] ?? 0), $rateUnits, MoneyMath::PERCENT_DENOMINATOR)
            : $value;
        self::execute('UPDATE installments SET penalty_discount_amount = penalty_discount_amount + ? WHERE id = ?', [$discount, (int) $id]);
        self::execute(
            'INSERT INTO penalties (installment_id, type, amount, percent, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $id, $type === 'percent' ? 'percent' : 'fixed', $discount, $type === 'percent' ? $value : null, $createdBy]
        );
        return true;
    }

    public static function markPaid($id, $userId)
    {
        $installment = self::find($id);
        if (!$installment) {
            return false;
        }
        $amount = normalize_money($installment['payable'] ?? 0);
        if ($amount > 0) {
            Payment::record($id, $installment['contract_id'], $userId, $amount, 'manual', 'paid', null, null, 'تسویه دستی قسط');
        }
        self::execute('UPDATE installments SET paid_amount = base_amount, remaining_amount = 0, last_payment_date = CURDATE(), status = ? WHERE id = ?', ['paid', (int) $id]);
        return true;
    }

    public static function refreshStatus($id)
    {
        $row = self::fetch('SELECT * FROM installments WHERE id = ?', [(int) $id]);
        if (!$row) {
            return;
        }
        if (($row['status'] ?? '') === 'cancelled') {
            return;
        }
        $baseAmount = normalize_money($row['base_amount'] ?? 0);
        $paidAmount = normalize_money($row['paid_amount'] ?? 0);
        $status = FinanceHelper::status($baseAmount, $paidAmount, $row['due_date']);
        self::execute('UPDATE installments SET status = ?, remaining_amount = ? WHERE id = ?', [$status, max(0, $baseAmount - $paidAmount), (int) $id]);
    }

    public static function withPreview(array $rows)
    {
        $settings = Settings::allKeyed();
        foreach ($rows as &$row) {
            $storedStatus = $row['status'] ?? null;
            $storedRemaining = normalize_money($row['remaining_amount'] ?? (normalize_money($row['base_amount'] ?? 0) - normalize_money($row['paid_amount'] ?? 0)));
            $preview = FinanceHelper::preview($row, Payment::forInstallment($row['id']), $settings);
            $row = array_merge($row, $preview);
            if ($row['status'] !== $storedStatus || $storedRemaining !== normalize_money($preview['remaining_amount'] ?? 0)) {
                self::execute('UPDATE installments SET status = ?, remaining_amount = ? WHERE id = ?', [$row['status'], $row['remaining_amount'], $row['id']]);
            }
        }
        unset($row);
        return $rows;
    }
}
