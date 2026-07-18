<?php

class Event extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        self::execute(
            "CREATE TABLE IF NOT EXISTS events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                assigned_user_id BIGINT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                event_date DATE NOT NULL,
                event_time TIME NULL,
                event_type VARCHAR(40) NOT NULL DEFAULT 'general',
                description TEXT NULL,
                color VARCHAR(20) NOT NULL DEFAULT 'primary',
                reminder_type VARCHAR(40) NULL,
                reminder_at DATETIME NULL,
                reminder_sent_at DATETIME NULL,
                due_day_sent_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_events_date (event_date),
                KEY idx_events_assigned (assigned_user_id),
                KEY idx_events_reminder (reminder_at, reminder_sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $columns = [
            'assigned_user_id' => 'BIGINT UNSIGNED NULL AFTER user_id',
            'event_time' => 'TIME NULL AFTER event_date',
            'event_type' => "VARCHAR(40) NOT NULL DEFAULT 'general' AFTER event_time",
            'reminder_type' => 'VARCHAR(40) NULL AFTER color',
            'reminder_at' => 'DATETIME NULL AFTER reminder_type',
            'reminder_sent_at' => 'DATETIME NULL AFTER reminder_at',
            'due_day_sent_at' => 'DATETIME NULL AFTER reminder_sent_at',
        ];
        foreach ($columns as $column => $definition) {
            try {
                self::execute("ALTER TABLE events ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }
        try {
            self::execute('ALTER TABLE events ADD INDEX idx_events_assigned (assigned_user_id)');
        } catch (Throwable $e) {
        }
        try {
            self::execute('ALTER TABLE events ADD INDEX idx_events_reminder (reminder_at, reminder_sent_at)');
        } catch (Throwable $e) {
        }
        try {
            self::execute('UPDATE events SET assigned_user_id = user_id WHERE assigned_user_id IS NULL AND user_id IS NOT NULL');
        } catch (Throwable $e) {
        }
        self::$schemaReady = true;
    }

    public static function allForMonth($month)
    {
        self::ensureSchema();
        $start = date('Y-m-01', strtotime($month . '-01'));
        $end = date('Y-m-01', strtotime($start . ' +1 month'));
        return self::fetchAll(
            'SELECT e.*, u.full_name AS user_name, u.role AS user_role
             FROM events e
             LEFT JOIN users u ON u.id = COALESCE(e.assigned_user_id, e.user_id)
             WHERE e.event_date >= ? AND e.event_date < ?
             ORDER BY e.event_date ASC, COALESCE(e.event_time, "23:59:59") ASC, e.id ASC',
            [$start, $end]
        );
    }

    public static function allBetween($startDate, $endDate)
    {
        self::ensureSchema();
        return self::fetchAll(
            'SELECT e.*, u.full_name AS user_name, u.role AS user_role
             FROM events e
             LEFT JOIN users u ON u.id = COALESCE(e.assigned_user_id, e.user_id)
             WHERE e.event_date >= ? AND e.event_date < ?
             ORDER BY e.event_date ASC, COALESCE(e.event_time, "23:59:59") ASC, e.id ASC',
            [$startDate, $endDate]
        );
    }

    public static function allVisibleBetween($startDate, $endDate, array $viewer)
    {
        self::ensureSchema();
        $role = (string) ($viewer['role'] ?? '');
        $viewerId = (int) ($viewer['id'] ?? 0);
        $select = 'SELECT e.*, u.full_name AS user_name, u.role AS user_role
                   FROM events e
                   LEFT JOIN users u ON u.id = COALESCE(e.assigned_user_id, e.user_id)';
        $order = ' ORDER BY e.event_date ASC, COALESCE(e.event_time, "23:59:59") ASC, e.id ASC';
        if ($role === 'admin') {
            return self::fetchAll(
                $select . " WHERE e.event_date >= ? AND e.event_date < ? AND e.event_type != 'installment'" . $order,
                [$startDate, $endDate]
            );
        }
        if ($role === 'customer') {
            return self::fetchAll(
                $select . " WHERE e.event_date >= ? AND e.event_date < ?
                 AND e.event_type != 'installment'
                 AND COALESCE(e.assigned_user_id, e.user_id) = ?" . $order,
                [$startDate, $endDate, $viewerId]
            );
        }
        return self::fetchAll(
            $select . " WHERE e.event_date >= ? AND e.event_date < ?
             AND e.event_type != 'installment'
             AND (e.assigned_user_id = ? OR e.user_id = ?)" . $order,
            [$startDate, $endDate, $viewerId, $viewerId]
        );
    }

    public static function installmentCalendarEvents($startDate, $endDate, $customerId = null)
    {
        $params = [$startDate, $endDate];
        $where = "c.status != 'cancelled' AND i.status != 'cancelled' AND i.due_date >= ? AND i.due_date < ?";
        if ($customerId) {
            $where .= ' AND c.customer_id = ?';
            $params[] = (int) $customerId;
        }
        $rows = self::fetchAll(
            "SELECT i.id AS installment_id, i.installment_number, i.due_date, i.status,
             c.id AS contract_id, c.contract_number, c.customer_id, u.full_name AS user_name, u.role AS user_role
             FROM installments i
             JOIN contracts c ON c.id = i.contract_id
             JOIN users u ON u.id = c.customer_id
             WHERE {$where}
             ORDER BY i.due_date ASC, i.installment_number ASC",
            $params
        );
        return array_map(function ($row) {
            $status = $row['status'] ?? 'pending';
            $color = $status === 'paid' ? 'success' : (($status === 'overdue' || ($row['due_date'] ?? '') < date('Y-m-d')) ? 'danger' : 'warning');
            return [
                'id' => 0,
                'system_key' => 'installment-' . (int) $row['installment_id'],
                'installment_id' => (int) $row['installment_id'],
                'contract_id' => (int) $row['contract_id'],
                'assigned_user_id' => (int) $row['customer_id'],
                'title' => 'قسط ' . to_persian_digits($row['installment_number']) . ' قرارداد ' . $row['contract_number'],
                'event_date' => $row['due_date'],
                'event_time' => null,
                'event_type' => 'installment',
                'description' => '',
                'color' => $color,
                'user_name' => $row['user_name'] ?? '',
                'user_role' => $row['user_role'] ?? 'customer',
                'installment_status' => $status,
                'is_system' => true,
            ];
        }, $rows);
    }

    public static function createEvent(array $data)
    {
        self::ensureSchema();
        $settings = Settings::allKeyed();
        $eventTime = normalize_time($data['event_time'] ?? '') ?: null;
        $assignedUserId = !empty($data['assigned_user_id'])
            ? (int) $data['assigned_user_id']
            : (!empty($data['user_id']) ? (int) $data['user_id'] : null);
        $reminderType = self::normalizeReminderType($data['reminder_type'] ?? '', $settings['calendar_default_reminder_type'] ?? '1_day');
        $reminderAt = self::calculateReminderAt(
            $data['event_date'],
            $eventTime,
            $reminderType,
            $data['custom_reminder_date'] ?? null,
            $data['custom_reminder_time'] ?? null
        );
        self::execute(
            'INSERT INTO events
             (user_id, assigned_user_id, title, event_date, event_time, event_type, description, color, reminder_type, reminder_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $assignedUserId,
                $assignedUserId,
                trim($data['title']),
                $data['event_date'],
                $eventTime,
                self::normalizeEventType($data['event_type'] ?? 'general'),
                trim($data['description'] ?? ''),
                in_array($data['color'] ?? '', ['primary', 'success', 'warning', 'danger', 'info'], true) ? $data['color'] : 'primary',
                $reminderType,
                $reminderAt,
            ]
        );
    }

    public static function deleteEvent($id)
    {
        self::ensureSchema();
        self::execute('DELETE FROM events WHERE id = ?', [(int) $id]);
    }

    public static function syncLegalCaseEvents($caseId, $lawyerId, array $dates, $contractNumber = '', $customerName = '')
    {
        self::ensureSchema();
        $caseId = (int) $caseId;
        if ($caseId <= 0) {
            return;
        }
        self::execute('DELETE FROM events WHERE description LIKE ?', ['legal_case:' . $caseId . ':%']);
        $recipients = [];
        if ($lawyerId) {
            $recipients[] = (int) $lawyerId;
        }
        $case = LegalCase::find($caseId);
        if ($case && !empty($case['customer_id'])) {
            $recipients[] = (int) $case['customer_id'];
        }
        foreach (User::all('admin', null, 'active') as $admin) {
            $recipients[] = (int) $admin['id'];
        }
        $recipients = array_values(array_unique(array_filter($recipients)));
        $labels = [
            'notice_date' => 'ابلاغ پرونده حقوقی',
            'court_date' => 'دادگاه پرونده حقوقی',
            'hearing_date' => 'جلسه رسیدگی پرونده حقوقی',
        ];
        foreach ($labels as $field => $label) {
            if (empty($dates[$field]) || !$recipients) {
                continue;
            }
            foreach ($recipients as $recipientId) {
                self::createEvent([
                    'assigned_user_id' => $recipientId,
                    'title' => $label . ($contractNumber ? ' ' . $contractNumber : ''),
                    'event_date' => $dates[$field],
                    'event_time' => null,
                    'event_type' => 'legal',
                    'description' => 'legal_case:' . $caseId . ':' . $field . "\n" . trim($customerName),
                    'color' => $field === 'court_date' ? 'danger' : 'info',
                    'reminder_type' => '1_day',
                ]);
            }
        }
    }

    public static function processDueReminders($now = null)
    {
        self::ensureSchema();
        $settings = Settings::allKeyed();
        $result = [
            'enabled' => ($settings['calendar_notifications_enabled'] ?? '1') === '1',
            'reminder_sent' => 0,
            'due_day_sent' => 0,
            'skipped_no_recipient' => 0,
        ];
        if (!$result['enabled']) {
            return $result;
        }

        $now = $now ?: date('Y-m-d H:i:s');
        $today = substr($now, 0, 10);

        $reminders = self::fetchAll(
            'SELECT * FROM events
             WHERE reminder_at IS NOT NULL AND reminder_sent_at IS NULL AND reminder_at <= ?
             ORDER BY reminder_at ASC, id ASC
             LIMIT 200',
            [$now]
        );
        foreach ($reminders as $event) {
            $sent = self::sendCalendarNotification($event, 'reminder', $settings);
            if ($sent > 0) {
                self::execute('UPDATE events SET reminder_sent_at = NOW() WHERE id = ? AND reminder_sent_at IS NULL', [(int) $event['id']]);
                $result['reminder_sent'] += $sent;
            } else {
                $result['skipped_no_recipient']++;
            }
        }

        if (($settings['calendar_due_day_repeat_enabled'] ?? '1') === '1') {
            $dueDayEvents = self::fetchAll(
                'SELECT * FROM events
                 WHERE event_date = ? AND due_day_sent_at IS NULL
                 ORDER BY COALESCE(event_time, "23:59:59") ASC, id ASC
                 LIMIT 200',
                [$today]
            );
            foreach ($dueDayEvents as $event) {
                $sent = self::sendCalendarNotification($event, 'due_day', $settings);
                if ($sent > 0) {
                    self::execute('UPDATE events SET due_day_sent_at = NOW() WHERE id = ? AND due_day_sent_at IS NULL', [(int) $event['id']]);
                    $result['due_day_sent'] += $sent;
                } else {
                    $result['skipped_no_recipient']++;
                }
            }
        }

        return $result;
    }

    public static function notificationStatus(array $event)
    {
        if (!empty($event['due_day_sent_at'])) {
            return ['label' => 'اعلان روز موعد ارسال شده', 'class' => 'success'];
        }
        if (!empty($event['reminder_sent_at'])) {
            return ['label' => 'اعلان یادآوری ارسال شده', 'class' => 'info'];
        }
        return ['label' => 'اعلان ارسال نشده', 'class' => 'warning'];
    }

    public static function reminderOptions()
    {
        return [
            '' => 'پیش‌فرض تنظیمات',
            'same_day' => 'همان روز',
            '1_hour' => '۱ ساعت قبل',
            '3_hours' => '۳ ساعت قبل',
            '1_day' => '۱ روز قبل',
            '3_days' => '۳ روز قبل',
            '7_days' => '۷ روز قبل',
            'custom' => 'زمان دلخواه',
        ];
    }

    public static function reminderLabel($type)
    {
        $options = self::reminderOptions();
        return $options[$type ?: ''] ?? 'پیش‌فرض تنظیمات';
    }

    public static function eventTypeOptions()
    {
        return [
            'general' => 'عمومی',
            'meeting' => 'جلسه',
            'followup' => 'پیگیری',
            'installment' => 'اقساط',
            'payment' => 'پرداخت',
            'legal' => 'حقوقی',
            'other' => 'سایر',
        ];
    }

    public static function eventTypeLabel($type)
    {
        $options = self::eventTypeOptions();
        return $options[$type ?: 'general'] ?? 'عمومی';
    }

    public static function displayTime($time)
    {
        if (!$time) {
            return 'بدون ساعت';
        }
        return to_persian_digits(substr((string) $time, 0, 5));
    }

    protected static function sendCalendarNotification(array $event, $kind, array $settings)
    {
        $recipients = self::notificationRecipients($event, $settings);
        if (!$recipients) {
            return 0;
        }
        $eventDate = jdate($event['event_date']);
        $eventTime = self::displayTime($event['event_time'] ?? null);
        $title = $kind === 'due_day' ? 'رویداد امروز تقویم' : 'یادآوری رویداد تقویم';
        $body = $kind === 'due_day'
            ? 'موعد رویداد «' . $event['title'] . '» امروز در تاریخ ' . $eventDate . ' ساعت ' . $eventTime . ' است.'
            : 'موعد رویداد «' . $event['title'] . '» در تاریخ ' . $eventDate . ' ساعت ' . $eventTime . ' نزدیک است.';
        $url = self::eventUrl($event);
        foreach ($recipients as $recipientId) {
            Notification::create((int) $recipientId, $title, $body, 'calendar', $url);
        }
        return count($recipients);
    }

    protected static function notificationRecipients(array $event, array $settings)
    {
        $assignedUserId = (int) ($event['assigned_user_id'] ?: ($event['user_id'] ?? 0));
        if ($assignedUserId > 0) {
            $user = self::fetch('SELECT id FROM users WHERE id = ? AND status = ? LIMIT 1', [$assignedUserId, 'active']);
            return $user ? [(int) $user['id']] : [];
        }
        if (($event['event_type'] ?? '') === 'legal') {
            return self::legalEventRecipients($event);
        }
        return [];
    }

    protected static function legalEventRecipients(array $event)
    {
        if (empty($event['description']) || !preg_match('/legal_case:(\d+):/', (string) $event['description'], $matches)) {
            return [];
        }
        $case = LegalCase::find((int) $matches[1]);
        if (!$case) {
            return [];
        }
        $recipients = [];
        if (!empty($case['customer_id'])) {
            $recipients[] = (int) $case['customer_id'];
        }
        if (!empty($case['lawyer_id'])) {
            $recipients[] = (int) $case['lawyer_id'];
        }
        foreach (User::all('admin', null, 'active') as $admin) {
            $recipients[] = (int) $admin['id'];
        }
        return array_values(array_unique(array_filter($recipients)));
    }

    protected static function eventUrl(array $event)
    {
        [$gy, $gm, $gd] = array_map('intval', explode('-', substr($event['event_date'], 0, 10)));
        [$jy, $jm] = gregorian_to_jalali($gy, $gm, $gd);
        return url('calendar', ['j_month' => sprintf('%04d/%02d', $jy, $jm)]) . '#event-' . (int) $event['id'];
    }

    protected static function calculateReminderAt($eventDate, $eventTime, $reminderType, $customReminderDate = null, $customReminderTime = null)
    {
        $eventDateTime = new DateTime($eventDate . ' ' . ($eventTime ?: '09:00:00'));
        if ($reminderType === 'custom') {
            $customDate = parse_jalali_date($customReminderDate ?? '');
            $customTime = normalize_time($customReminderTime ?? '') ?: '09:00';
            if (!$customDate) {
                throw new InvalidArgumentException('برای یادآوری دلخواه، تاریخ یادآوری معتبر وارد کنید.');
            }
            return $customDate . ' ' . $customTime . ':00';
        }
        if ($reminderType === 'same_day') {
            return $eventDate . ' 00:00:00';
        }
        $intervals = [
            '1_hour' => '-1 hour',
            '3_hours' => '-3 hours',
            '1_day' => '-1 day',
            '3_days' => '-3 days',
            '7_days' => '-7 days',
        ];
        if (!isset($intervals[$reminderType])) {
            $reminderType = '1_day';
        }
        return $eventDateTime->modify($intervals[$reminderType])->format('Y-m-d H:i:s');
    }

    protected static function normalizeReminderType($type, $defaultType = '1_day')
    {
        $type = trim((string) $type);
        if ($type === '') {
            $type = $defaultType ?: '1_day';
        }
        return array_key_exists($type, self::reminderOptions()) && $type !== '' ? $type : '1_day';
    }

    protected static function normalizeEventType($type)
    {
        $type = trim((string) $type);
        return array_key_exists($type, self::eventTypeOptions()) ? $type : 'general';
    }
}
