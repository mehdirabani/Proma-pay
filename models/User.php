<?php

class User extends Model
{
    protected static $profileColumnsReady = false;

    public static function ensureProfileColumns()
    {
        if (self::$profileColumnsReady) {
            return;
        }
        foreach ([
            'address' => 'TEXT NULL',
            'avatar_key' => "VARCHAR(40) NULL",
            'department' => "VARCHAR(40) NULL",
            'is_department_manager' => "TINYINT(1) NOT NULL DEFAULT 0",
            'tour_completed_at' => 'DATETIME NULL',
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

    public static function all($role = null, $search = null, $status = null)
    {
        $params = [];
        $where = [];
        if ($role) {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        if ($status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search) {
            $needle = '%' . to_english_digits($search) . '%';
            $where[] = '(full_name LIKE ? OR national_id LIKE ? OR mobile LIKE ? OR secondary_phone LIKE ? OR username LIKE ? OR email LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle, $needle);
        }
        $sql = 'SELECT * FROM users' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC';
        return self::fetchAll($sql, $params);
    }

    public static function customers($search = null, $status = null)
    {
        return self::all('customer', $search, $status);
    }

    public static function customerSummaries($search = null, $status = null)
    {
        Payment::ensureCorrectionSchema();
        $params = [];
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
             ORDER BY u.id DESC",
            $params
        );
    }

    public static function medalsForUsers(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = self::fetchAll("SELECT * FROM medals WHERE user_id IN ({$placeholders}) ORDER BY id DESC", $userIds);
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
            'INSERT INTO medals (user_id, title, description, points, code, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userId, trim($title), trim($description), (int) to_english_digits($points), null]
        );
    }

    public static function addMedalIfMissing($userId, $code, $title, $description = '', $points = 0)
    {
        self::ensureMedalSchema();
        $exists = self::fetch('SELECT id FROM medals WHERE user_id = ? AND code = ? LIMIT 1', [(int) $userId, $code]);
        if ($exists) {
            return false;
        }
        self::execute(
            'INSERT INTO medals (user_id, title, description, points, code, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userId, trim($title), trim($description), (int) to_english_digits($points), $code]
        );
        return true;
    }

    public static function ensureMedalSchema()
    {
        try {
            self::execute('ALTER TABLE medals ADD COLUMN code VARCHAR(80) NULL');
        } catch (Throwable $e) {
        }
        try {
            self::execute('CREATE UNIQUE INDEX uq_medals_user_code ON medals (user_id, code)');
        } catch (Throwable $e) {
        }
    }

    public static function deleteMedal($id)
    {
        self::execute('DELETE FROM medals WHERE id = ?', [(int) $id]);
    }

    public static function create(array $data)
    {
        self::ensureProfileColumns();
        self::execute(
            'INSERT INTO users (role, username, full_name, national_id, mobile, secondary_phone, email, password_hash, status, department, is_department_manager, created_at)
             VALUES (:role, :username, :full_name, :national_id, :mobile, :secondary_phone, :email, :password_hash, :status, :department, :is_department_manager, NOW())',
            [
                'role' => $data['role'],
                'username' => $data['username'] ?: null,
                'full_name' => $data['full_name'],
                'national_id' => trim(to_english_digits($data['national_id'] ?? '')) ?: null,
                'mobile' => trim(to_english_digits($data['mobile'] ?? '')) ?: null,
                'secondary_phone' => trim(to_english_digits($data['secondary_phone'] ?? '')) ?: null,
                'email' => $data['email'] ?: null,
                'password_hash' => password_hash($data['password'] ?: bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                'status' => $data['status'] ?? 'active',
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
            'national_id' => trim(to_english_digits($data['national_id'] ?? $user['national_id'])) ?: null,
            'mobile' => trim(to_english_digits($data['mobile'] ?? $user['mobile'])) ?: null,
            'secondary_phone' => trim(to_english_digits($data['secondary_phone'] ?? $user['secondary_phone'])) ?: null,
            'email' => ($data['email'] ?? $user['email']) ?: null,
            'status' => $data['status'] ?? $user['status'],
            'address' => $data['address'] ?? ($user['address'] ?? ''),
            'avatar_key' => $data['avatar_key'] ?? ($user['avatar_key'] ?? null),
            'department' => $data['department'] ?? ($user['department'] ?? null),
            'is_department_manager' => !empty($data['is_department_manager']) ? 1 : 0,
        ];
        $passwordSql = '';
        if (!empty($data['password'])) {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        self::execute(
            "UPDATE users SET role = :role, username = :username, full_name = :full_name,
             national_id = :national_id, mobile = :mobile, secondary_phone = :secondary_phone,
             email = :email, status = :status, address = :address, avatar_key = :avatar_key,
             department = :department, is_department_manager = :is_department_manager {$passwordSql} WHERE id = :id",
            $params
        );
        return true;
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
        self::ensureProfileColumns();
        return self::fetchAll("SELECT id, full_name, role FROM users WHERE role IN ('admin','operator','lawyer') AND status = 'active' ORDER BY role, full_name");
    }

    public static function departments()
    {
        return app_config('departments', []);
    }

    public static function markTourCompleted($id)
    {
        self::ensureProfileColumns();
        self::execute('UPDATE users SET tour_completed_at = NOW() WHERE id = ?', [(int) $id]);
    }
}
