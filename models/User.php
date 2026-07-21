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
        SchemaGuard::requireColumns('users', [
            'address', 'avatar_key', 'avatar_path', 'avatar_version', 'avatar_updated_at',
            'father_name', 'issued_from', 'department', 'is_department_manager',
        ]);
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
            "SELECT id, full_name, mobile, secondary_phone, national_id, status
             FROM users
             WHERE role = 'customer'
             AND (full_name LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR national_id LIKE ? OR address LIKE ?)
             ORDER BY id DESC
             LIMIT {$limit}",
            [$needle, $needle, $needle, $needle, $needle]
        );
    }

    public static function identityMatches($nationalId = '', $mobile = '', $email = '')
    {
        self::ensureProfileColumns();
        $nationalId = trim(to_english_digits((string) $nationalId));
        $mobile = self::normalizePhoneValue($mobile);
        $email = trim((string) $email);
        $checks = [];
        $params = [];
        if ($nationalId !== '') {
            $checks[] = 'national_id = ?';
            $params[] = $nationalId;
        }
        if ($mobile !== '') {
            $checks[] = '(mobile = ? OR secondary_phone = ?)';
            array_push($params, $mobile, $mobile);
        }
        if ($email !== '') {
            $checks[] = 'email = ?';
            $params[] = $email;
        }
        if (!$checks) {
            return [];
        }
        $rows = self::fetchAll(
            'SELECT id, full_name, mobile, secondary_phone, national_id, email, status
             FROM users
             WHERE role = ? AND (' . implode(' OR ', $checks) . ')
             ORDER BY CASE WHEN status = ? THEN 0 ELSE 1 END, id DESC
             LIMIT 10',
            array_merge(['customer'], $params, ['active'])
        );
        foreach ($rows as &$row) {
            $matchTypes = [];
            if ($nationalId !== '' && trim(to_english_digits((string) ($row['national_id'] ?? ''))) === $nationalId) {
                $matchTypes[] = 'national_id';
            }
            $rowMobile = self::normalizePhoneValue($row['mobile'] ?? '');
            $rowSecondary = self::normalizePhoneValue($row['secondary_phone'] ?? '');
            if ($mobile !== '' && ($rowMobile === $mobile || $rowSecondary === $mobile)) {
                $matchTypes[] = 'mobile';
            }
            if ($email !== '' && strcasecmp(trim((string) ($row['email'] ?? '')), $email) === 0) {
                $matchTypes[] = 'email';
            }
            $row['match_types'] = $matchTypes;
        }
        unset($row);
        return $rows;
    }

    public static function normalizePhone($value)
    {
        return self::normalizePhoneValue($value);
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
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' THEN c.id END) AS contract_count,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status != 'cancelled' THEN i.id END) AS installment_count,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status = 'paid' THEN i.id END) AS paid_installments,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date < CURDATE() THEN i.id END) AS overdue_installments,
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
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' THEN c.id END) AS contract_count,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status != 'cancelled' THEN i.id END) AS installment_count,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status = 'paid' THEN i.id END) AS paid_installments,
             COUNT(DISTINCT CASE WHEN c.status != 'cancelled' AND i.status NOT IN ('paid', 'cancelled') AND i.due_date < CURDATE() THEN i.id END) AS overdue_installments,
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
        if (class_exists('Medal')) {
            $grouped = Medal::mergeLegacy($grouped, $userIds);
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
        self::execute('UPDATE medals SET is_active = 0, updated_at = NOW() WHERE id = ?', [(int) $id]);
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
        SchemaGuard::requireColumns('medals', ['user_id', 'title', 'description', 'points', 'code', 'icon_key', 'source', 'is_active', 'updated_at', 'created_at']);
        self::$medalSchemaReady = true;
    }

    public static function create(array $data)
    {
        self::ensureProfileColumns();
        $data = self::prepareUserData($data);
        self::assertUniqueIdentity($data);
        $avatarKey = avatar_key_for($data['avatar_key'] ?? null, ($data['national_id'] ?? '') . '|' . ($data['mobile'] ?? '') . '|' . ($data['full_name'] ?? ''));
        $avatarSuggestion = avatar_suggestion_for($data['full_name'] ?? '', $data['role'] ?? '');
        $avatarExplicit = !empty($data['avatar_key']) && in_array((string) $data['avatar_key'], avatar_options(), true);
        self::execute(
            'INSERT INTO users (role, username, full_name, father_name, issued_from, national_id, mobile, secondary_phone, email, password_hash, status, address, avatar_key, avatar_category, avatar_source, avatar_locked, avatar_suggestion_reason, department, is_department_manager, created_at)
             VALUES (:role, :username, :full_name, :father_name, :issued_from, :national_id, :mobile, :secondary_phone, :email, :password_hash, :status, :address, :avatar_key, :avatar_category, :avatar_source, :avatar_locked, :avatar_suggestion_reason, :department, :is_department_manager, NOW())',
            [
                'role' => $data['role'],
                'username' => trim(to_english_digits($data['username'] ?? '')) ?: null,
                'full_name' => $data['full_name'],
                'father_name' => trim((string) ($data['father_name'] ?? '')) ?: null,
                'issued_from' => trim((string) ($data['issued_from'] ?? '')) ?: null,
                'national_id' => trim(to_english_digits($data['national_id'] ?? '')) ?: null,
                'mobile' => self::normalizePhoneValue($data['mobile'] ?? '') ?: null,
                'secondary_phone' => self::normalizePhoneValue($data['secondary_phone'] ?? '') ?: null,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'password_hash' => password_hash(($data['password'] ?? '') ?: bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                'status' => $data['status'] ?? 'active',
                'address' => $data['address'] ?? '',
                'avatar_key' => $avatarExplicit ? $avatarKey : $avatarSuggestion['key'],
                'avatar_category' => $avatarSuggestion['category'],
                'avatar_source' => $avatarExplicit ? 'manual' : $avatarSuggestion['source'],
                'avatar_locked' => $avatarExplicit ? 1 : 0,
                'avatar_suggestion_reason' => $avatarExplicit ? 'انتخاب دستی کاربر.' : $avatarSuggestion['reason'],
                'department' => $data['department'] ?? null,
                'is_department_manager' => !empty($data['is_department_manager']) ? 1 : 0,
            ]
        );
        $userId = (int) self::lastInsertId();
        if (class_exists('PluginManager')) {
            PluginManager::fire('user.created', ['user_id' => $userId, 'role' => $data['role'] ?? '', 'actor_user_id' => Auth::id() ?: null], true);
        }
        return $userId;
    }

    public static function findDuplicateCustomer(array $data)
    {
        self::ensureProfileColumns();
        $checks = [];
        $nationalId = trim(to_english_digits($data['national_id'] ?? ''));
        $mobile = self::normalizePhoneValue($data['mobile'] ?? '');
        $secondaryPhone = self::normalizePhoneValue($data['secondary_phone'] ?? '');
        $email = trim((string) ($data['email'] ?? ''));

        if ($nationalId !== '') {
            $checks[] = ['national_id = ?', $nationalId];
        }
        if ($mobile !== '') {
            $checks[] = ['mobile = ?', $mobile];
            $checks[] = ['secondary_phone = ?', $mobile];
        }
        if ($secondaryPhone !== '') {
            $checks[] = ['mobile = ?', $secondaryPhone];
            $checks[] = ['secondary_phone = ?', $secondaryPhone];
        }
        if ($email !== '') {
            $checks[] = ['email = ?', $email];
        }
        if (!$checks) {
            return null;
        }

        $where = [];
        $params = [];
        foreach ($checks as $check) {
            $where[] = $check[0];
            $params[] = $check[1];
        }
        return self::fetch(
            "SELECT *
             FROM users
             WHERE role = 'customer'
             AND (" . implode(' OR ', $where) . ")
             ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, id DESC
             LIMIT 1",
            $params
        );
    }

    public static function updateUser($id, array $data)
    {
        self::ensureProfileColumns();
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $data = self::prepareUserData($data, $user);
        self::assertUniqueIdentity($data, (int) $id);
        $avatarKey = avatar_key_for($data['avatar_key'] ?? null, (string) $id . '|' . ($data['full_name'] ?? ($user['full_name'] ?? '')));
        $avatarExplicit = !empty($data['avatar_key']) && in_array((string) $data['avatar_key'], avatar_options(), true);
        $avatarSuggestion = avatar_suggestion_for($data['full_name'] ?? ($user['full_name'] ?? ''), $data['role'] ?? ($user['role'] ?? ''));
        $params = [
            'id' => $id,
            'role' => $data['role'] ?? $user['role'],
            'username' => trim(to_english_digits($data['username'] ?? $user['username'] ?? '')) ?: null,
            'full_name' => $data['full_name'] ?? $user['full_name'],
            'father_name' => $data['father_name'] ?? ($user['father_name'] ?? null),
            'issued_from' => $data['issued_from'] ?? ($user['issued_from'] ?? null),
            'national_id' => trim(to_english_digits($data['national_id'] ?? $user['national_id'])) ?: null,
            'mobile' => self::normalizePhoneValue($data['mobile'] ?? $user['mobile']) ?: null,
            'secondary_phone' => self::normalizePhoneValue($data['secondary_phone'] ?? $user['secondary_phone']) ?: null,
            'email' => ($data['email'] ?? $user['email']) ?: null,
            'status' => $data['status'] ?? $user['status'],
            'address' => $data['address'] ?? ($user['address'] ?? ''),
            'avatar_key' => $avatarExplicit ? $avatarKey : ($user['avatar_key'] ?? $avatarSuggestion['key']),
            'avatar_category' => $avatarExplicit ? ($user['avatar_category'] ?? 'manual') : $avatarSuggestion['category'],
            'avatar_source' => $avatarExplicit ? 'manual' : ($user['avatar_source'] ?? $avatarSuggestion['source']),
            'avatar_locked' => $avatarExplicit ? 1 : (int) ($user['avatar_locked'] ?? 0),
            'avatar_suggestion_reason' => $avatarExplicit ? 'انتخاب دستی کاربر.' : ($user['avatar_suggestion_reason'] ?? $avatarSuggestion['reason']),
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
             avatar_category = :avatar_category, avatar_source = :avatar_source, avatar_locked = :avatar_locked,
             avatar_suggestion_reason = :avatar_suggestion_reason,
             department = :department, is_department_manager = :is_department_manager {$passwordSql} WHERE id = :id",
            $params
        );
        if (class_exists('PluginManager')) {
            PluginManager::fire('user.updated', ['user_id' => (int) $id, 'role' => $params['role'] ?? '', 'actor_user_id' => Auth::id() ?: null], true);
        }
        return true;
    }

    protected static function prepareUserData(array $data, array $existing = null)
    {
        $role = $data['role'] ?? ($existing['role'] ?? '');
        $nationalId = trim(to_english_digits($data['national_id'] ?? ($existing['national_id'] ?? '')));
        $mobile = self::normalizePhoneValue($data['mobile'] ?? ($existing['mobile'] ?? ''));
        $username = trim(to_english_digits($data['username'] ?? ($existing['username'] ?? '')));

        if ($role === 'customer') {
            if ($username === '' && $nationalId !== '') {
                $data['username'] = $nationalId;
            }
            if ($existing === null && trim((string) ($data['password'] ?? '')) === '' && strlen($mobile) >= 4) {
                $data['password'] = substr($mobile, -4);
            }
        }

        if (array_key_exists('mobile', $data)) {
            $data['mobile'] = $mobile;
        }
        if (array_key_exists('secondary_phone', $data)) {
            $data['secondary_phone'] = self::normalizePhoneValue($data['secondary_phone'] ?? '');
        }
        if (array_key_exists('national_id', $data)) {
            $data['national_id'] = $nationalId;
        }
        return $data;
    }

    protected static function normalizePhoneValue($value)
    {
        $digits = preg_replace('/\D+/', '', to_english_digits((string) $value));
        if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    public static function syncCustomerLoginDefaults($id, array $user = null, $resetDefaultPassword = false)
    {
        self::ensureProfileColumns();
        $user = $user ?: self::find((int) $id);
        if (!$user || ($user['role'] ?? '') !== 'customer') {
            return false;
        }

        $updates = [];
        $params = [];
        $nationalId = trim(to_english_digits($user['national_id'] ?? ''));
        $mobile = self::normalizePhoneValue($user['mobile'] ?? '');
        if (trim((string) ($user['username'] ?? '')) === '' && $nationalId !== '') {
            $duplicate = self::fetch('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1', [$nationalId, (int) $id]);
            if (!$duplicate) {
                $updates[] = 'username = ?';
                $params[] = $nationalId;
            }
        }
        if ($resetDefaultPassword && strlen($mobile) >= 4) {
            $defaultPassword = substr($mobile, -4);
            if (!password_verify($defaultPassword, $user['password_hash'] ?? '')) {
                $updates[] = 'password_hash = ?';
                $params[] = password_hash($defaultPassword, PASSWORD_DEFAULT);
            }
        }
        if (!$updates) {
            return true;
        }
        $updates[] = 'updated_at = NOW()';
        $params[] = (int) $id;
        try {
            self::execute('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected static function assertUniqueIdentity(array $data, $ignoreId = null)
    {
        $checks = [
            'username' => ['label' => 'نام کاربری', 'value' => trim((string) ($data['username'] ?? ''))],
            'national_id' => ['label' => 'کد ملی', 'value' => trim(to_english_digits($data['national_id'] ?? ''))],
            'mobile' => ['label' => 'موبایل', 'value' => self::normalizePhoneValue($data['mobile'] ?? '')],
            'email' => ['label' => 'ایمیل', 'value' => trim((string) ($data['email'] ?? ''))],
        ];
        foreach ($checks as $column => $check) {
            if ($check['value'] === '') {
                continue;
            }
            $sql = "SELECT id, full_name, role FROM users WHERE {$column} = ?";
            $params = [$check['value']];
            if ($ignoreId) {
                $sql .= ' AND id != ?';
                $params[] = (int) $ignoreId;
            }
            $sql .= ' LIMIT 1';
            $existing = self::fetch($sql, $params);
            if ($existing) {
                throw new InvalidArgumentException(
                    $check['label'] . ' واردشده قبلاً برای «' . ($existing['full_name'] ?? 'کاربر') . '» ثبت شده است.'
                );
            }
        }
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
        SchemaGuard::requireColumns('customer_merge_logs', ['keep_customer_id', 'merged_customer_id', 'merged_by', 'snapshot_json', 'created_at']);
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

    public static function updateAvatar($id, $avatarKey)
    {
        self::ensureProfileColumns();
        $avatarKey = trim((string) $avatarKey);
        if (!in_array($avatarKey, avatar_options(), true)) {
            throw new InvalidArgumentException('آواتار انتخاب‌شده معتبر نیست.');
        }
        $user = self::find((int) $id);
        if (!$user) {
            throw new InvalidArgumentException('حساب کاربری پیدا نشد.');
        }
        self::execute(
            "UPDATE users
             SET avatar_key = ?, avatar_category = 'manual', avatar_source = 'self_service', avatar_locked = 1,
                 avatar_suggestion_reason = ?, avatar_path = NULL, avatar_version = avatar_version + 1,
                 avatar_updated_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$avatarKey, 'انتخاب مستقیم صاحب حساب.', (int) $id]
        );
        self::archivePreviousAvatar($user['avatar_path'] ?? null);
        if (class_exists('AuditLog')) {
            try {
                AuditLog::record('profile', 'avatar_updated', 'user', (int) $id, [
                    'actor_user_id' => (int) $id,
                    'severity' => 'low',
                    'old_values' => ['avatar_key' => $user['avatar_key'] ?? null],
                    'new_values' => ['avatar_key' => $avatarKey],
                    'description' => 'آواتار توسط صاحب حساب تغییر کرد.',
                ]);
            } catch (Throwable $ignored) {
            }
        }
        return true;
    }

    public static function updateUploadedAvatar($id, $relativePath)
    {
        self::ensureProfileColumns();
        $user = self::find((int) $id);
        $relativePath = trim((string) $relativePath);
        if (!$user) {
            throw new InvalidArgumentException('حساب کاربری پیدا نشد.');
        }
        if (strpos($relativePath, 'storage/secure_uploads/avatars/' . (int) $id . '/') !== 0 || !UploadHelper::absolutePath($relativePath)) {
            throw new InvalidArgumentException('مسیر آواتار معتبر نیست.');
        }
        self::execute(
            "UPDATE users SET avatar_path = ?, avatar_source = 'upload', avatar_locked = 1,
             avatar_suggestion_reason = ?, avatar_version = avatar_version + 1,
             avatar_updated_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$relativePath, 'بارگذاری مستقیم توسط صاحب حساب.', (int) $id]
        );
        self::archivePreviousAvatar($user['avatar_path'] ?? null, $relativePath);
        self::auditAvatarChange((int) $id, $user, ['avatar_path' => $relativePath, 'avatar_source' => 'upload'], 'avatar_uploaded');
        return true;
    }

    public static function removeUploadedAvatar($id)
    {
        self::ensureProfileColumns();
        $user = self::find((int) $id);
        if (!$user) {
            throw new InvalidArgumentException('حساب کاربری پیدا نشد.');
        }
        self::execute(
            "UPDATE users SET avatar_path = NULL, avatar_source = 'self_service',
             avatar_version = avatar_version + 1, avatar_updated_at = NOW(), updated_at = NOW() WHERE id = ?",
            [(int) $id]
        );
        self::archivePreviousAvatar($user['avatar_path'] ?? null);
        self::auditAvatarChange((int) $id, $user, ['avatar_path' => null, 'avatar_key' => $user['avatar_key'] ?? null], 'avatar_removed');
        return true;
    }

    protected static function archivePreviousAvatar($oldPath, $newPath = null)
    {
        $oldPath = trim((string) $oldPath);
        if ($oldPath === '' || $oldPath === (string) $newPath) {
            return;
        }
        try {
            UploadHelper::deleteRelative($oldPath);
        } catch (Throwable $e) {
            ErrorHandler::log('avatar_archive_previous', $e, 500);
        }
    }

    protected static function auditAvatarChange($userId, array $oldUser, array $newValues, $action)
    {
        if (!class_exists('AuditLog')) {
            return;
        }
        try {
            AuditLog::record('profile', $action, 'user', (int) $userId, [
                'actor_user_id' => (int) $userId,
                'severity' => 'low',
                'old_values' => ['avatar_key' => $oldUser['avatar_key'] ?? null, 'avatar_path' => $oldUser['avatar_path'] ?? null],
                'new_values' => $newValues,
                'description' => 'آواتار توسط صاحب حساب تغییر کرد.',
            ]);
        } catch (Throwable $ignored) {
        }
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
