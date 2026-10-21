<?php

namespace Proma\Plugins\Repair\Services;

use Proma\Plugins\Repair\Domain\RepairWorkflow;
use Proma\Plugins\Repair\Repositories\RepairRepository;
use Proma\Plugins\Repair\Repositories\RepairSettingsRepository;
use Proma\Plugins\Repair\Support\PatternLock;
use Proma\Plugins\Repair\Support\RepairDate;

class RepairService
{
    private $repairs;
    private $settings;

    public function __construct(?RepairRepository $repairs = null, ?RepairSettingsRepository $settings = null)
    {
        $this->repairs = $repairs ?: new RepairRepository();
        $this->settings = $settings ?: new RepairSettingsRepository();
    }

    public function intake(array $input, int $actorId): array
    {
        $customerId = (int) (($input['customer']['customer_id'] ?? 0));
        $customer = $customerId > 0 ? \Model::fetch("SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1", [$customerId]) : null;
        if (!$customer) {
            throw new \InvalidArgumentException('یک مشتری فعال را از جستجوی مشتریان انتخاب کنید.');
        }
        $isDraft = (string) ($input['_intent'] ?? '') === 'draft';
        $deviceInput = (array) ($input['device'] ?? []);
        $deviceType = strtolower(trim((string) ($deviceInput['device_type'] ?? 'mobile')));
        $types = ['mobile', 'tablet', 'laptop', 'smartwatch', 'headphones', 'console', 'accessory', 'other'];
        if (!in_array($deviceType, $types, true)) {
            throw new \InvalidArgumentException('نوع دستگاه معتبر نیست.');
        }
        $passwordType = trim((string) ($deviceInput['password_type'] ?? ''));
        if (!in_array($passwordType, ['', 'pin', 'password', 'pattern'], true)) {
            throw new \InvalidArgumentException('نوع رمز دستگاه معتبر نیست.');
        }
        $password = trim((string) ($deviceInput['device_password'] ?? ''));
        if ($passwordType === 'pin') {
            $password = \to_english_digits($password);
            if ($password !== '' && !preg_match('/^\d{4,12}$/', $password)) {
                throw new \InvalidArgumentException('PIN باید بین ۴ تا ۱۲ رقم باشد.');
            }
        } elseif ($passwordType === 'pattern' && $password !== '') {
            $password = PatternLock::normalize($password);
        }
        if (mb_strlen($password, 'UTF-8') > 255) {
            throw new \InvalidArgumentException('رمز دستگاه نباید بیش از ۲۵۵ نویسه باشد.');
        }
        if ($password !== '' && $passwordType === '') {
            throw new \InvalidArgumentException('نوع رمز دستگاه را انتخاب کنید.');
        }
        $customerReport = $this->text($input['customer_report'] ?? '', 4000);
        if (!$isDraft && $customerReport === '') {
            throw new \InvalidArgumentException('شرح خرابی اعلام‌شده توسط مشتری الزامی است.');
        }
        $model = $this->text($deviceInput['model'] ?? '', 150);
        if (!$isDraft && $model === '') {
            throw new \InvalidArgumentException('مدل دستگاه را وارد کنید.');
        }
        $imei = $this->digitsOrText($deviceInput['imei'] ?? '', 40);
        if ($imei !== '' && !preg_match('/^[A-Za-z0-9-]{6,40}$/', $imei)) {
            throw new \InvalidArgumentException('شناسه IMEI معتبر نیست.');
        }
        $parentRepairId = max(0, (int) ($input['parent_repair_id'] ?? 0));
        if ($parentRepairId > 0 && !$this->repairs->warrantyReturnSource($parentRepairId, $customerId)) {
            throw new \InvalidArgumentException('پرونده اصلی گارانتی فعال یا متعلق به این مشتری نیست.');
        }
        $deliveryDateInput = trim((string) ($input['estimated_delivery_date'] ?? ''));
        $deliveryAt = $deliveryDateInput !== ''
            ? RepairDate::dateTime($deliveryDateInput, $input['estimated_delivery_time'] ?? '')
            : RepairDate::legacyDateTime($input['estimated_delivery_at'] ?? '');
        if ($deliveryDateInput !== '' && !$deliveryAt) {
            throw new \InvalidArgumentException('تاریخ تحویل تخمینی معتبر نیست.');
        }
        if ($deliveryAt && (strtotime($deliveryAt) === false || strtotime($deliveryAt) > strtotime('+3 years') || strtotime($deliveryAt) < strtotime('-1 day'))) {
            throw new \InvalidArgumentException('زمان تحویل تخمینی باید در بازهٔ مجاز باشد.');
        }
        $settings = $this->settings->all();
        $data = [
            'customer_id' => $customerId,
            'parent_repair_id' => $parentRepairId,
            'customer_report' => $customerReport,
            'status' => $isDraft ? 'draft' : 'received',
            'technician_user_id' => max(0, (int) ($input['technician_user_id'] ?? 0)),
            'intake_note' => $this->text($input['intake_note'] ?? '', 255),
            'estimated_delivery_at' => $deliveryAt,
            'default_warranty_days' => (int) $settings['default_warranty_days'],
            'device' => [
                'device_type' => $deviceType,
                'brand' => $this->text($deviceInput['brand'] ?? '', 100),
                'model' => $model,
                'color' => $this->text($deviceInput['color'] ?? '', 80),
                'imei' => $imei,
                'serial_number' => $this->text($deviceInput['serial_number'] ?? '', 100),
                'storage_capacity' => $this->text($deviceInput['storage_capacity'] ?? '', 40),
                'ram_capacity' => $this->text($deviceInput['ram_capacity'] ?? '', 40),
                'carrier' => $this->text($deviceInput['carrier'] ?? '', 80),
                'sim_number' => $this->digitsOrText($deviceInput['sim_number'] ?? '', 40),
                'accessories' => $this->tokens($deviceInput['accessories'] ?? [], ['sim', 'case', 'charger', 'cable', 'memory_card', 'box', 'other']),
                'condition_flags' => $this->tokens($deviceInput['condition_flags'] ?? [], ['screen_cracked', 'scratches', 'frame_cracked', 'back_cracked', 'water_damage', 'impact', 'opened_before', 'repaired_before', 'other']),
                'condition_notes' => $this->text($deviceInput['condition_notes'] ?? '', 2000),
                'password_type' => $passwordType,
                'device_password' => $password,
                'password_delete_after_days' => max(-1, min(3650, (int) $settings['password_retention_days'])),
            ],
        ];
        $repair = $this->repairs->create($data, $actorId, (string) $settings['repair_number_prefix']);
        if (!$isDraft && class_exists('Notification')) {
            try {
                \Notification::create($customerId, 'تعمیر دستگاه ثبت شد', 'پرونده ' . $repair['tracking_code'] . ' برای دستگاه شما ایجاد شد.', 'repair', \url('portal/repairs/' . (int) $repair['id']), 'repair-created:' . (int) $repair['id']);
            } catch (\Throwable $notificationError) {
                error_log('[PromaRepair][intake] Repair saved but customer notification failed: ' . $notificationError->getMessage());
            }
        }
        return $repair;
    }

