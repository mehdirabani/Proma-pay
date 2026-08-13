<?php

class LegalCase extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        SchemaGuard::requireColumns('legal_cases', ['lawyer_id', 'customer_id', 'contract_id', 'status', 'stage', 'complaint_number', 'notice_date', 'court_date', 'hearing_date', 'expense_amount', 'expense_reason', 'notes', 'created_at', 'updated_at']);
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
                AND c.status != 'cancelled'
                AND i.status NOT IN ('paid', 'cancelled')
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
        return normalize_money($row['total'] ?? 0);
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
             JOIN contracts c ON c.id = i.contract_id
             WHERE i.contract_id = ? AND c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date < CURDATE()",
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
        if (!empty($case['legal_referred_at'])) {
            $items[] = [
                'kind' => 'legal_referral_confirmed',
                'title' => 'ارجاع رسمی حقوقی ثبت شد',
                'description' => 'این زمانِ ثبت‌شده مبنای آغاز محاسبه جریمه حقوقی واقعی است؛ پرونده داخلی یا پیش‌نویس به‌تنهایی این اثر را ندارد.',
                'actor_name' => 'سامانه',
                'status' => 'referred',
                'stage' => 'ارجاع رسمی ثبت‌شده',
                'happened_at' => $case['legal_referred_at'],
            ];
        }

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
                'cost_amount' => normalize_money($log['cost_amount'] ?? 0),
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
        $status = in_array($data['status'] ?? null, ['open', 'referred', 'under_legal_review', 'warning_prepared', 'draft_prepared', 'external_submission_confirmed', 'closed', 'archived'], true)
            ? $data['status']
            : $case['status'];
        $lawyerId = array_key_exists('lawyer_id', $data) && !empty($data['lawyer_id'])
            ? (int) $data['lawyer_id']
            : $case['lawyer_id'];
        $confirmReferral = self::isActualReferralStatus($status);
        self::execute(
            'UPDATE legal_cases SET lawyer_id = ?, stage = ?, status = ?, legal_referred_at = CASE WHEN ? = 1 AND legal_referred_at IS NULL THEN NOW() ELSE legal_referred_at END, updated_at = NOW() WHERE id = ?',
            [
                $lawyerId ?: null,
                $stage,
                $status,
                $confirmReferral ? 1 : 0,
                (int) $id,
            ]
        );
        if ($confirmReferral && empty($case['legal_referred_at'])) {
            self::recordReferralAudit((int) $id, (int) ($case['contract_id'] ?? 0), 'تایید ارجاع رسمی از مسیر لاگ پرونده');
        }
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
        $openCase = self::fetch("SELECT id FROM legal_cases WHERE contract_id = ? AND status NOT IN ('closed', 'archived') ORDER BY id DESC LIMIT 1", [(int) $contractId]);
        if ($openCase) {
            self::execute(
                'UPDATE legal_cases SET lawyer_id = COALESCE(?, lawyer_id), stage = ?, status = ?, notes = ?, legal_referred_at = COALESCE(legal_referred_at, NOW()), updated_at = NOW() WHERE id = ?',
                [$lawyerId ?: null, $reason ?: 'ارجاع به حقوقی', 'referred', trim($reason . "\n" . $notes), (int) $openCase['id']]
            );
            $caseId = (int) $openCase['id'];
        } else {
            self::execute(
                'INSERT INTO legal_cases (lawyer_id, customer_id, contract_id, status, stage, notes, expense_amount, legal_referred_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), NOW())',
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
            self::recordReferralAudit($caseId, (int) $contractId, 'ارجاع رسمی حقوقی ثبت و مبنای محاسبه جریمه حقوقی شد.');
            Notification::createForLegalCase(
                $case,
                'پرونده حقوقی قرارداد',
                'پرونده حقوقی قرارداد ' . ($case['contract_number'] ?? '') . ' ثبت یا بروزرسانی شد.',
                url('legal/show/' . $caseId)
            );
        }
        // Creating an internal file is not an official judicial action; do
        // not send a message that could be read as a formal legal notice.
        return $caseId;
    }

    public static function contractIsEligible($contractId)
    {
        return !empty(LegalEligibilityService::forContract((int) $contractId)['eligible']);
    }

    /** Legal staff may open one internal review case. It is never a legal referral by itself. */
    public static function createSelfInitiated($lawyerId, $contractId, $notes = '', $requestUuid = '')
    {
        self::ensureSchema();
        $lawyerId = (int) $lawyerId;
        $contractId = (int) $contractId;
        $lawyer = User::find($lawyerId);
        if (!$lawyer || ($lawyer['role'] ?? '') !== 'lawyer' || ($lawyer['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('حساب واحد حقوقی معتبر نیست.');
        }
        $requestUuid = strtolower(trim((string) $requestUuid));
        if (!preg_match('/^[a-f0-9]{24,64}$/', $requestUuid)) {
            throw new InvalidArgumentException('شناسه یکتای درخواست پرونده معتبر نیست.');
        }
        self::begin();
        try {
            $existingRequest = self::fetch('SELECT legal_case_id, payload_hash FROM legal_case_requests WHERE request_uuid = ? FOR UPDATE', [$requestUuid]);
            if ($existingRequest) {
                $expected = hash('sha256', $contractId . '|' . trim((string) $notes));
                if (!hash_equals((string) $existingRequest['payload_hash'], $expected)) {
                    throw new InvalidArgumentException('این شناسه درخواست با اطلاعات دیگری قبلاً استفاده شده است.');
                }
                self::commit();
                return (int) $existingRequest['legal_case_id'];
            }
            $contract = self::fetch('SELECT * FROM contracts WHERE id = ? FOR UPDATE', [$contractId]);
            if (!$contract) throw new InvalidArgumentException('قرارداد معتبر انتخاب نشده است.');
            if (($contract['status'] ?? '') === 'cancelled') {
                throw new InvalidArgumentException('برای قرارداد لغوشده نمی‌توان پرونده حقوقی داخلی ایجاد کرد.');
            }
            $eligibility = LegalEligibilityService::forContract($contractId);
            $activePolicy = LegalEligibilityService::activePolicy();
            if (empty($activePolicy['policy']['allow_self_initiation'])) {
                throw new InvalidArgumentException('ایجاد مستقل پرونده در سیاست حقوقی این قرارداد مجاز نیست.');
            }
            $active = self::fetch("SELECT id FROM legal_cases WHERE contract_id = ? AND status NOT IN ('closed', 'archived') AND archived_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE", [$contractId]);
            if ($active) throw new InvalidArgumentException('برای این قرارداد یک پرونده حقوقی فعال وجود دارد.');
            self::execute(
                "INSERT INTO legal_cases (lawyer_id, customer_id, contract_id, status, stage, notes, expense_amount, request_uuid, legal_referred_at, created_at, updated_at)
                 VALUES (?, ?, ?, 'under_legal_review', 'تشکیل پرونده داخلی', ?, 0, ?, NULL, NOW(), NOW())",
                [$lawyerId, (int) $contract['customer_id'], $contractId, trim((string) $notes), $requestUuid]
            );
            $caseId = (int) self::lastInsertId();
            self::execute(
                'INSERT INTO legal_case_requests (request_uuid, contract_id, legal_case_id, requested_by, payload_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$requestUuid, $contractId, $caseId, $lawyerId, hash('sha256', $contractId . '|' . trim((string) $notes))]
            );
            self::execute("UPDATE contracts SET legal_status = 'under_legal_review', status = CASE WHEN status = 'closed' THEN status ELSE 'referred' END WHERE id = ?", [$contractId]);
            $case = self::find($caseId);
            if ($case) {
                try {
                    AuditLog::record('legal_case', 'legal.case.self_initiated', 'legal_case', $caseId, [
                        'actor_user_id' => $lawyerId, 'contract_id' => $contractId, 'customer_id' => (int) $contract['customer_id'],
                        'new_values' => ['eligibility' => $eligibility], 'description' => 'تشکیل داخلی پرونده حقوقی پس از ارزیابی سیاست نسخه‌دار.',
                    ]);
                } catch (Throwable $ignored) {}
                Notification::create($lawyerId, 'پرونده داخلی حقوقی ایجاد شد', 'پرونده داخلی قرارداد ' . ($case['contract_number'] ?? '') . ' برای بررسی حقوقی ایجاد شد.', 'legal', url('legal/show/' . $caseId), 'legal-case:' . $caseId);
            }
            self::commit();
            return $caseId;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function updateCase($id, array $data)
    {
        self::ensureSchema();
        $previous = self::find((int) $id);
        if (!$previous) {
            throw new InvalidArgumentException('پرونده حقوقی پیدا نشد.');
        }
        $expense = normalize_money($data['expense_amount'] ?? 0);
        if ($expense > 0 && trim((string) ($data['expense_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('علت هزینه حقوقی الزامی است.');
        }
        $noticeDate = parse_jalali_date($data['notice_date'] ?? '') ?: null;
        $courtDate = parse_jalali_date($data['court_date'] ?? '') ?: null;
        $hearingDate = parse_jalali_date($data['hearing_date'] ?? '') ?: null;
        $status = in_array($data['status'] ?? '', ['open', 'referred', 'under_legal_review', 'warning_prepared', 'draft_prepared', 'external_submission_confirmed', 'closed', 'archived'], true)
            ? (string) $data['status'] : (string) $previous['status'];
        $confirmReferral = self::isActualReferralStatus($status);
        self::execute(
            'UPDATE legal_cases SET lawyer_id = ?, stage = ?, status = ?, complaint_number = ?, notice_date = ?, court_date = ?, hearing_date = ?, expense_amount = ?, expense_reason = ?, notes = ?, legal_referred_at = CASE WHEN ? = 1 AND legal_referred_at IS NULL THEN NOW() ELSE legal_referred_at END, updated_at = NOW() WHERE id = ?',
            [
                $data['lawyer_id'] ?: null,
                $data['stage'],
                $status,
                $data['complaint_number'] ?: null,
                $noticeDate,
                $courtDate,
                $hearingDate,
                $expense,
                $data['expense_reason'] ?? '',
                $data['notes'] ?? '',
                $confirmReferral ? 1 : 0,
                (int) $id,
            ]
        );
        if ($confirmReferral && empty($previous['legal_referred_at'])) {
            self::recordReferralAudit((int) $id, (int) ($previous['contract_id'] ?? 0), 'تایید ارجاع رسمی حقوقی از ویرایش پرونده');
        }
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
            // Legal history is evidence. Archive the case; never destroy its
            // logs, drafts, payment relation or audit history.
            self::execute("UPDATE legal_cases SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = ?", [(int) $id]);
            $openOther = self::fetch(
                "SELECT id FROM legal_cases WHERE contract_id = ? AND status NOT IN ('closed', 'archived') AND archived_at IS NULL ORDER BY id DESC LIMIT 1",
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

    /** Only an explicit referral/confirmed external filing activates the real rate. */
    public static function isActualReferralStatus(string $status): bool
    {
        return in_array($status, ['referred', 'external_submission_confirmed'], true);
    }

    private static function recordReferralAudit(int $caseId, int $contractId, string $description): void
    {
        try {
            AuditLog::record('legal_case', 'legal.case.referral_confirmed', 'legal_case', $caseId, [
                'actor_user_id' => Auth::id(),
                'contract_id' => $contractId,
                'new_values' => ['legal_referred_at' => date('Y-m-d H:i:s')],
                'description' => $description,
            ]);
        } catch (Throwable $ignored) {
            // Audit log must not turn a valid, persisted referral into a retry.
        }
    }

    public static function eligibleContracts($search = null)
    {
        return LegalEligibilityService::queue($search, 100);
    }
}
