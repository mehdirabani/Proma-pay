<?php

class ProfileRequest extends Model
{
    public static function statusLabel($status)
    {
        return [
            'pending' => 'در انتظار بررسی',
            'approved' => 'تأیید کامل',
            'partial' => 'تأیید جزئی',
            'rejected' => 'رد شده',
        ][$status] ?? 'نامشخص';
    }

    public static function fieldLabels()
    {
        return [
            'full_name' => 'نام و نام خانوادگی',
            'mobile' => 'شماره موبایل',
            'secondary_phone' => 'تلفن دوم',
            'email' => 'ایمیل',
            'address' => 'آدرس',
        ];
    }

    public static function ensureSchema()
    {
        User::ensureProfileColumns();
        self::fetch('SELECT id FROM profile_update_requests LIMIT 1');
    }

    public static function createRequest($userId, array $payload)
    {
        self::ensureSchema();
        $user = User::find((int) $userId);
        if (!$user) {
            throw new InvalidArgumentException('حساب کاربری پیدا نشد.');
        }
        $changes = [];
        foreach (self::fieldLabels() as $field => $label) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$field]);
            if ($field === 'mobile' || $field === 'secondary_phone') {
                $value = preg_replace('/\D+/', '', to_english_digits($value));
            }
            if ($value !== trim((string) ($user[$field] ?? ''))) {
                $changes[$field] = $value;
            }
        }
        if (!$changes) {
            return false;
        }
        $encoded = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = [];
        foreach (array_keys($changes) as $field) {
            $snapshot[$field] = trim((string) ($user[$field] ?? ''));
        }
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pending = self::fetch("SELECT id FROM profile_update_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [(int) $userId]);
        if ($pending) {
            self::execute(
                'UPDATE profile_update_requests SET payload_json = ?, current_snapshot_json = ?, reviewed_by = NULL, review_notes = NULL, reviewed_fields_json = NULL, rejected_fields_json = NULL, customer_response = NULL, created_at = NOW(), updated_at = NOW() WHERE id = ?',
                [$encoded, $snapshotJson, (int) $pending['id']]
            );
            return (int) $pending['id'];
        }
        self::execute(
            'INSERT INTO profile_update_requests (user_id, payload_json, current_snapshot_json, status, created_at) VALUES (?, ?, ?, ?, NOW())',
            [(int) $userId, $encoded, $snapshotJson, 'pending']
        );
        return (int) self::lastInsertId();
    }

    public static function pending()
    {
        return self::all(['status' => 'pending']);
    }

    public static function all(array $filters = [])
    {
        $filters['page'] = 1;
        $filters['per_page'] = min(200, max(1, (int) ($filters['per_page'] ?? 200)));
        return self::paginated($filters)['items'];
    }

    public static function paginated(array $filters = [])
    {
        self::ensureSchema();
        $status = trim((string) ($filters['status'] ?? 'pending'));
        $params = [];
        $where = [];
        if (in_array($status, ['pending', 'approved', 'rejected', 'partial'], true)) {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['role'])) {
            $where[] = 'u.role = ?';
            $params[] = trim((string) $filters['role']);
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'pr.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['q'])) {
            $needle = '%' . trim(to_english_digits((string) $filters['q'])) . '%';
            $where[] = '(u.full_name LIKE ? OR u.mobile LIKE ? OR u.national_id LIKE ? OR u.username LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) (self::fetch(
            "SELECT COUNT(*) AS total
             FROM profile_update_requests pr
             JOIN users u ON u.id = pr.user_id{$whereSql}",
            $params
        )['total'] ?? 0);
        $perPage = max(12, min(200, (int) ($filters['per_page'] ?? 24)));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll(
            "SELECT pr.*, u.full_name, u.role, u.mobile, u.national_id, u.username,
                    reviewer.full_name AS reviewer_name,
                    u.full_name AS current_full_name, u.mobile AS current_mobile,
                    u.secondary_phone AS current_secondary_phone, u.email AS current_email,
                    u.address AS current_address
             FROM profile_update_requests pr
             JOIN users u ON u.id = pr.user_id
             LEFT JOIN users reviewer ON reviewer.id = pr.reviewed_by
             {$whereSql}
             ORDER BY CASE WHEN pr.status = 'pending' THEN 0 ELSE 1 END, pr.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'items' => self::decorateRows($rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public static function summary()
    {
        self::ensureSchema();
        $row = self::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS partial,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
             FROM profile_update_requests"
        ) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'partial' => (int) ($row['partial'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ];
    }

    public static function historiesForUsers(array $userIds)
    {
        self::ensureSchema();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        $userIds = array_slice($userIds, 0, 100);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = self::fetchAll(
            "SELECT pr.*, reviewer.full_name AS reviewer_name
             FROM profile_update_requests pr
             LEFT JOIN users reviewer ON reviewer.id = pr.reviewed_by
             WHERE pr.user_id IN ({$placeholders})
             ORDER BY pr.user_id, pr.id DESC
             LIMIT 1000",
            $userIds
        );
        $grouped = [];
        foreach (self::decorateRows($rows) as $row) {
            $grouped[(int) $row['user_id']][] = $row;
        }
        return $grouped;
    }

    protected static function decorateRows(array $rows)
    {
        foreach ($rows as &$row) {
            $row['requested_fields'] = json_decode((string) ($row['payload_json'] ?? ''), true) ?: [];
            $row['reviewed_fields'] = json_decode((string) ($row['reviewed_fields_json'] ?? ''), true) ?: [];
            $row['rejected_fields'] = json_decode((string) ($row['rejected_fields_json'] ?? ''), true) ?: [];
            $snapshot = json_decode((string) ($row['current_snapshot_json'] ?? ''), true) ?: [];
            $row['snapshot_fields'] = $snapshot;
            $row['conflict_fields'] = [];
            foreach ($row['requested_fields'] as $field => $value) {
                if (array_key_exists($field, $snapshot) && array_key_exists('current_' . $field, $row)
                    && trim((string) ($row['current_' . $field] ?? '')) !== trim((string) $snapshot[$field])) {
                    $row['conflict_fields'][] = $field;
                }
            }
        }
        unset($row);
        return $rows;
    }

    public static function countByStatus($status = 'pending')
    {
        self::ensureSchema();
        $row = self::fetch('SELECT COUNT(*) AS total FROM profile_update_requests WHERE status = ?', [(string) $status]);
        return (int) ($row['total'] ?? 0);
    }

    public static function latestForUser($userId)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM profile_update_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $userId]);
    }

    public static function approve($id, $reviewerId)
    {
        return self::approveFields($id, $reviewerId, [], '');
    }

    public static function approveFields($id, $reviewerId, array $approvedFields = [], $notes = '')
    {
        self::ensureSchema();
        $request = self::fetch("SELECT * FROM profile_update_requests WHERE id = ? AND status = 'pending'", [(int) $id]);
        if (!$request) {
            return false;
        }
        $payload = json_decode($request['payload_json'], true) ?: [];
        $payload = array_intersect_key($payload, self::fieldLabels());
        if (!$approvedFields) {
            $approvedFields = array_keys($payload);
        }
        $approvedFields = array_values(array_intersect(array_keys($payload), array_map('strval', $approvedFields)));
        $user = User::find((int) $request['user_id']);
        $snapshot = json_decode((string) ($request['current_snapshot_json'] ?? ''), true) ?: [];
        $conflicts = [];
        foreach ($approvedFields as $field) {
            if (array_key_exists($field, $snapshot) && trim((string) ($user[$field] ?? '')) !== trim((string) $snapshot[$field])) {
                $conflicts[] = $field;
            }
        }
        $approvedFields = array_values(array_diff($approvedFields, $conflicts));
        $approvedPayload = array_intersect_key($payload, array_flip($approvedFields));
        $rejectedFields = array_values(array_diff(array_keys($payload), $approvedFields));
        $status = !$approvedFields ? 'rejected' : ($rejectedFields ? 'partial' : 'approved');
        self::begin();
        try {
            if ($approvedPayload) {
                User::applyProfileData((int) $request['user_id'], $approvedPayload);
            }
            self::execute(
                'UPDATE profile_update_requests SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_fields_json = ?, rejected_fields_json = ?, updated_at = NOW() WHERE id = ?',
                [$status, (int) $reviewerId, trim((string) $notes) ?: null, json_encode($approvedFields, JSON_UNESCAPED_UNICODE), json_encode($rejectedFields, JSON_UNESCAPED_UNICODE), (int) $id]
            );
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        try {
            $message = $status === 'approved' ? 'درخواست اصلاح مشخصات شما تایید و روی حساب اعمال شد.' : ($status === 'partial' ? 'بخشی از اصلاحات مشخصات شما تایید و اعمال شد.' : 'به دلیل تعارض یا رد فیلدها، اصلاحی روی حساب اعمال نشد.');
            Notification::create((int) $request['user_id'], 'نتیجه بررسی مشخصات', $message, 'profile', url('profile'), 'profile-review:' . (int) $id);
        } catch (Throwable $ignored) {
        }
        return ['ok' => true, 'status' => $status, 'approved_fields' => $approvedFields, 'rejected_fields' => $rejectedFields, 'conflict_fields' => $conflicts];
    }

    public static function reject($id, $reviewerId, $notes = '')
    {
        $result = self::approveFields((int) $id, (int) $reviewerId, ['__none__'], $notes);
        return !empty($result['ok']);
    }

    public static function respond($id, $userId, $response)
    {
        $response = trim((string) $response);
        if ($response === '') {
            throw new InvalidArgumentException('متن پاسخ را وارد کنید.');
        }
        return self::execute(
            'UPDATE profile_update_requests SET customer_response = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND status IN (?, ?)',
            [$response, (int) $id, (int) $userId, 'partial', 'rejected']
        );
    }
}
