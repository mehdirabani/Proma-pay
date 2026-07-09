<?php

class LegalCase extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE legal_cases ADD COLUMN expense_reason TEXT NULL AFTER expense_amount');
        } catch (Throwable $e) {
        }
        foreach ([
            'notice_date' => 'DATE NULL AFTER complaint_number',
            'court_date' => 'DATE NULL AFTER notice_date',
            'hearing_date' => 'DATE NULL AFTER court_date',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE legal_cases ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        self::$schemaReady = true;
    }

    public static function all($filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = self::listWhere($filters, $params);
        [$sort, $direction] = self::sortSql($filters);
        $sql = "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile,
                lawyer.full_name AS lawyer_name
                FROM legal_cases lc
                JOIN contracts c ON c.id = lc.contract_id
                JOIN users u ON u.id = lc.customer_id
                LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY {$sort} {$direction}, lc.id DESC";
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(100, (int) $filters['limit']));
        }
        return self::fetchAll($sql, $params);
    }

    public static function paginated(array $filters = [])
    {
        self::ensureSchema();
        $params = [];
        $where = self::listWhere($filters, $params);
        [$sort, $direction] = self::sortSql($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = self::fetch(
            "SELECT COUNT(*) AS total
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             {$whereSql}",
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 30)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile,
                    lawyer.full_name AS lawyer_name
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id
             {$whereSql}
             ORDER BY {$sort} {$direction}, lc.id DESC
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
        if (!empty($filters['eligible_only'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM installments i
                WHERE i.contract_id = lc.contract_id
                AND i.status != 'paid'
                AND i.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
        }
        if (!empty($filters['lawyer_id'])) {
            $where[] = 'lc.lawyer_id = ?';
            $params[] = (int) $filters['lawyer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'lc.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $needle = '%' . to_english_digits($filters['search']) . '%';
            $where[] = '(c.contract_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.national_id LIKE ? OR lc.complaint_number LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        return $where;
    }

    protected static function sortSql(array $filters)
    {
        $sortMap = [
            'created' => 'lc.created_at',
            'updated' => 'lc.updated_at',
            'contract' => 'c.contract_number',
            'customer' => 'u.full_name',
            'status' => 'lc.status',
        ];
        $sort = $sortMap[$filters['sort'] ?? ''] ?? 'lc.id';
        $direction = strtolower($filters['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        return [$sort, $direction];
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT lc.*, c.contract_number, c.principal_amount, c.down_payment_amount, c.status AS contract_status,
                    u.full_name AS customer_name, u.mobile, u.national_id, u.secondary_phone,
                    lawyer.full_name AS lawyer_name
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id
             WHERE lc.id = ?",
            [(int) $id]
        );
    }

    public static function forContract($contractId)
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile,
                    lawyer.full_name AS lawyer_name
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id
             WHERE lc.contract_id = ?
             ORDER BY CASE WHEN lc.status = 'closed' THEN 1 ELSE 0 END, lc.updated_at DESC, lc.id DESC",
            [(int) $contractId]
        );
    }

    public static function latestForContract($contractId)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT lc.*, c.contract_number, u.full_name AS customer_name, u.mobile,
                    lawyer.full_name AS lawyer_name
             FROM legal_cases lc
             JOIN contracts c ON c.id = lc.contract_id
             JOIN users u ON u.id = lc.customer_id
             LEFT JOIN users lawyer ON lawyer.id = lc.lawyer_id
             WHERE lc.contract_id = ?
             ORDER BY CASE WHEN lc.status = 'closed' THEN 1 ELSE 0 END, lc.updated_at DESC, lc.id DESC
             LIMIT 1",
            [(int) $contractId]
        );
    }

    public static function expenseTotalForContract($contractId)
    {
        self::ensureSchema();
        $row = self::fetch(
            'SELECT COALESCE(SUM(expense_amount), 0) AS total FROM legal_cases WHERE contract_id = ?',
            [(int) $contractId]
        );
        return (float) ($row['total'] ?? 0);
    }

    public static function hasAccessibleCaseForLawyer($contractId, $lawyerId)
    {
        self::ensureSchema();
        if (!$lawyerId) {
            return false;
        }
        $case = self::fetch(
            "SELECT id
             FROM legal_cases
             WHERE contract_id = ? AND lawyer_id = ? AND status != 'closed'
             ORDER BY id DESC
             LIMIT 1",
            [(int) $contractId, (int) $lawyerId]
        );
        return (bool) $case;
    }

    public static function canAccess(array $case, array $user)
    {
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'lawyer') {
            return !empty($case['lawyer_id']) && (int) $case['lawyer_id'] === (int) $user['id'];
        }
        return false;
    }

    public static function overdueCountForCase($caseId)
    {
        $case = self::find((int) $caseId);
        if (!$case) {
            return 0;
        }
        $row = self::fetch(
            "SELECT COUNT(*) AS total
             FROM installments i
             WHERE i.contract_id = ? AND i.status != 'paid' AND i.due_date < CURDATE()",
            [(int) $case['contract_id']]
        );
        return (int) ($row['total'] ?? 0);
    }

    public static function timeline($caseId)
    {
        self::ensureSchema();
        $case = self::find((int) $caseId);
        if (!$case) {
            return [];
        }
        LegalCaseLog::ensureSchema();
        $items = [];
        $items[] = [
            'kind' => 'case_created',
            'title' => 'ثبت پرونده حقوقی',
            'description' => $case['notes'] ?? '',
            'actor_name' => 'سامانه',
            'status' => $case['status'] ?? '',
            'stage' => $case['stage'] ?? '',
            'happened_at' => $case['created_at'] ?? '',
        ];

        $logs = LegalCaseLog::forCase((int) $caseId);
        foreach ($logs as $log) {
            $items[] = [
                'kind' => 'legal_log',
                'title' => $log['action_title'] ?? '',
                'description' => $log['description'] ?? '',
                'actor_name' => $log['registered_by_name'] ?: 'سامانه',
                'assignee_name' => $log['assigned_lawyer_name'] ?: '',
                'status' => $log['next_status'] ?? '',
                'stage' => $log['action_stage'] ?? '',
                'cost_amount' => (float) ($log['cost_amount'] ?? 0),
                'cost_type' => $log['cost_type'] ?? '',
                'attachment_id' => !empty($log['attachment_path']) ? (int) $log['id'] : null,
                'happened_at' => trim(($log['action_date'] ?? '') . ' ' . ($log['action_time'] ?? '00:00:00')),
                'created_at' => $log['created_at'] ?? '',
            ];
        }

        if (!empty($case['updated_at']) && ($case['updated_at'] ?? '') !== ($case['created_at'] ?? '')) {
            $items[] = [
                'kind' => 'case_updated',
                'title' => 'آخرین بروزرسانی پرونده',
                'description' => status_label($case['status'] ?? '') . ' - ' . ($case['stage'] ?? ''),
                'actor_name' => 'سامانه',
                'status' => $case['status'] ?? '',
                'stage' => $case['stage'] ?? '',
                'happened_at' => $case['updated_at'],
            ];
        }

        usort($items, function ($a, $b) {
            return strcmp((string) ($b['happened_at'] ?? ''), (string) ($a['happened_at'] ?? ''));
        });
        return $items;
    }

    public static function paymentsForCase($caseId)
    {
        $case = self::find((int) $caseId);
        if (!$case) {
            return [];
        }
        return Payment::forLegalCase((int) $case['contract_id'], (int) $case['customer_id']);
    }

    public static function applyLogUpdate($id, array $data)
    {
        self::ensureSchema();
        $case = self::find((int) $id);
        if (!$case) {
            return;
        }
        $stage = trim((string) ($data['stage'] ?? '')) ?: $case['stage'];
        $status = in_array($data['status'] ?? null, ['open', 'referred', 'closed'], true)
            ? $data['status']
            : $case['status'];
        $lawyerId = array_key_exists('lawyer_id', $data) && !empty($data['lawyer_id'])
            ? (int) $data['lawyer_id']
            : $case['lawyer_id'];
        self::execute(
            'UPDATE legal_cases SET lawyer_id = ?, stage = ?, status = ?, updated_at = NOW() WHERE id = ?',
            [
                $lawyerId ?: null,
                $stage,
                $status,
                (int) $id,
            ]
        );
    }

    public static function createCase($lawyerId, $contractId, $notes = '', $reason = '')
    {
        self::ensureSchema();
        $contract = Contract::find($contractId);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد معتبر انتخاب نشده است.');
        }
        $lawyerId = !empty($lawyerId) ? (int) $lawyerId : null;
        if ($lawyerId) {
            $lawyer = User::find($lawyerId);
            if (!$lawyer || ($lawyer['role'] ?? '') !== 'lawyer' || ($lawyer['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('وکیل انتخاب‌شده معتبر نیست.');
            }
        }
        if (!self::contractIsEligible((int) $contractId)) {
            throw new InvalidArgumentException('ارجاع حقوقی فقط برای اقساط پرداخت‌نشده با بیش از ۳۰ روز تأخیر مجاز است.');
        }
        $reason = trim((string) $reason);
        $notes = trim((string) $notes);
        $openCase = self::fetch("SELECT id FROM legal_cases WHERE contract_id = ? AND status != 'closed' ORDER BY id DESC LIMIT 1", [(int) $contractId]);
        if ($openCase) {
            self::execute(
                'UPDATE legal_cases SET lawyer_id = COALESCE(?, lawyer_id), stage = ?, status = ?, notes = ?, updated_at = NOW() WHERE id = ?',
                [$lawyerId ?: null, $reason ?: 'ارجاع به حقوقی', 'referred', trim($reason . "\n" . $notes), (int) $openCase['id']]
            );
            $caseId = (int) $openCase['id'];
        } else {
            self::execute(
                'INSERT INTO legal_cases (lawyer_id, customer_id, contract_id, status, stage, notes, expense_amount, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())',
                [$lawyerId ?: null, $contract['customer_id'], (int) $contractId, 'referred', $reason ?: 'ارجاع به حقوقی', trim($reason . "\n" . $notes)]
            );
            $caseId = (int) self::lastInsertId();
        }
        self::execute(
            'UPDATE contracts SET legal_status = ?, status = CASE WHEN status = ? THEN status ELSE ? END WHERE id = ?',
            ['referred', 'closed', 'referred', (int) $contractId]
        );
        if (class_exists('ContractDocument')) {
            ContractDocument::log((int) $contractId, 'refer_legal', null, ['reason' => $reason, 'notes' => $notes, 'lawyer_id' => $lawyerId], 'ارجاع به واحد حقوقی', Auth::id());
        }
        $case = self::find($caseId);
        if ($case) {
            Notification::createForLegalCase(
                $case,
                'پرونده حقوقی قرارداد',
                'پرونده حقوقی قرارداد ' . ($case['contract_number'] ?? '') . ' ثبت یا بروزرسانی شد.',
                url('legal/show/' . $caseId)
            );
        }
        try {
            Chat::botMessage((int) $contract['customer_id'], 'پرونده حقوقی قرارداد شما ثبت شد و اطلاع‌رسانی‌های بعدی مستقیم ارسال می‌شود.', url('notifications'));
        } catch (Throwable $ignored) {
        }
        return $caseId;
    }

    public static function contractIsEligible($contractId)
    {
        $row = self::fetch(
            "SELECT COUNT(*) AS total
             FROM installments
             WHERE contract_id = ?
             AND status != 'paid'
             AND due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [(int) $contractId]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    public static function updateCase($id, array $data)
    {
        self::ensureSchema();
        $expense = normalize_money($data['expense_amount'] ?? 0);
        if ($expense > 0 && trim((string) ($data['expense_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('علت هزینه حقوقی الزامی است.');
        }
        $noticeDate = parse_jalali_date($data['notice_date'] ?? '') ?: null;
        $courtDate = parse_jalali_date($data['court_date'] ?? '') ?: null;
        $hearingDate = parse_jalali_date($data['hearing_date'] ?? '') ?: null;
        self::execute(
            'UPDATE legal_cases SET lawyer_id = ?, stage = ?, status = ?, complaint_number = ?, notice_date = ?, court_date = ?, hearing_date = ?, expense_amount = ?, expense_reason = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [
                $data['lawyer_id'] ?: null,
                $data['stage'],
                $data['status'],
                $data['complaint_number'] ?: null,
                $noticeDate,
                $courtDate,
                $hearingDate,
                $expense,
                $data['expense_reason'] ?? '',
                $data['notes'] ?? '',
                (int) $id,
            ]
        );
        $case = self::find((int) $id);
        if ($case) {
            Event::syncLegalCaseEvents((int) $id, (int) ($case['lawyer_id'] ?? 0), [
                'notice_date' => $noticeDate,
                'court_date' => $courtDate,
                'hearing_date' => $hearingDate,
            ], $case['contract_number'] ?? '', $case['customer_name'] ?? '');
            Notification::createForLegalCase(
                $case,
                'بروزرسانی پرونده حقوقی',
                'وضعیت پرونده قرارداد ' . ($case['contract_number'] ?? '') . ' بروزرسانی شد.',
                url('legal/show/' . (int) $id)
            );
        }
    }

    public static function deleteCase($id)
    {
        self::ensureSchema();
        $case = self::find((int) $id);
        if (!$case) {
            throw new InvalidArgumentException('پرونده حقوقی پیدا نشد.');
        }
        self::begin();
        try {
            foreach (LegalCaseLog::forCase((int) $id) as $log) {
                LegalCaseLog::deleteLog((int) $log['id']);
            }
            try {
                Event::execute('DELETE FROM events WHERE description LIKE ?', ['legal_case:' . (int) $id . ':%']);
            } catch (Throwable $e) {
            }
            self::execute('DELETE FROM legal_cases WHERE id = ?', [(int) $id]);
            $openOther = self::fetch(
                "SELECT id FROM legal_cases WHERE contract_id = ? AND status != 'closed' ORDER BY id DESC LIMIT 1",
                [(int) $case['contract_id']]
            );
            if (!$openOther) {
                self::execute(
                    "UPDATE contracts
                     SET legal_status = NULL,
                         status = CASE WHEN status = 'referred' THEN 'active' ELSE status END
                     WHERE id = ?",
                    [(int) $case['contract_id']]
                );
            }
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        return true;
    }

    public static function eligibleContracts($search = null)
    {
        $params = [];
        $where = "i.status != 'paid' AND i.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        if (trim((string) $search) !== '') {
            $needle = '%' . to_english_digits($search) . '%';
            $where .= ' AND (c.contract_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        return self::fetchAll(
            "SELECT DISTINCT c.*, u.full_name AS customer_name, u.mobile
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             JOIN installments i ON i.contract_id = c.id
             WHERE {$where}
             ORDER BY c.id DESC",
            $params
        );
    }
}
