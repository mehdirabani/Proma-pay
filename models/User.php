<?php

class User extends Model
{
    protected static $profileColumnsReady = false;
    protected static $medalSchemaReady = false;

    public static function ensureProfileColumns()
    {
        if (self::$profileColumnsReady) {
            return;
        }
        foreach ([
            'address' => 'TEXT NULL',
            'avatar_key' => "VARCHAR(40) NULL",
            'father_name' => 'VARCHAR(190) NULL',
            'issued_from' => 'VARCHAR(190) NULL',
            'department' => 'VARCHAR(40) NULL',
            'is_department_manager' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE users ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        self::$profileColumnsReady = true;
    }

    public static function find($id)
    {
        return self::fetch('SELECT * FROM users WHERE id = ?', [(int) $id]);
    }

    public static function findForPasswordReset($identifier)
    {
        $identifier = trim(to_english_digits((string) $identifier));
        if ($identifier === '') {
            return null;
        }
        self::ensureProfileColumns();
        return self::fetch(
            "SELECT * FROM users
             WHERE status = 'active'
             AND (username = :username OR mobile = :mobile OR national_id = :national_id OR email = :email)
             LIMIT 1",
            [
                'username' => $identifier,
                'mobile' => $identifier,
                'national_id' => $identifier,
                'email' => $identifier,
            ]
        );
    }

    public static function all($role = null, $search = null, $status = null, array $options = [])
    {
        self::ensureProfileColumns();
        $params = [];
        $where = self::userListWhere($role, $search, $status, $options, $params);
        $limitSql = '';
        if (!empty($options['limit'])) {
            $limitSql = ' LIMIT ' . max(1, min(100, (int) $options['limit']));
        }
        $sql = 'SELECT * FROM users' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC' . $limitSql;
        return self::fetchAll($sql, $params);
    }

    public static function paginated($role = null, $search = null, $status = null, array $options = [])
    {
        self::ensureProfileColumns();
        $params = [];
        $where = self::userListWhere($role, $search, $status, $options, $params);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = self::fetch("SELECT COUNT(*) AS total FROM users{$whereSql}", $params);
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(6, min(100, (int) ($options['per_page'] ?? $options['limit'] ?? 36)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT * FROM users{$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
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

    protected static function userListWhere($role, $search, $status, array $options, array &$params)
    {
        $where = [];
        if ($role) {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        $where[] = "(username IS NULL OR username != 'proma_notice_bot')";
        if (!empty($options['roles'])) {
            $roles = array_values(array_intersect((array) $options['roles'], ['admin', 'operator', 'lawyer', 'customer']));
            if ($roles) {
                $where[] = 'role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
                array_push($params, ...$roles);
            }
        }
        if (array_key_exists('department', $options) && $options['department'] !== null && $options['department'] !== '') {
            $where[] = 'department = ?';
            $params[] = $options['department'];
        }
        if ($status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search) {
            $needle = '%' . to_english_digits($search) . '%';
            $where[] = '(full_name LIKE ? OR national_id LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR username LIKE ? OR email LIKE ? OR department LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle, $needle, $needle);
        }
        return $where;
    }

    public static function customers($search = null, $status = null)
    {
        return self::all('customer', $search, $status);
    }

    public static function searchCustomers($query, $limit = 10)
    {
        self::ensureProfileColumns();
        $query = trim(to_english_digits((string) $query));
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }
        $needle = '%' . $query . '%';
        $limit = max(1, min(25, (int) $limit));
        return self::fetchAll(
            "SELECT id, full_name, mobile, national_id, status
             FROM users
             WHERE role = 'customer'
             AND (full_name LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR national_id LIKE ? OR address LIKE ?)
             ORDER BY id DESC
             LIMIT {$limit}",
            [$needle, $needle, $needle, $needle, $needle]
        );
    }

    public static function searchActiveCustomers($query, $limit = 10)
    {
        self::ensureProfileColumns();
        $query = trim(to_english_digits((string) $query));
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }
        $needle = '%' . $query . '%';
        $limit = max(1, min(20, (int) $limit));
        return self::fetchAll(
            "SELECT id, full_name, mobile, secondary_phone, national_id, status
             FROM users
             WHERE role = 'customer'
             AND status = 'active'
             AND (full_name LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR national_id LIKE ? OR address LIKE ? OR id = ?)
             ORDER BY full_name ASC
             LIMIT {$limit}",
            [$needle, $needle, $needle, $needle, $needle, ctype_digit($query) ? (int) $query : 0]
        );
    }

    public static function searchUsers($query, array $roles = [], $limit = 10)
    {
        self::ensureProfileColumns();
        $query = trim(to_english_digits((string) $query));
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }
        $roles = array_values(array_intersect($roles, ['admin', 'operator', 'lawyer', 'customer']));
        $needle = '%' . $query . '%';
        $params = [$needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle];
        $roleSql = '';
        if ($roles) {
            $roleSql = ' AND role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
            array_push($params, ...$roles);
        }
        $limit = max(1, min(25, (int) $limit));
        return self::fetchAll(
            "SELECT id, full_name, mobile, secondary_phone, national_id, role, department, is_department_manager, status
             FROM users
             WHERE status = 'active'
             AND (username IS NULL OR username != 'proma_notice_bot')
             AND (full_name LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR national_id LIKE ? OR role LIKE ? OR department LIKE ? OR username LIKE ? OR email LIKE ?)
             {$roleSql}
             ORDER BY FIELD(role, 'admin', 'operator', 'lawyer', 'customer'), full_name
             LIMIT {$limit}",
            $params
        );
    }

    public static function customerSummaries($search = null, $status = null, $limit = null)
    {
        Payment::ensureCorrectionSchema();
        $params = [];
        $where = self::customerSummaryWhere($search, $status, $params);
        return self::fetchAll(
            "SELECT u.*,
             COUNT(DISTINCT c.id) AS contract_count,
             COUNT(DISTINCT i.id) AS installment_count,
             COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END) AS paid_installments,
             COUNT(DISTINCT CASE WHEN i.status != 'paid' AND i.due_date < CURDATE() THEN i.id END) AS overdue_installments,
             COALESCE(SUM(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0 THEN p.amount ELSE 0 END),0) AS paid_total
             FROM users u
             LEFT JOIN contracts c ON c.customer_id = u.id
             LEFT JOIN installments i ON i.contract_id = c.id
             LEFT JOIN payments p ON p.installment_id = i.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.id
             ORDER BY u.id DESC"
             . ($limit ? ' LIMIT ' . max(1, min(100, (int) $limit)) : ''),
            $params
        );
    }

    public static function customerSummariesPaginated($search = null, $status = null, $page = 1, $perPage = 24)
    {
        Payment::ensureCorrectionSchema();
        $params = [];
        $where = self::customerSummaryWhere($search, $status, $params);
        $whereSql = implode(' AND ', $where);
        $count = self::fetch("SELECT COUNT(*) AS total FROM users u WHERE {$whereSql}", $params);
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(6, min(100, (int) $perPage));
        $page = max(1, (int) $page);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT u.*,
             COUNT(DISTINCT c.id) AS contract_count,
             COUNT(DISTINCT i.id) AS installment_count,
             COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END) AS paid_installments,
             COUNT(DISTINCT CASE WHEN i.status != 'paid' AND i.due_date < CURDATE() THEN i.id END) AS overdue_installments,
             COALESCE(SUM(CASE WHEN p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0 THEN p.amount ELSE 0 END),0) AS paid_total
             FROM users u
             LEFT JOIN contracts c ON c.customer_id = u.id
             LEFT JOIN installments i ON i.contract_id = c.id
             LEFT JOIN payments p ON p.installment_id = i.id
             WHERE {$whereSql}
             GROUP BY u.id
             ORDER BY u.id DESC
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

    protected static function customerSummaryWhere($search, $status, array &$params)
    {
        $where = ["u.role = 'customer'"];
        if ($status) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($search) {
            $needle = '%' . to_english_digits($search) . '%';
            $where[] = '(u.full_name LIKE ? OR u.national_id LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        return $where;
    }

    public static function medalsForUsers(array $userIds)
    {
        self::ensureMedalSchema();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = self::fetchAll("SELECT * FROM medals WHERE user_id IN ({$placeholders}) AND COALESCE(is_active, 1) = 1 ORDER BY source ASC, id DESC", $userIds);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['user_id']][] = $row;
        }
        return $grouped;
    }

    public static function addMedal($userId, $title, $description = '', $points = 0)
    {
        self::ensureMedalSchema();
        self::execute(
            'INSERT INTO medals (user_id, title, description, points, icon_key, source, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
            [(int) $userId, trim($title), trim($description), (int) to_english_digits($points), 'award', 'manual']
        );
    }

    public static function syncAutomaticMedals($userId)
    {
        $userId = (int) $userId;
        self::ensureMedalSchema();
        if ($userId <= 0) {
            return;
        }
        $contractCount = (int) (self::fetch('SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ?', [$userId])['total'] ?? 0);
        if ($contractCount >= 5) {
            self::addMedalOnce($userId, '۵ قرارداد اقساطی', 'ثبت حداقل پنج قرارداد اقساطی', 50);
        }

        $earlyContract = self::fetch(
            "SELECT c.id
             FROM contracts c
             JOIN installments i ON i.contract_id = c.id
             WHERE c.customer_id = ?
             GROUP BY c.id
             HAVING COUNT(i.id) > 0
             AND SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) = COUNT(i.id)
             AND MAX(i.last_payment_date) IS NOT NULL
             AND MAX(i.last_payment_date) < MAX(i.due_date)
             LIMIT 1",
            [$userId]
        );
        if ($earlyContract) {
            self::addMedalOnce($userId, 'تسویه زودهنگام قرارداد', 'تسویه کامل یک قرارداد پیش از آخرین سررسید', 80);
        }

        $earlyInstallments = (int) (self::fetch(
            "SELECT COUNT(*) AS total
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             WHERE c.customer_id = ? AND i.status = 'paid' AND i.last_payment_date IS NOT NULL AND i.last_payment_date < i.due_date",
            [$userId]
        )['total'] ?? 0);
        if ($earlyInstallments >= 5) {
            self::addMedalOnce($userId, '۵ قسط زودپرداخت', 'پرداخت حداقل پنج قسط زودتر از سررسید', 60);
        }
        if (class_exists('IdentityDocument') && IdentityDocument::isVerified($userId)) {
            self::addMedalOnce($userId, 'احراز هویت کامل', 'مدارک هویتی مشتری تایید شده است.', 30);
        }
        $paidTotal = (float) (self::fetch(
            "SELECT COALESCE(SUM(p.amount),0) AS total
             FROM payments p
             JOIN contracts c ON c.id = p.contract_id
             WHERE c.customer_id = ? AND p.status = 'paid' AND COALESCE(p.is_corrected,0) = 0",
            [$userId]
        )['total'] ?? 0);
        if ($paidTotal >= 100000000) {
            self::addMedalOnce($userId, 'مشتری ممتاز', 'جمع پرداخت موفق مشتری از حد ممتاز عبور کرده است.', 100);
        }
    }

    protected static function addMedalOnce($userId, $title, $description, $points)
    {
        self::ensureMedalSchema();
        $code = 'auto_' . substr(hash('sha1', $title), 0, 24);
        $exists = self::fetch('SELECT id FROM medals WHERE user_id = ? AND code = ? LIMIT 1', [(int) $userId, $code]);
        if ($exists) {
            self::execute('UPDATE medals SET is_active = 1, source = ? WHERE id = ?', ['auto', (int) $exists['id']]);
            return;
        }
        self::execute(
            'INSERT INTO medals (user_id, title, description, points, code, icon_key, source, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [(int) $userId, $title, $description, (int) $points, $code, 'award', 'auto']
        );
    }

    public static function deleteMedal($id)
    {
        self::execute('DELETE FROM medals WHERE id = ?', [(int) $id]);
    }

    public static function updateMedal($id, array $data)
    {
        self::ensureMedalSchema();
        self::execute(
            'UPDATE medals SET title = ?, description = ?, points = ?, icon_key = ?, is_active = ?, updated_at = NOW() WHERE id = ?',
            [
                trim((string) ($data['title'] ?? '')),
                trim((string) ($data['description'] ?? '')),
                (int) to_english_digits($data['points'] ?? 0),
                preg_replace('/[^a-z0-9_-]/i', '', (string) ($data['icon_key'] ?? 'award')) ?: 'award',
                isset($data['is_active']) ? 1 : 0,
                (int) $id,
            ]
        );
    }

    public static function ensureMedalSchema()
    {
        if (self::$medalSchemaReady) {
            return;
        }
        foreach ([
            'icon_key' => "VARCHAR(40) NULL DEFAULT 'award'",
            'source' => "VARCHAR(40) NOT NULL DEFAULT 'manual'",
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'updated_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE medals ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        self::$medalSchemaReady = true;
    }

    public static function create(array $data)
    {
        self::ensureProfileColumns();
        self::execute(
            'INSERT INTO users (role, username, full_name, father_name, issued_from, national_id, mobile, secondary_phone, email, password_hash, status, address, avatar_key, department, is_department_manager, created_at)
             VALUES (:role, :username, :full_name, :father_name, :issued_from, :national_id, :mobile, :secondary_phone, :email, :password_hash, :status, :address, :avatar_key, :department, :is_department_manager, NOW())',
            [
                'role' => $data['role'],
                'username' => $data['username'] ?: null,
                'full_name' => $data['full_name'],
                'father_name' => trim((string) ($data['father_name'] ?? '')) ?: null,
                'issued_from' => trim((string) ($data['issued_from'] ?? '')) ?: null,
                'national_id' => trim(to_english_digits($data['national_id'] ?? '')) ?: null,
                'mobile' => trim(to_english_digits($data['mobile'] ?? '')) ?: null,
                'secondary_phone' => trim(to_english_digits($data['secondary_phone'] ?? '')) ?: null,
                'email' => $data['email'] ?: null,
                'password_hash' => password_hash($data['password'] ?: bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                'status' => $data['status'] ?? 'active',
                'address' => $data['address'] ?? '',
                'avatar_key' => $data['avatar_key'] ?? null,
                'department' => $data['department'] ?? null,
                'is_department_manager' => !empty($data['is_department_manager']) ? 1 : 0,
            ]
        );
        return (int) self::lastInsertId();
    }

    public static function updateUser($id, array $data)
    {
        self::ensureProfileColumns();
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $params = [
            'id' => $id,
            'role' => $data['role'] ?? $user['role'],
            'username' => ($data['username'] ?? $user['username']) ?: null,
            'full_name' => $data['full_name'] ?? $user['full_name'],
            'father_name' => $data['father_name'] ?? ($user['father_name'] ?? null),
            'issued_from' => $data['issued_from'] ?? ($user['issued_from'] ?? null),
            'national_id' => trim(to_english_digits($data['national_id'] ?? $user['national_id'])) ?: null,
            'mobile' => trim(to_english_digits($data['mobile'] ?? $user['mobile'])) ?: null,
            'secondary_phone' => trim(to_english_digits($data['secondary_phone'] ?? $user['secondary_phone'])) ?: null,
            'email' => ($data['email'] ?? $user['email']) ?: null,
            'status' => $data['status'] ?? $user['status'],
            'address' => $data['address'] ?? ($user['address'] ?? ''),
            'avatar_key' => $data['avatar_key'] ?? ($user['avatar_key'] ?? null),
            'department' => $data['department'] ?? ($user['department'] ?? null),
            'is_department_manager' => array_key_exists('is_department_manager', $data) ? (!empty($data['is_department_manager']) ? 1 : 0) : (int) ($user['is_department_manager'] ?? 0),
        ];
        $passwordSql = '';
        if (!empty($data['password'])) {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        self::execute(
            "UPDATE users SET role = :role, username = :username, full_name = :full_name,
             father_name = :father_name, issued_from = :issued_from,
             national_id = :national_id, mobile = :mobile, secondary_phone = :secondary_phone,
             email = :email, status = :status, address = :address, avatar_key = :avatar_key,
             department = :department, is_department_manager = :is_department_manager {$passwordSql} WHERE id = :id",
            $params
        );
        return true;
    }

    public static function mergeCustomers($keepId, $mergeId, $adminId = null)
    {
        self::ensureProfileColumns();
        self::ensureMergeSchema();
        $keepId = (int) $keepId;
        $mergeId = (int) $mergeId;
        if ($keepId <= 0 || $mergeId <= 0 || $keepId === $mergeId) {
            throw new InvalidArgumentException('دو مشتری متفاوت را برای ادغام انتخاب کنید.');
        }
        $keep = self::find($keepId);
        $merge = self::find($mergeId);
        if (!$keep || !$merge || ($keep['role'] ?? '') !== 'customer' || ($merge['role'] ?? '') !== 'customer') {
            throw new InvalidArgumentException('هر دو پرونده باید مشتری معتبر باشند.');
        }

        $snapshot = [
            'keep' => $keep,
            'merge' => $merge,
        ];
        $keepUpdates = self::mergedCustomerProfile($keep, $merge);

        self::begin();
        try {
            self::execute(
                'INSERT INTO customer_merge_logs (keep_customer_id, merged_customer_id, merged_by, snapshot_json, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$keepId, $mergeId, $adminId ?: null, json_encode($snapshot, JSON_UNESCAPED_UNICODE)]
            );

            self::mergeGuarantors($keepId, $mergeId);
            foreach ([
                'contracts.customer_id',
                'legal_cases.customer_id',
                'payment_receipts.customer_id',
                'operator_calls.customer_id',
                'notifications.user_id',
                'identity_documents.user_id',
                'profile_update_requests.user_id',
                'password_resets.user_id',
                'events.user_id',
                'events.assigned_user_id',
                'ai_action_logs.user_id',
                'import_batches.user_id',
                'payments.user_id',
                'messages.sender_id',
                'messages.receiver_id',
            ] as $target) {
                [$table, $column] = explode('.', $target, 2);
                self::safeExecute("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?", [$keepId, $mergeId]);
            }

            self::mergeMedals($keepId, $mergeId);

            self::execute(
                'UPDATE users SET username = NULL, national_id = NULL, mobile = NULL, email = NULL, status = ?, full_name = ?, updated_at = NOW() WHERE id = ?',
                ['inactive', trim((string) $merge['full_name']) . ' (ادغام شده در #' . $keepId . ')', $mergeId]
            );

            if ($keepUpdates) {
                $sets = [];
                $params = [];
                foreach ($keepUpdates as $column => $value) {
                    $sets[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sets[] = 'updated_at = NOW()';
                $params[] = $keepId;
                self::execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            }

            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }

        return true;
    }

    protected static function ensureMergeSchema()
    {
        self::execute(
            "CREATE TABLE IF NOT EXISTS customer_merge_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                keep_customer_id BIGINT UNSIGNED NOT NULL,
                merged_customer_id BIGINT UNSIGNED NOT NULL,
                merged_by BIGINT UNSIGNED NULL,
                snapshot_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_customer_merge_keep (keep_customer_id),
                KEY idx_customer_merge_merged (merged_customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected static function mergedCustomerProfile(array $keep, array $merge)
    {
        $updates = [];
        foreach (['father_name', 'issued_from', 'national_id', 'mobile', 'email', 'address', 'avatar_key'] as $field) {
            if (trim((string) ($keep[$field] ?? '')) === '' && trim((string) ($merge[$field] ?? '')) !== '') {
                $updates[$field] = $merge[$field];
            }
        }
        if (trim((string) ($keep['secondary_phone'] ?? '')) === '') {
            $secondary = trim((string) ($merge['secondary_phone'] ?? ''));
            $mergeMobile = trim((string) ($merge['mobile'] ?? ''));
            $keepMobile = trim((string) ($keep['mobile'] ?? ''));
            if ($secondary !== '') {
                $updates['secondary_phone'] = $secondary;
            } elseif ($mergeMobile !== '' && $mergeMobile !== $keepMobile) {
                $updates['secondary_phone'] = $mergeMobile;
            }
        }
        return $updates;
    }

    protected static function mergeGuarantors($keepId, $mergeId)
    {
        self::safeExecute(
            'INSERT IGNORE INTO contract_guarantors (contract_id, guarantor_id)
             SELECT contract_id, ? FROM contract_guarantors WHERE guarantor_id = ?',
            [$keepId, $mergeId]
        );
        self::safeExecute('DELETE FROM contract_guarantors WHERE guarantor_id = ?', [$mergeId]);
    }

    protected static function mergeMedals($keepId, $mergeId)
    {
        self::ensureMedalSchema();
        $rows = self::fetchAll('SELECT * FROM medals WHERE user_id = ?', [$mergeId]);
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $exists = self::fetch('SELECT id FROM medals WHERE user_id = ? AND code = ? LIMIT 1', [$keepId, $code]);
                if ($exists) {
                    self::execute('UPDATE medals SET code = NULL, source = ? WHERE id = ?', ['merged', (int) $row['id']]);
                }
            }
        }
        self::safeExecute('UPDATE medals SET user_id = ? WHERE user_id = ?', [$keepId, $mergeId]);
    }

    protected static function safeExecute($sql, array $params = [])
    {
        try {
            self::execute($sql, $params);
        } catch (Throwable $e) {
        }
    }

    public static function setPassword($id, $password)
    {
        self::execute('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [
            password_hash((string) $password, PASSWORD_DEFAULT),
            (int) $id,
        ]);
    }

    public static function applyProfileData($id, array $data)
    {
        self::ensureProfileColumns();
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $payload = [
            'role' => $user['role'],
            'username' => $user['username'],
            'full_name' => $data['full_name'] ?? $user['full_name'],
            'national_id' => $user['national_id'],
            'mobile' => $data['mobile'] ?? $user['mobile'],
            'secondary_phone' => $data['secondary_phone'] ?? $user['secondary_phone'],
            'email' => $data['email'] ?? $user['email'],
            'status' => $user['status'],
            'address' => $data['address'] ?? ($user['address'] ?? ''),
            'avatar_key' => $data['avatar_key'] ?? ($user['avatar_key'] ?? null),
        ];
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        return self::updateUser($id, $payload);
    }

    public static function deleteUser($id)
    {
        return self::execute('DELETE FROM users WHERE id = ?', [(int) $id]);
    }

    public static function countAdmins()
    {
        return (int) self::fetch('SELECT COUNT(*) AS total FROM users WHERE role = ?', ['admin'])['total'];
    }

    public static function staffContacts()
    {
        return self::fetchAll("SELECT id, full_name, role, department FROM users WHERE role IN ('admin','operator','lawyer') AND status = 'active' ORDER BY role, full_name");
    }
}