    public function track(string $trackingCode, string $mobile, string $ip): ?array
    {
        $trackingCode = strtoupper(trim($trackingCode));
        $mobile = $this->normalizeMobile($mobile);
        if (!preg_match('/^[A-Z0-9-]{8,40}$/', $trackingCode) || strlen($mobile) < 10) {
            return null;
        }
        $this->consumeTrackingAttempt($trackingCode, $ip);
        $repair = \Model::fetch(
            'SELECT r.id, r.tracking_code, r.status, r.estimated_delivery_at, r.created_at, d.device_type, d.brand, d.model
             FROM proma_repairs r JOIN users u ON u.id=r.customer_id JOIN proma_repair_devices d ON d.repair_id=r.id
             WHERE r.tracking_code = ? AND r.deleted_at IS NULL AND (u.mobile = ? OR u.secondary_phone = ?) LIMIT 1',
            [$trackingCode, $mobile, $mobile]
        );
        if (!$repair) {
            return null;
        }
        if (($this->settings->all()['show_customer_delivery_estimate'] ?? '1') !== '1') {
            $repair['estimated_delivery_at'] = null;
        }
        $repair['status_label'] = RepairWorkflow::customerLabel((string) $repair['status']);
        $repair['timeline'] = $this->repairs->timeline((int) $repair['id'], true);
        return $repair;
    }

    public function purgeExpiredPasswords(): int
    {
        $ids = \Model::fetchAll(
            'SELECT d.repair_id FROM proma_repair_devices d JOIN proma_repairs r ON r.id=d.repair_id
             WHERE d.password_ciphertext IS NOT NULL AND d.password_delete_at IS NOT NULL AND d.password_delete_at <= NOW()
               AND r.status = \'delivered\' LIMIT 100'
        );
        foreach ($ids as $row) {
            $id = (int) $row['repair_id'];
            \Model::execute('UPDATE proma_repair_devices SET password_ciphertext=NULL, password_type=NULL, password_deleted_at=NOW() WHERE repair_id=?', [$id]);
            $this->repairs->audit($id, 0, 'device_password_expired', ['retention' => 'automatic']);
        }
        return count($ids);
    }

    private function money($value): int
    {
        return \Proma\Plugins\Repair\Domain\RepairMoney::input($value);
    }

    private function consumeTrackingAttempt(string $tracking, string $ip): void
    {
        $ip = substr((string) $ip, 0, 64);
        $buckets = [
            hash('sha256', $ip . '|tracking|' . $tracking) => 10,
            hash('sha256', $ip . '|all-tracking') => 60,
        ];
        \Model::execute('DELETE FROM proma_repair_tracking_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        foreach ($buckets as $bucket => $limit) {
            \Model::execute('INSERT INTO proma_repair_tracking_attempts (bucket_hash, attempted_at) VALUES (?, NOW())', [$bucket]);
            $row = \Model::fetch('SELECT COUNT(*) AS total FROM proma_repair_tracking_attempts WHERE bucket_hash=? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$bucket]);
            if ((int) ($row['total'] ?? 0) > $limit) {
                throw new \RuntimeException('تعداد تلاش‌های رهگیری زیاد است. یک ساعت دیگر دوباره امتحان کنید.');
            }
        }
    }

    private function normalizeMobile($value): string
    {
        $value = preg_replace('/\D+/', '', \to_english_digits((string) $value));
        if (strpos($value, '0098') === 0) {
            $value = '0' . substr($value, 4);
        } elseif (strpos($value, '98') === 0 && strlen($value) > 10) {
            $value = '0' . substr($value, 2);
        } elseif (strlen($value) === 10 && strpos($value, '9') === 0) {
            $value = '0' . $value;
        }
        return substr($value, 0, 16);
    }

    private function tokens($value, array $allowed): array
    {
        $values = is_array($value) ? $value : [$value];
        return array_values(array_unique(array_filter(array_map('strval', $values), static function ($item) use ($allowed) {
            return in_array($item, $allowed, true);
        })));
    }

    private function digitsOrText($value, int $max): string
    {
        return mb_substr(trim(\to_english_digits((string) $value)), 0, $max, 'UTF-8');
    }

    private function text($value, int $max): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $max, 'UTF-8');
    }

}
