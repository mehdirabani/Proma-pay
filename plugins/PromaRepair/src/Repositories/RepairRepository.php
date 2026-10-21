<?php

namespace Proma\Plugins\Repair\Repositories;

use Proma\Plugins\Repair\Domain\FinancialSummary;
use Proma\Plugins\Repair\Domain\RepairMoney;
use Proma\Plugins\Repair\Domain\RepairWorkflow;

class RepairRepository
{
    public function schemaReady(): bool
    {
        $row = \Model::fetch("SHOW TABLES LIKE 'proma_repairs'");
        $row2 = \Model::fetch("SHOW TABLES LIKE 'proma_repair_settings'");
        return (bool) $row && (bool) $row2;
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['r.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], RepairWorkflow::STATUSES, true)) {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['technician_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM proma_repair_assignments ra WHERE ra.repair_id = r.id AND ra.technician_user_id = ? AND ra.unassigned_at IS NULL)';
            $params[] = (int) $filters['technician_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'r.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filters['date_to'];
        }
        $deviceTypes = ['mobile', 'tablet', 'laptop', 'smartwatch', 'headphones', 'console', 'accessory', 'other'];
        if (!empty($filters['device_type']) && in_array($filters['device_type'], $deviceTypes, true)) {
            $where[] = 'd.device_type = ?';
            $params[] = $filters['device_type'];
        }
        if (!empty($filters['brand'])) {
            $where[] = 'd.brand LIKE ?';
            $params[] = '%' . trim((string) $filters['brand']) . '%';
        }
        if (in_array($filters['payment_status'] ?? '', ['unpaid', 'partial', 'paid'], true)) {
            $revenue = '(SELECT COALESCE(SUM(c.amount),0) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL)';
            $received = '(SELECT COALESCE(SUM(p.amount),0) FROM proma_repair_payments p WHERE p.repair_id=r.id AND p.voided_at IS NULL)';
            if ($filters['payment_status'] === 'unpaid') {
                $where[] = $revenue . ' > 0 AND ' . $received . ' = 0';
            } elseif ($filters['payment_status'] === 'partial') {
                $where[] = $received . ' > 0 AND ' . $received . ' < ' . $revenue;
            } else {
                $where[] = $revenue . ' > 0 AND ' . $received . ' >= ' . $revenue;
            }
        }
        if (in_array($filters['profitability'] ?? '', ['profitable', 'unprofitable'], true)) {
            $profit = '((SELECT COALESCE(SUM(c.amount),0) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL) - (SELECT COALESCE(SUM(e.amount),0) FROM proma_repair_expenses e WHERE e.repair_id=r.id AND e.voided_at IS NULL AND e.status IN (\'approved\',\'paid\')))';
            $where[] = $profit . ($filters['profitability'] === 'profitable' ? ' > 0' : ' <= 0');
        }
        if (in_array($filters['warranty'] ?? '', ['yes', 'no'], true)) {
            $where[] = ($filters['warranty'] === 'yes' ? '' : 'NOT ') . 'EXISTS (SELECT 1 FROM proma_repair_warranties w WHERE w.repair_id=r.id AND w.enabled=1)';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $needle = '%' . $q . '%';
            $where[] = '(r.tracking_code LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR u.secondary_phone LIKE ? OR d.imei LIKE ? OR d.serial_number LIKE ? OR d.model LIKE ? OR d.brand LIKE ? OR CAST(r.id AS CHAR) LIKE ? OR EXISTS (SELECT 1 FROM proma_repair_assignments qa JOIN users qu ON qu.id=qa.technician_user_id WHERE qa.repair_id=r.id AND qa.unassigned_at IS NULL AND qu.full_name LIKE ?))';
            array_push($params, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle, $needle);
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = \Model::fetch(
            'SELECT COUNT(*) AS total FROM proma_repairs r JOIN users u ON u.id = r.customer_id JOIN proma_repair_devices d ON d.repair_id = r.id' . $whereSql,
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(10, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $items = \Model::fetchAll(
            'SELECT r.id, r.tracking_code, r.customer_id, r.status, r.customer_report, r.estimated_delivery_at, r.created_at,
                    u.full_name AS customer_name, u.mobile AS customer_mobile, d.device_type, d.brand, d.model, d.imei,
                    (SELECT u2.full_name FROM proma_repair_assignments a JOIN users u2 ON u2.id = a.technician_user_id
                     WHERE a.repair_id = r.id AND a.assignment_role = \'primary\' AND a.unassigned_at IS NULL ORDER BY a.id DESC LIMIT 1) AS technician_name
             FROM proma_repairs r JOIN users u ON u.id = r.customer_id JOIN proma_repair_devices d ON d.repair_id = r.id' .
            $whereSql . " ORDER BY r.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function find(int $id): ?array
    {
        return \Model::fetch(
            'SELECT r.*, u.full_name AS customer_name, u.mobile AS customer_mobile, u.secondary_phone AS customer_secondary_phone,
                    d.device_type, d.brand, d.model, d.color, d.imei, d.serial_number, d.storage_capacity, d.ram_capacity,
                    d.carrier, d.sim_number, d.accessories, d.condition_flags, d.condition_notes, d.password_type,
                    (d.password_ciphertext IS NOT NULL AND d.password_deleted_at IS NULL) AS has_device_password,
                    (SELECT u2.full_name FROM proma_repair_assignments a JOIN users u2 ON u2.id = a.technician_user_id
                     WHERE a.repair_id = r.id AND a.assignment_role = \'primary\' AND a.unassigned_at IS NULL ORDER BY a.id DESC LIMIT 1) AS technician_name,
                    (SELECT a.technician_user_id FROM proma_repair_assignments a WHERE a.repair_id = r.id AND a.assignment_role = \'primary\' AND a.unassigned_at IS NULL ORDER BY a.id DESC LIMIT 1) AS technician_id
             FROM proma_repairs r JOIN users u ON u.id = r.customer_id JOIN proma_repair_devices d ON d.repair_id = r.id
             WHERE r.id = ? AND r.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    public function create(array $data, int $actorId, string $prefix): array
    {
        \Model::begin();
        try {
            $temp = 'TMP-' . strtoupper(bin2hex(random_bytes(12)));
            \Model::execute(
                'INSERT INTO proma_repairs (tracking_code, customer_id, created_by, status, customer_report, estimated_delivery_at, parent_repair_id, is_warranty_return, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$temp, (int) $data['customer_id'], $actorId, (string) ($data['status'] ?? 'received'), (string) $data['customer_report'], $data['estimated_delivery_at'] ?: null, (int) ($data['parent_repair_id'] ?? 0) ?: null, !empty($data['parent_repair_id']) ? 1 : 0]
            );
            $id = (int) \Model::lastInsertId();
            $year = $this->jalaliYear();
            $tracking = strtoupper($prefix) . '-' . $year . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            \Model::execute('UPDATE proma_repairs SET tracking_code = ? WHERE id = ?', [$tracking, $id]);

            $device = $data['device'];
            $cipher = null;
            $passwordType = trim((string) ($device['password_type'] ?? ''));
            $password = trim((string) ($device['device_password'] ?? ''));
            if ($password !== '') {
                $cipher = (new \Proma\Plugins\Repair\Support\SecretCipher())->encrypt($password);
            } else {
                $passwordType = '';
            }
            $deleteDays = max(-1, min(3650, (int) ($device['password_delete_after_days'] ?? 0)));
            $deleteAt = $deleteDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $deleteDays . ' days')) : null;
            \Model::execute(
                'INSERT INTO proma_repair_devices
                 (repair_id, device_type, brand, model, color, imei, serial_number, storage_capacity, ram_capacity, carrier, sim_number,
                  accessories, condition_flags, condition_notes, password_type, password_ciphertext, password_delete_after_days, password_delete_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id, $device['device_type'], $device['brand'], $device['model'], $device['color'], $device['imei'], $device['serial_number'],
                    $device['storage_capacity'], $device['ram_capacity'], $device['carrier'], $device['sim_number'],
                    json_encode($device['accessories'], JSON_UNESCAPED_UNICODE), json_encode($device['condition_flags'], JSON_UNESCAPED_UNICODE),
                    $device['condition_notes'], $passwordType ?: null, $cipher, $deleteDays, $deleteAt,
                ]
            );
            \Model::execute(
                'INSERT INTO proma_repair_status_history (repair_id, previous_status, new_status, user_id, note, created_at) VALUES (?, NULL, ?, ?, ?, NOW())',
                [$id, (string) ($data['status'] ?? 'received'), $actorId, !empty($data['status']) && $data['status'] === 'draft' ? 'ذخیره پیش‌نویس پذیرش' : 'پذیرش اولیه دستگاه']
            );
            if (trim((string) ($data['intake_note'] ?? '')) !== '') {
                \Model::execute('INSERT INTO proma_repair_notes (repair_id, user_id, note, visible_to_customer, created_at) VALUES (?, ?, ?, 0, NOW())', [$id, $actorId, trim((string) $data['intake_note'])]);
            }
            $technicianId = (int) ($data['technician_user_id'] ?? 0);
            if ($technicianId > 0) {
                $technician = \Model::fetch("SELECT t.user_id FROM proma_repair_technicians t JOIN users u ON u.id=t.user_id WHERE t.user_id=? AND t.active=1 AND t.role_active=1 AND u.status='active' LIMIT 1", [$technicianId]);
                if (!$technician) {
                    throw new \InvalidArgumentException('تعمیرکار انتخاب‌شده فعال نیست.');
                }
                \Model::execute("INSERT INTO proma_repair_assignments (repair_id, technician_user_id, assignment_role, assigned_by, assigned_at) VALUES (?, ?, 'primary', ?, NOW())", [$id, $technicianId, $actorId]);
            }
            \Model::execute(
                'INSERT INTO proma_repair_warranties (repair_id, enabled, warranty_days, created_at) VALUES (?, 0, ?, NOW())',
                [$id, max(0, (int) ($data['default_warranty_days'] ?? 0))]
            );
            $initialCharge = RepairMoney::input($data['initial_charge'] ?? 0);
            $initialPayment = RepairMoney::input($data['initial_payment'] ?? 0);
            if ($initialPayment > $initialCharge) {
                throw new \InvalidArgumentException('دریافتی اولیه نمی‌تواند از مبلغ اولیه بیشتر باشد.');
            }
            if ($initialCharge > 0) {
                \Model::execute('INSERT INTO proma_repair_charges (repair_id, charge_type, description, amount, created_by, created_at) VALUES (?, \'repair_service\', ?, ?, ?, NOW())', [$id, 'مبلغ اولیه پذیرش', $initialCharge, $actorId]);
            }
            if ($initialPayment > 0) {
                \Model::execute('INSERT INTO proma_repair_payments (repair_id, payment_type, amount, payment_method, reference, note, paid_at, created_by, created_at) VALUES (?, \'deposit\', ?, \'cash\', NULL, ?, NOW(), ?, NOW())', [$id, $initialPayment, 'دریافت هنگام پذیرش', $actorId]);
            }
            $this->audit($id, $actorId, 'repair_created', ['status' => 'received', 'tracking_code' => $tracking]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        return $this->find($id) ?: [];
    }

    public function timeline(int $id, bool $customer = false): array
    {
        $noteSelect = $customer ? 'NULL AS note' : 'h.note';
        $actorSelect = $customer ? 'NULL AS user_name' : 'u.full_name AS user_name';
        return \Model::fetchAll(
            'SELECT h.previous_status, h.new_status, ' . $noteSelect . ', h.created_at, ' . $actorSelect . '
             FROM proma_repair_status_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.repair_id = ? ORDER BY h.id ASC',
            [$id]
        );
    }

    public function diagnosis(int $id): array
    {
        return \Model::fetchAll(
            'SELECT d.*, u.full_name AS technician_name FROM proma_repair_diagnoses d LEFT JOIN users u ON u.id = d.technician_user_id WHERE d.repair_id = ? ORDER BY d.id DESC',
            [$id]
        );
    }

    public function notes(int $id, bool $customer = false): array
    {
        $sql = 'SELECT n.*, u.full_name AS user_name FROM proma_repair_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.repair_id = ?';
        if ($customer) {
            $sql .= ' AND n.visible_to_customer = 1';
        }
        return \Model::fetchAll($sql . ' ORDER BY n.id DESC', [$id]);
    }

    public function financialRows(int $id): array
    {
        return [
            'charges' => \Model::fetchAll('SELECT * FROM proma_repair_charges WHERE repair_id = ? AND voided_at IS NULL ORDER BY id DESC', [$id]),
            'payments' => \Model::fetchAll('SELECT * FROM proma_repair_payments WHERE repair_id = ? AND voided_at IS NULL ORDER BY paid_at DESC, id DESC', [$id]),
            'expenses' => \Model::fetchAll('SELECT * FROM proma_repair_expenses WHERE repair_id = ? AND voided_at IS NULL ORDER BY id DESC', [$id]),
        ];
    }

    public function financialSummary(int $id): array
    {
        $rows = $this->financialRows($id);
        $allExpenses = $rows['expenses'];
        $rows['expenses'] = array_values(array_filter($allExpenses, static function ($row) {
            return in_array((string) ($row['status'] ?? 'approved'), ['approved', 'paid'], true);
        }));
        $summary = FinancialSummary::calculate($rows['charges'], $rows['payments'], $rows['expenses']);
        $rows['all_expenses'] = $allExpenses;
        return $summary + $rows;
    }

    public function addDiagnosis(int $id, int $actorId, array $input): void
    {
        \Model::execute(
            'INSERT INTO proma_repair_diagnoses (repair_id, technician_user_id, diagnosis, proposed_amount, estimated_hours, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$id, $actorId, trim((string) $input['diagnosis']), RepairMoney::input($input['proposed_amount']), $input['estimated_hours'] !== '' ? max(0, (float) $input['estimated_hours']) : null]
        );
    }

    public function decideCustomerApproval(int $id, int $actorId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('تصمیم مشتری معتبر نیست.');
        }
        $target = $decision === 'approved' ? 'approved' : 'cancelled';
        $addedCharge = false;
        \Model::begin();
        try {
            $repair = \Model::fetch('SELECT id, status FROM proma_repairs WHERE id=? FOR UPDATE', [$id]);
            if (!$repair || $repair['status'] !== 'waiting_customer_approval' || !RepairWorkflow::canTransition((string) $repair['status'], $target)) {
                throw new \InvalidArgumentException('این پرونده دیگر در انتظار تأیید مشتری نیست.');
            }
            $diagnosis = \Model::fetch('SELECT id, proposed_amount FROM proma_repair_diagnoses WHERE repair_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$id]);
            if (!$diagnosis) {
                throw new \InvalidArgumentException('ابتدا تشخیص و هزینه پیشنهادی را ثبت کنید.');
            }
            $proposedAmount = RepairMoney::stored($diagnosis['proposed_amount'] ?? 0);
            \Model::execute('UPDATE proma_repair_diagnoses SET customer_decision=?, decision_at=NOW() WHERE id=?', [$decision, (int) $diagnosis['id']]);
            if ($decision === 'approved' && $proposedAmount > 0) {
                $alreadyCharged = \Model::fetch("SELECT id FROM proma_repair_charges WHERE repair_id=? AND charge_type='repair_service' AND description='اجرت پیشنهادی تعمیر' AND voided_at IS NULL LIMIT 1", [$id]);
                if (!$alreadyCharged) {
                    \Model::execute(
                        'INSERT INTO proma_repair_charges (repair_id, charge_type, description, amount, created_by, created_at) VALUES (?, \'repair_service\', \'اجرت پیشنهادی تعمیر\', ?, ?, NOW())',
                        [$id, $proposedAmount, $actorId]
                    );
                    $addedCharge = true;
                }
            }
            $note = $decision === 'approved' ? 'هزینه توسط مشتری تأیید شد.' : 'مشتری با هزینه اعلام‌شده موافقت نکرد.';
            \Model::execute('UPDATE proma_repairs SET status=?, updated_at=NOW() WHERE id=?', [$target, $id]);
            \Model::execute('INSERT INTO proma_repair_status_history (repair_id, previous_status, new_status, user_id, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$id, $repair['status'], $target, $actorId, $note]);
            $this->audit($id, $actorId, 'customer_decision_recorded', ['decision' => $decision]);
            if ($addedCharge) {
                $this->audit($id, $actorId, 'charge_added', ['amount' => $proposedAmount, 'type' => 'repair_service', 'source' => 'customer_approval']);
            }
            $this->audit($id, $actorId, 'status_changed', ['from' => $repair['status'], 'to' => $target]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        $this->notifyCustomer($id, $target);
    }

    public function saveWarranty(int $id, int $actorId, array $input): void
    {
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $days = max(0, min(3650, (int) $input['warranty_days']));
        $start = trim((string) ($input['starts_at'] ?? '')) ?: null;
        $end = trim((string) ($input['ends_at'] ?? '')) ?: null;
        if ($enabled && (!$start || !$end || $end < $start)) {
            throw new \InvalidArgumentException('بازه زمانی گارانتی را کامل و معتبر وارد کنید.');
        }
        \Model::execute(
            'INSERT INTO proma_repair_warranties (repair_id, enabled, warranty_days, starts_at, ends_at, conditions, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), warranty_days=VALUES(warranty_days), starts_at=VALUES(starts_at), ends_at=VALUES(ends_at), conditions=VALUES(conditions), updated_by=VALUES(updated_by), updated_at=NOW()',
            [$id, $enabled, $days, $start, $end, trim((string) ($input['conditions'] ?? '')) ?: null, $actorId]
        );
        $this->audit($id, $actorId, 'warranty_changed', ['enabled' => (bool) $enabled, 'days' => $days, 'ends_at' => $end]);
    }

    public function storeAttachment(int $id, string $type, string $path, array $file, int $actorId): int
    {
        \Model::execute(
            'INSERT INTO proma_repair_attachments (repair_id, attachment_type, stored_path, original_name, mime_type, file_size, uploader_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$id, $type, $path, mb_substr(basename((string) ($file['name'] ?? 'image')), 0, 190, 'UTF-8'), (string) $file['mime'], (int) $file['size'], $actorId]
        );
        $attachmentId = (int) \Model::lastInsertId();
        $this->audit($id, $actorId, 'attachment_uploaded', ['attachment_id' => $attachmentId, 'type' => $type]);
        return $attachmentId;
    }

    public function attachment(int $id): ?array
    {
        return \Model::fetch('SELECT * FROM proma_repair_attachments WHERE id=? LIMIT 1', [$id]);
    }

    public function customerAttachment(int $id, int $customerId): ?array
    {
        return \Model::fetch(
            "SELECT a.* FROM proma_repair_attachments a JOIN proma_repairs r ON r.id=a.repair_id
             WHERE a.id=? AND r.customer_id=? AND a.attachment_type IN ('front','back') LIMIT 1",
            [$id, $customerId]
        );
    }

    public function warrantyReturnSource(int $parentRepairId, int $customerId): ?array
    {
        return \Model::fetch(
            'SELECT r.id AS parent_repair_id, r.customer_id, u.full_name AS customer_name, u.mobile AS customer_mobile,
                    d.device_type, d.brand, d.model, d.color, d.imei, d.serial_number, d.storage_capacity, d.ram_capacity,
                    d.carrier, d.sim_number, d.accessories, d.condition_flags
             FROM proma_repairs r JOIN users u ON u.id=r.customer_id
             JOIN proma_repair_devices d ON d.repair_id=r.id JOIN proma_repair_warranties w ON w.repair_id=r.id
             WHERE r.id=? AND r.customer_id=? AND w.enabled=1 AND w.starts_at<=CURDATE() AND w.ends_at>=CURDATE() LIMIT 1',
            [$parentRepairId, $customerId]
        );
    }

    public function statistics(): array
    {
        $range = \Model::fetch(
            'SELECT COUNT(*) AS repair_count,
                    AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, delivered_at) END) AS average_hours,
                    SUM(status = \'unable_to_repair\') AS unable_to_repair,
                    SUM(status = \'cancelled\') AS cancelled,
                    SUM(status = \'delivered\') AS delivered,
                    ROUND(100 * SUM(status = \'delivered\') / NULLIF(SUM(status IN (\'delivered\',\'unable_to_repair\',\'cancelled\')),0),1) AS success_rate,
                    COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c JOIN proma_repairs fr ON fr.id=c.repair_id WHERE c.voided_at IS NULL AND fr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS revenue,
                    COALESCE((SELECT SUM(e.amount) FROM proma_repair_expenses e JOIN proma_repairs fr ON fr.id=e.repair_id WHERE e.voided_at IS NULL AND e.status IN (\'approved\',\'paid\') AND fr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS cost,
                    COALESCE((SELECT SUM(p.amount) FROM proma_repair_payments p JOIN proma_repairs fr ON fr.id=p.repair_id WHERE p.voided_at IS NULL AND fr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS received
             FROM proma_repairs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        ) ?: [];
        $technicians = \Model::fetchAll(
            'SELECT u.full_name, COUNT(DISTINCT a.repair_id) AS assigned_repairs,
                    COUNT(DISTINCT CASE WHEN r.status=\'delivered\' THEN r.id END) AS completed_repairs,
                    AVG(CASE WHEN r.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,r.created_at,r.delivered_at) END) AS average_hours,
                    COALESCE((SELECT SUM(e.amount) FROM proma_repair_expenses e WHERE e.technician_user_id=t.user_id AND e.voided_at IS NULL AND e.status IN (\'approved\',\'paid\') AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS recorded_cost,
                    COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c WHERE c.voided_at IS NULL AND c.repair_id IN (SELECT pa.repair_id FROM proma_repair_assignments pa WHERE pa.technician_user_id=t.user_id AND pa.assignment_role=\'primary\' AND pa.unassigned_at IS NULL AND pa.assigned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))),0) AS revenue_generated
             FROM proma_repair_technicians t JOIN users u ON u.id=t.user_id
             LEFT JOIN proma_repair_assignments a ON a.technician_user_id=t.user_id AND a.unassigned_at IS NULL AND a.assigned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             LEFT JOIN proma_repairs r ON r.id=a.repair_id
             WHERE t.active=1 GROUP BY t.user_id, u.full_name ORDER BY assigned_repairs DESC'
        );
        $brands = \Model::fetchAll("SELECT COALESCE(NULLIF(d.brand,''),'نامشخص') AS label, COUNT(*) AS total FROM proma_repairs r JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY COALESCE(NULLIF(d.brand,''),'نامشخص') ORDER BY total DESC LIMIT 8");
        $models = \Model::fetchAll("SELECT CONCAT_WS(' ',NULLIF(d.brand,''),NULLIF(d.model,'')) AS label, COUNT(*) AS total FROM proma_repairs r JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY CONCAT_WS(' ',NULLIF(d.brand,''),NULLIF(d.model,'')) ORDER BY total DESC LIMIT 8");
        $failures = \Model::fetchAll("SELECT LEFT(TRIM(diagnosis),120) AS label, COUNT(*) AS total FROM proma_repair_diagnoses WHERE created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) AND TRIM(diagnosis)<>'' GROUP BY LEFT(TRIM(diagnosis),120) ORDER BY total DESC LIMIT 8");
        return ['summary' => $range, 'technicians' => $technicians, 'brands' => $brands, 'models' => $models, 'failures' => $failures];
    }

    public function addCharge(int $id, int $actorId, array $input): void
    {
        \Model::execute(
            'INSERT INTO proma_repair_charges (repair_id, charge_type, description, amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$id, $input['charge_type'], $input['description'], RepairMoney::input($input['amount']), $actorId]
        );
        $this->audit($id, $actorId, 'charge_added', ['amount' => RepairMoney::input($input['amount']), 'type' => $input['charge_type']]);
    }

    public function addPayment(int $id, int $actorId, array $input): void
    {
        \Model::execute(
            'INSERT INTO proma_repair_payments (repair_id, payment_type, amount, payment_method, reference, note, paid_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$id, $input['payment_type'], RepairMoney::input($input['amount']), $input['payment_method'], $input['reference'] ?: null, $input['note'] ?: null, $input['paid_at'], $actorId]
        );
        $this->audit($id, $actorId, 'payment_added', ['amount' => RepairMoney::input($input['amount']), 'method' => $input['payment_method']]);
    }

    public function addExpense(int $id, int $actorId, array $input): void
    {
        \Model::execute(
            'INSERT INTO proma_repair_expenses (repair_id, expense_type, description, amount, status, expense_date, technician_user_id, created_by, created_at) VALUES (?, ?, ?, ?, \'approved\', ?, ?, ?, NOW())',
            [$id, $input['expense_type'], $input['description'], RepairMoney::input($input['amount']), $input['expense_date'] ?? date('Y-m-d'), $input['technician_user_id'] ?: null, $actorId]
        );
        $this->audit($id, $actorId, 'expense_added', ['amount' => RepairMoney::input($input['amount']), 'type' => $input['expense_type']]);
    }

    public function submitTechnicianExpense(int $id, int $actorId, array $input): int
    {
        $amount = RepairMoney::input($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ هزینه باید بیشتر از صفر باشد.');
        }
        \Model::begin();
        try {
            \Model::execute(
                'INSERT INTO proma_repair_expenses (repair_id, expense_type, description, amount, status, expense_date, technician_user_id, created_by, submitted_by, submitted_at, created_at)
                 VALUES (?, ?, ?, ?, \'pending_approval\', ?, ?, ?, ?, NOW(), NOW())',
                [$id, (string) $input['expense_type'], (string) $input['description'], $amount, $input['expense_date'] ?? date('Y-m-d'), $actorId, $actorId, $actorId]
            );
            $expenseId = (int) \Model::lastInsertId();
            $this->expenseEvent($expenseId, $id, $actorId, 'submitted', null, $amount, (string) ($input['note'] ?? ''));
            $this->audit($id, $actorId, 'expense_submitted_for_approval', ['expense_id' => $expenseId, 'amount' => $amount, 'type' => $input['expense_type']]);
            \Model::commit();
            return $expenseId;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function approveTechnicianExpense(int $repairId, int $expenseId, int $actorId): int
    {
        \Model::begin();
        try {
            $expense = \Model::fetch('SELECT * FROM proma_repair_expenses WHERE id=? AND repair_id=? AND voided_at IS NULL LIMIT 1 FOR UPDATE', [$expenseId, $repairId]);
            if (!$expense) {
                throw new \InvalidArgumentException('درخواست هزینه پیدا نشد.');
            }
            if (in_array((string) $expense['status'], ['approved', 'paid'], true) && (int) ($expense['accounting_transaction_id'] ?? 0) > 0) {
                \Model::commit();
                return (int) $expense['accounting_transaction_id'];
            }
            if ((string) $expense['status'] !== 'pending_approval' || (int) ($expense['technician_user_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('این هزینه در انتظار تأیید نیست.');
            }
            $key = 'repair-expense-approve:' . $expenseId;
            $entryId = \PluginManager::filter('accounting.repair_expense.post', null, [
                'expense_id' => $expenseId, 'repair_id' => $repairId, 'user_id' => (int) $expense['technician_user_id'],
                'actor_id' => $actorId, 'amount' => RepairMoney::stored($expense['amount'] ?? 0),
                'description' => mb_substr('هزینه تعمیر ' . $expense['description'], 0, 255, 'UTF-8'),
                'idempotency_key' => $key, 'metadata' => ['repair_id' => $repairId, 'expense_type' => $expense['expense_type']],
            ]);
            if ((int) $entryId <= 0) {
                throw new \RuntimeException('افزونه حسابداری فعال نیست یا ثبت سند دفترکل ناموفق بود.');
            }
            \Model::execute('UPDATE proma_repair_expenses SET status=\'approved\', approved_by=?, approved_at=NOW(), accounting_transaction_id=?, idempotency_key=?, manager_note=NULL WHERE id=?', [$actorId, (int) $entryId, $key, $expenseId]);
            $amount = RepairMoney::stored($expense['amount'] ?? 0);
            $this->expenseEvent($expenseId, $repairId, $actorId, 'approved', $amount, $amount, 'ثبت در دفترکل حسابداری #' . (int) $entryId);
            $this->audit($repairId, $actorId, 'expense_approved', ['expense_id' => $expenseId, 'accounting_transaction_id' => (int) $entryId]);
            \Model::commit();
            return (int) $entryId;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function rejectTechnicianExpense(int $repairId, int $expenseId, int $actorId, string $note): void
    {
        if (trim($note) === '') {
            throw new \InvalidArgumentException('دلیل رد هزینه را وارد کنید.');
        }
        \Model::begin();
        try {
            $expense = \Model::fetch('SELECT * FROM proma_repair_expenses WHERE id=? AND repair_id=? AND status=\'pending_approval\' AND voided_at IS NULL LIMIT 1 FOR UPDATE', [$expenseId, $repairId]);
            if (!$expense) {
                throw new \InvalidArgumentException('درخواست هزینه در انتظار تأیید پیدا نشد.');
            }
            $note = mb_substr(trim(strip_tags($note)), 0, 500, 'UTF-8');
            \Model::execute('UPDATE proma_repair_expenses SET status=\'rejected\', rejected_by=?, rejected_at=NOW(), manager_note=? WHERE id=?', [$actorId, $note, $expenseId]);
            $amount = RepairMoney::stored($expense['amount'] ?? 0);
            $this->expenseEvent($expenseId, $repairId, $actorId, 'rejected', $amount, $amount, $note);
            $this->audit($repairId, $actorId, 'expense_rejected', ['expense_id' => $expenseId, 'reason' => $note]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function settleTechnicianExpense(int $repairId, int $expenseId, int $actorId): int
    {
        \Model::begin();
        try {
            $expense = \Model::fetch('SELECT * FROM proma_repair_expenses WHERE id=? AND repair_id=? AND voided_at IS NULL LIMIT 1 FOR UPDATE', [$expenseId, $repairId]);
            if (!$expense) {
                throw new \InvalidArgumentException('هزینه پیدا نشد.');
            }
            if ((string) $expense['status'] === 'paid' && (int) ($expense['settlement_transaction_id'] ?? 0) > 0) {
                \Model::commit();
                return (int) $expense['settlement_transaction_id'];
            }
            if ((string) $expense['status'] !== 'approved' || (int) ($expense['accounting_transaction_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('ابتدا هزینه را در حسابداری تأیید کنید.');
            }
            $entryId = \PluginManager::filter('accounting.repair_expense.settle', null, [
                'expense_id' => $expenseId, 'repair_id' => $repairId, 'user_id' => (int) $expense['technician_user_id'],
                'actor_id' => $actorId, 'amount' => RepairMoney::stored($expense['amount'] ?? 0),
                'description' => mb_substr('تسویه هزینه تعمیر ' . $expense['description'], 0, 255, 'UTF-8'),
                'idempotency_key' => 'repair-expense-settle:' . $expenseId, 'metadata' => ['repair_id' => $repairId, 'expense_id' => $expenseId],
            ]);
            if ((int) $entryId <= 0) {
                throw new \RuntimeException('ثبت تسویه در دفترکل حسابداری انجام نشد.');
            }
            \Model::execute('UPDATE proma_repair_expenses SET status=\'paid\', settlement_transaction_id=? WHERE id=?', [(int) $entryId, $expenseId]);
            $amount = RepairMoney::stored($expense['amount'] ?? 0);
            $this->expenseEvent($expenseId, $repairId, $actorId, 'settled', $amount, $amount, 'ثبت در دفترکل حسابداری #' . (int) $entryId);
            $this->audit($repairId, $actorId, 'expense_settled', ['expense_id' => $expenseId, 'settlement_transaction_id' => (int) $entryId]);
            \Model::commit();
            return (int) $entryId;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function pendingExpenses(): array
    {
        return \Model::fetchAll('SELECT e.*, r.tracking_code, u.full_name AS technician_name, d.brand, d.model FROM proma_repair_expenses e JOIN proma_repairs r ON r.id=e.repair_id JOIN users u ON u.id=e.technician_user_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE e.status=\'pending_approval\' AND e.voided_at IS NULL ORDER BY e.submitted_at, e.id');
    }

    public function pendingExpensesForRepair(int $repairId): array
    {
        return \Model::fetchAll('SELECT e.*, u.full_name AS technician_name FROM proma_repair_expenses e LEFT JOIN users u ON u.id=e.technician_user_id WHERE e.repair_id=? AND e.status=\'pending_approval\' AND e.voided_at IS NULL ORDER BY e.submitted_at, e.id', [$repairId]);
    }

    public function technicianExpenses(int $technicianId, ?string $status = null): array
    {
        $sql = 'SELECT e.*, r.tracking_code, d.brand, d.model FROM proma_repair_expenses e JOIN proma_repairs r ON r.id=e.repair_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE e.technician_user_id=? AND e.voided_at IS NULL';
        $params = [$technicianId];
        if ($status && in_array($status, ['pending_approval', 'approved', 'rejected', 'paid'], true)) { $sql .= ' AND e.status=?'; $params[] = $status; }
        return \Model::fetchAll($sql . ' ORDER BY e.id DESC LIMIT 200', $params);
    }

    private function expenseEvent(int $expenseId, int $repairId, int $actorId, string $action, ?int $previous, ?int $final, string $note): void
    {
        \Model::execute('INSERT INTO proma_repair_expense_events (expense_id, repair_id, actor_user_id, action, previous_amount, final_amount, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', [$expenseId, $repairId, $actorId ?: null, $action, $previous, $final, $note !== '' ? mb_substr($note, 0, 500, 'UTF-8') : null]);
    }

    public function voidFinancialEntry(int $repairId, string $kind, int $entryId, int $actorId, string $reason): void
    {
        $tables = [
            'charge' => ['table' => 'proma_repair_charges', 'action' => 'charge_voided'],
            'payment' => ['table' => 'proma_repair_payments', 'action' => 'payment_voided'],
            'expense' => ['table' => 'proma_repair_expenses', 'action' => 'expense_voided'],
        ];
        if (!isset($tables[$kind])) {
            throw new \InvalidArgumentException('نوع سند مالی معتبر نیست.');
        }
        $table = $tables[$kind]['table'];
        \Model::begin();
        try {
            $select = $kind === 'expense' ? 'id, amount, accounting_transaction_id, settlement_transaction_id' : 'id, amount';
            $row = \Model::fetch("SELECT {$select} FROM {$table} WHERE id=? AND repair_id=? AND voided_at IS NULL LIMIT 1 FOR UPDATE", [$entryId, $repairId]);
            if (!$row) {
                throw new \InvalidArgumentException('سند مالی فعال پیدا نشد.');
            }
            if ($kind === 'expense' && ((int) ($row['accounting_transaction_id'] ?? 0) > 0 || (int) ($row['settlement_transaction_id'] ?? 0) > 0)) {
                throw new \InvalidArgumentException('هزینه‌ای که در دفترکل حسابداری ثبت شده با ابطال عادی قابل تغییر نیست؛ سند اصلاحی باید از حسابداری ثبت شود.');
            }
            \Model::execute("UPDATE {$table} SET voided_at=NOW(), voided_by=?, void_reason=? WHERE id=? AND repair_id=? AND voided_at IS NULL", [$actorId, mb_substr($reason, 0, 255, 'UTF-8'), $entryId, $repairId]);
            $this->audit($repairId, $actorId, $tables[$kind]['action'], ['entry_id' => $entryId, 'amount' => RepairMoney::stored($row['amount'] ?? 0), 'reason' => $reason]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function deleteDevicePassword(int $repairId, int $actorId): bool
    {
        $secret = \Model::fetch('SELECT password_ciphertext FROM proma_repair_devices WHERE repair_id=? AND password_ciphertext IS NOT NULL AND password_deleted_at IS NULL LIMIT 1', [$repairId]);
        if (!$secret) {
            return false;
        }
        \Model::execute('UPDATE proma_repair_devices SET password_ciphertext=NULL, password_type=NULL, password_delete_at=NULL, password_deleted_at=NOW() WHERE repair_id=? AND password_ciphertext IS NOT NULL AND password_deleted_at IS NULL', [$repairId]);
        $this->audit($repairId, $actorId, 'device_password_deleted_manual');
        return true;
    }

    public function softDelete(int $repairId, int $actorId, string $reason = ''): void
    {
        \Model::begin();
        try {
            $repair = \Model::fetch('SELECT id, tracking_code FROM proma_repairs WHERE id=? AND deleted_at IS NULL LIMIT 1 FOR UPDATE', [$repairId]);
            if (!$repair) {
                throw new \InvalidArgumentException('پرونده فعال پیدا نشد.');
            }
            \Model::execute('UPDATE proma_repairs SET deleted_at=NOW(), deleted_by=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL', [$actorId, $repairId]);
            \Model::execute('UPDATE proma_repair_assignments SET unassigned_at=NOW() WHERE repair_id=? AND unassigned_at IS NULL', [$repairId]);
            $this->audit($repairId, $actorId, 'repair_archived', ['tracking_code' => $repair['tracking_code'], 'reason' => mb_substr(trim($reason), 0, 255, 'UTF-8')]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function addNote(int $id, int $actorId, string $note, bool $visible): void
    {
        \Model::execute('INSERT INTO proma_repair_notes (repair_id, user_id, note, visible_to_customer, created_at) VALUES (?, ?, ?, ?, NOW())', [$id, $actorId, $note, $visible ? 1 : 0]);
        $this->audit($id, $actorId, 'note_added', ['visible_to_customer' => $visible]);
    }

    public function updateFields(int $id, int $actorId, array $repairFields, array $deviceFields): void
    {
        $repairAllowed = ['customer_report', 'estimated_delivery_at'];
        $deviceAllowed = ['device_type', 'brand', 'model', 'color', 'imei', 'serial_number', 'storage_capacity', 'ram_capacity', 'carrier', 'sim_number', 'condition_notes'];
        $sets = [];
        $params = [];
        $changed = [];
        foreach ($repairFields as $key => $value) {
            if (!in_array($key, $repairAllowed, true)) {
                continue;
            }
            $sets[] = $key . '=?';
            $params[] = $value;
            $changed[] = $key;
        }
        if ($sets) {
            $sets[] = 'updated_at=NOW()';
            $params[] = $id;
            \Model::execute('UPDATE proma_repairs SET ' . implode(',', $sets) . ' WHERE id=?', $params);
        }
        $sets = [];
        $params = [];
        foreach ($deviceFields as $key => $value) {
            if (!in_array($key, $deviceAllowed, true)) {
                continue;
            }
            $sets[] = $key . '=?';
            $params[] = $value;
            $changed[] = 'device.' . $key;
        }
        if ($sets) {
            $params[] = $id;
            \Model::execute('UPDATE proma_repair_devices SET ' . implode(',', $sets) . ' WHERE repair_id=?', $params);
        }
        if (!$changed) {
            throw new \InvalidArgumentException('هیچ فیلد قابل ویرایشی دریافت نشد.');
        }
        $this->audit($id, $actorId, 'repair_updated', ['fields' => $changed]);
    }

    public function transition(int $id, string $newStatus, int $actorId, string $note = ''): void
    {
        \Model::begin();
        try {
            $repair = \Model::fetch('SELECT id, status, customer_id FROM proma_repairs WHERE id = ? FOR UPDATE', [$id]);
            if (!$repair) {
                throw new \InvalidArgumentException('پرونده تعمیر پیدا نشد.');
            }
            if (!RepairWorkflow::canTransition((string) $repair['status'], $newStatus)) {
                throw new \InvalidArgumentException('تغییر وضعیت انتخاب‌شده در این مرحله مجاز نیست.');
            }
            \Model::execute('UPDATE proma_repairs SET status = ?, updated_at = NOW(), delivered_at = IF(? = \'delivered\', NOW(), delivered_at) WHERE id = ?', [$newStatus, $newStatus, $id]);
            \Model::execute(
                'INSERT INTO proma_repair_status_history (repair_id, previous_status, new_status, user_id, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$id, $repair['status'], $newStatus, $actorId, mb_substr(trim($note), 0, 500, 'UTF-8') ?: null]
            );
            if ($newStatus === 'delivered') {
                \Model::execute(
                    'UPDATE proma_repair_devices SET
                       password_delete_at = CASE WHEN password_delete_after_days = 0 THEN NOW() WHEN password_delete_after_days > 0 THEN DATE_ADD(NOW(), INTERVAL password_delete_after_days DAY) ELSE NULL END
                     WHERE repair_id = ? AND password_ciphertext IS NOT NULL',
                    [$id]
                );
                \Model::execute(
                    'UPDATE proma_repair_devices SET password_ciphertext=NULL, password_type=NULL, password_deleted_at=NOW()
                     WHERE repair_id = ? AND password_delete_after_days = 0 AND password_ciphertext IS NOT NULL',
                    [$id]
                );
            }
            $this->audit($id, $actorId, 'status_changed', ['from' => $repair['status'], 'to' => $newStatus]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        $this->notifyCustomer($id, $newStatus);
    }

    public function assignments(int $id): array
    {
        return \Model::fetchAll(
            'SELECT a.*, u.full_name, u.mobile FROM proma_repair_assignments a JOIN users u ON u.id = a.technician_user_id WHERE a.repair_id = ? ORDER BY a.id DESC',
            [$id]
        );
    }

    public function assign(int $id, int $technicianId, int $actorId, string $role = 'primary'): void
    {
        $active = \Model::fetch('SELECT id FROM proma_repair_technicians WHERE user_id = ? AND active = 1 LIMIT 1', [$technicianId]);
        if (!$active) {
            throw new \InvalidArgumentException('حساب انتخاب‌شده در فهرست تعمیرکاران فعال نیست.');
        }
        \Model::begin();
        try {
            if ($role === 'primary') {
                \Model::execute("UPDATE proma_repair_assignments SET unassigned_at = NOW() WHERE repair_id = ? AND assignment_role = 'primary' AND unassigned_at IS NULL", [$id]);
            }
            \Model::execute(
                'INSERT INTO proma_repair_assignments (repair_id, technician_user_id, assignment_role, assigned_by, assigned_at) VALUES (?, ?, ?, ?, NOW())',
                [$id, $technicianId, $role === 'collaborator' ? 'collaborator' : 'primary', $actorId]
            );
            $this->audit($id, $actorId, 'technician_assigned', ['technician_user_id' => $technicianId, 'role' => $role]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        \Notification::create($technicianId, 'تعمیر جدید به شما ارجاع شد', 'یک پرونده تعمیر به فهرست کارهای شما افزوده شد.', 'repair', \url('plugin/repair/detail/' . $id), 'repair-assigned:' . $id . ':' . $technicianId);
    }

    public function isAssigned(int $id, int $userId): bool
    {
        return (bool) \Model::fetch('SELECT id FROM proma_repair_assignments WHERE repair_id = ? AND technician_user_id = ? AND unassigned_at IS NULL LIMIT 1', [$id, $userId]);
    }

    public function technicians(): array
    {
        return \Model::fetchAll(
            'SELECT t.*, u.full_name, u.mobile FROM proma_repair_technicians t JOIN users u ON u.id = t.user_id WHERE t.role_active=1 ORDER BY t.active DESC, u.full_name'
        );
    }

    public function addTechnician(int $userId, int $actorId): void
    {
        $user = \Model::fetch("SELECT id FROM users WHERE id = ? AND status = 'active' AND role = 'repair_technician' LIMIT 1", [$userId]);
        if (!$user) {
            throw new \InvalidArgumentException('تعمیرکار باید یک کاربر فعال کارمند در Proma Pay باشد.');
        }
        \Model::execute('INSERT INTO proma_repair_technicians (user_id, active, role_active, created_by, created_at) VALUES (?, 1, 1, ?, NOW()) ON DUPLICATE KEY UPDATE active = 1, role_active=1', [$userId, $actorId]);
        $this->audit(null, $actorId, 'technician_registered', ['user_id' => $userId]);
    }

    public function createTechnicianAccount(array $input, int $actorId): int
    {
        $data = [
            'full_name' => trim(strip_tags((string) ($input['full_name'] ?? ''))),
            'username' => trim(\to_english_digits((string) ($input['username'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'mobile' => trim((string) ($input['mobile'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'national_id' => trim(\to_english_digits((string) ($input['national_id'] ?? ''))),
        ];
        $validator = (new \Validator($data))
            ->required('full_name', 'نام کامل')
            ->required('username', 'نام کاربری')
            ->required('password', 'رمز عبور')
            ->mobile('mobile', 'موبایل')
            ->nationalId('national_id', 'کد ملی')
            ->email('email', 'ایمیل');
        if (!$validator->passes()) {
            throw new \InvalidArgumentException(implode(' ', $validator->errors()));
        }
        if (mb_strlen($data['full_name'], 'UTF-8') > 190 || mb_strlen($data['email'], 'UTF-8') > 190) {
            throw new \InvalidArgumentException('نام کامل و ایمیل نباید بیشتر از ۱۹۰ نویسه باشند.');
        }
        if (!preg_match('/^[\p{L}\p{N}._-]{3,100}$/u', $data['username'])) {
            throw new \InvalidArgumentException('نام کاربری باید ۳ تا ۱۰۰ نویسه و شامل حروف، عدد، نقطه، خط تیره یا زیرخط باشد.');
        }
        $passwordLength = mb_strlen($data['password'], 'UTF-8');
        if ($passwordLength < 8 || $passwordLength > 200) {
            throw new \InvalidArgumentException('رمز عبور باید دست‌کم ۸ نویسه باشد.');
        }

        \Model::begin();
        try {
            $userId = (int) \User::create([
                'role' => 'repair_technician',
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'father_name' => mb_substr(trim((string) ($input['father_name'] ?? '')), 0, 190, 'UTF-8'),
                'issued_from' => mb_substr(trim((string) ($input['issued_from'] ?? '')), 0, 190, 'UTF-8'),
                'national_id' => $data['national_id'],
                'mobile' => $data['mobile'],
                'secondary_phone' => '',
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
                'address' => trim((string) ($input['address'] ?? '')),
                'department' => null,
                'is_department_manager' => 0,
                'avatar_key' => \normalize_avatar_key($input['avatar_key'] ?? 'avatar-1'),
            ]);
            $this->addTechnician($userId, $actorId);
            \Model::commit();
            return $userId;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    public function technicianProfile(int $userId): ?array
    {
        return \Model::fetch("SELECT t.*, u.full_name, u.mobile, u.status AS user_status FROM proma_repair_technicians t JOIN users u ON u.id=t.user_id WHERE t.user_id=? AND t.active=1 AND t.role_active=1 AND u.role='repair_technician' AND u.status='active' LIMIT 1", [$userId]);
    }

    public function saveTechnicianProfile(int $userId, int $actorId, array $input): void
    {
        $specialty = mb_substr(trim(strip_tags((string) ($input['specialty'] ?? ''))), 0, 160, 'UTF-8');
        $phone = mb_substr(trim(strip_tags((string) ($input['phone'] ?? ''))), 0, 32, 'UTF-8');
        $code = mb_substr(trim(strip_tags((string) ($input['internal_code'] ?? ''))), 0, 40, 'UTF-8');
        $model = in_array(($input['commission_model'] ?? ''), ['fixed', 'percentage'], true) ? $input['commission_model'] : 'fixed';
        $value = $model === 'percentage'
            ? RepairMoney::percentage($input['commission_value'] ?? 0)
            : RepairMoney::input($input['commission_value'] ?? 0);
        $notes = mb_substr(trim(strip_tags((string) ($input['notes'] ?? ''))), 0, 2000, 'UTF-8');
        $activeSql = array_key_exists('active', $input) ? ', active=?' : '';
        $params = [$specialty ?: null, $phone ?: null, $code ?: null, $model, $value, $notes ?: null];
        if (array_key_exists('active', $input)) { $params[] = !empty($input['active']) ? 1 : 0; }
        $params[] = $userId;
        \Model::execute('UPDATE proma_repair_technicians SET specialty=?, phone=?, internal_code=?, commission_model=?, commission_value=?, notes=?' . $activeSql . ' WHERE user_id=?', $params);
        $this->audit(null, $actorId, 'technician_profile_updated', ['technician_user_id' => $userId]);
    }

    public function dashboard(?int $technicianId = null): array
    {
        $scope = ' WHERE deleted_at IS NULL';
        $scopeParams = [];
        if ($technicianId) {
            $scope .= ' AND EXISTS (SELECT 1 FROM proma_repair_assignments ra WHERE ra.repair_id=proma_repairs.id AND ra.technician_user_id=? AND ra.unassigned_at IS NULL)';
            $scopeParams[] = $technicianId;
        }
        $counts = \Model::fetchAll('SELECT status, COUNT(*) AS total, SUM(DATE(created_at)=CURDATE()) AS today_total FROM proma_repairs' . $scope . ' GROUP BY status', $scopeParams);
        $statusCounts = [];
        foreach ($counts as $row) {
            $statusCounts[(string) $row['status']] = (int) $row['total'];
            if ((string) $row['status'] === 'received') {
                $statusCounts['received_today'] = (int) $row['today_total'];
            }
        }
        $finance = $technicianId ? [] : (\Model::fetch(
            'SELECT COALESCE((SELECT SUM(amount) FROM proma_repair_charges WHERE voided_at IS NULL),0) AS revenue,
                    COALESCE((SELECT SUM(amount) FROM proma_repair_payments WHERE voided_at IS NULL),0) AS received,
                    COALESCE((SELECT SUM(amount) FROM proma_repair_expenses WHERE voided_at IS NULL AND status IN (\'approved\',\'paid\')),0) AS costs,
                    COALESCE((SELECT SUM(amount) FROM proma_repair_expenses WHERE voided_at IS NULL AND status IN (\'approved\',\'paid\') AND expense_type=\'spare_part\'),0) AS part_costs,
                    COALESCE((SELECT SUM(amount) FROM proma_repair_expenses WHERE voided_at IS NULL AND status IN (\'approved\',\'paid\') AND expense_type=\'technician_cost\'),0) AS technician_costs,
                    COALESCE((SELECT SUM(amount) FROM proma_repair_expenses WHERE voided_at IS NULL AND status IN (\'approved\',\'paid\') AND expense_type NOT IN (\'spare_part\',\'technician_cost\')),0) AS other_costs'
        ) ?: []);
        $scopeSql = $technicianId ? ' AND EXISTS (SELECT 1 FROM proma_repair_assignments a WHERE a.repair_id=r.id AND a.technician_user_id=? AND a.unassigned_at IS NULL)' : '';
        $dueParams = $technicianId ? [$technicianId] : [];
        $due = \Model::fetchAll("SELECT r.id, r.tracking_code, r.status, r.created_at, u.full_name, d.brand, d.model FROM proma_repairs r JOIN users u ON u.id=r.customer_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.deleted_at IS NULL AND r.status NOT IN ('delivered','cancelled','unable_to_repair')" . $scopeSql . ' ORDER BY r.created_at ASC LIMIT 8', $dueParams);
        $latest = $this->list($technicianId ? ['technician_id' => $technicianId] : [], 1, 8)['items'];
        $unassigned = $technicianId ? [] : \Model::fetchAll("SELECT r.id, r.tracking_code, u.full_name, d.brand, d.model FROM proma_repairs r JOIN users u ON u.id=r.customer_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.deleted_at IS NULL AND r.status NOT IN ('delivered','cancelled','unable_to_repair') AND NOT EXISTS (SELECT 1 FROM proma_repair_assignments a WHERE a.repair_id=r.id AND a.assignment_role='primary' AND a.unassigned_at IS NULL) ORDER BY r.id DESC LIMIT 8");
        $ready = \Model::fetchAll("SELECT r.id, r.tracking_code, r.created_at, u.full_name, d.brand, d.model FROM proma_repairs r JOIN users u ON u.id=r.customer_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.deleted_at IS NULL AND r.status='ready_for_delivery'" . $scopeSql . ' ORDER BY r.updated_at ASC LIMIT 8', $dueParams);
        $debts = $technicianId ? [] : \Model::fetchAll("SELECT r.id, r.tracking_code, u.full_name, d.brand, d.model, (COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL),0)-COALESCE((SELECT SUM(p.amount) FROM proma_repair_payments p WHERE p.repair_id=r.id AND p.voided_at IS NULL),0)) AS balance FROM proma_repairs r JOIN users u ON u.id=r.customer_id JOIN proma_repair_devices d ON d.repair_id=r.id WHERE r.deleted_at IS NULL AND r.status NOT IN ('cancelled','unable_to_repair') AND (COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL),0)-COALESCE((SELECT SUM(p.amount) FROM proma_repair_payments p WHERE p.repair_id=r.id AND p.voided_at IS NULL),0))>0 ORDER BY balance DESC LIMIT 8");
        $technicianPerformance = $technicianId ? [] : \Model::fetchAll(
            'SELECT u.full_name, COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN a.repair_id END) AS assigned_repairs,
                    COUNT(DISTINCT CASE WHEN r.status=\'delivered\' THEN r.id END) AS completed_repairs,
                    AVG(CASE WHEN r.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,r.created_at,r.delivered_at) END) AS average_hours
             FROM proma_repair_technicians t JOIN users u ON u.id=t.user_id
             LEFT JOIN proma_repair_assignments a ON a.technician_user_id=t.user_id AND a.unassigned_at IS NULL
             LEFT JOIN proma_repairs r ON r.id=a.repair_id AND r.deleted_at IS NULL
             WHERE t.active=1 GROUP BY t.user_id,u.full_name ORDER BY assigned_repairs DESC LIMIT 8'
        );
        return ['counts' => $statusCounts, 'finance' => $finance, 'latest' => $latest, 'overdue' => $due, 'unassigned' => $unassigned, 'ready' => $ready, 'debts' => $debts, 'technician_performance' => $technicianPerformance];
    }

    public function customerList(int $customerId, bool $includeFinancials = true, bool $includeDeliveryEstimate = true): array
    {
        $revenue = $includeFinancials ? 'COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL),0)' : '0';
        $received = $includeFinancials ? 'COALESCE((SELECT SUM(p.amount) FROM proma_repair_payments p WHERE p.repair_id=r.id AND p.voided_at IS NULL),0)' : '0';
        $delivery = $includeDeliveryEstimate ? 'r.estimated_delivery_at' : 'NULL';
        return \Model::fetchAll(
            'SELECT r.id, r.tracking_code, r.status, ' . $delivery . ' AS estimated_delivery_at, r.created_at,
                    d.brand, d.model,
                    ' . $revenue . ' AS total_revenue,
                    ' . $received . ' AS total_received,
                    (SELECT a.id FROM proma_repair_attachments a WHERE a.repair_id=r.id AND a.attachment_type=\'front\' ORDER BY a.id DESC LIMIT 1) AS front_attachment_id
             FROM proma_repairs r JOIN proma_repair_devices d ON d.repair_id=r.id
             WHERE r.customer_id = ? AND r.status <> \'draft\' AND r.deleted_at IS NULL ORDER BY r.id DESC',
            [$customerId]
        );
    }

    public function customerDetail(int $id, int $customerId, bool $includeFinancials = true, bool $includeDeliveryEstimate = true, bool $includeTimeline = true, bool $includeWarranty = true): ?array
    {
        $revenue = $includeFinancials ? 'COALESCE((SELECT SUM(c.amount) FROM proma_repair_charges c WHERE c.repair_id=r.id AND c.voided_at IS NULL),0)' : '0';
        $received = $includeFinancials ? 'COALESCE((SELECT SUM(p.amount) FROM proma_repair_payments p WHERE p.repair_id=r.id AND p.voided_at IS NULL),0)' : '0';
        $delivery = $includeDeliveryEstimate ? 'r.estimated_delivery_at' : 'NULL';
        $warrantyFields = $includeWarranty ? 'w.enabled AS warranty_enabled, w.warranty_days, w.starts_at AS warranty_starts_at, w.ends_at AS warranty_ends_at, w.conditions AS warranty_conditions' : '0 AS warranty_enabled, NULL AS warranty_days, NULL AS warranty_starts_at, NULL AS warranty_ends_at, NULL AS warranty_conditions';
        $proposed = $includeFinancials ? '(SELECT dgn.proposed_amount FROM proma_repair_diagnoses dgn WHERE dgn.repair_id=r.id ORDER BY dgn.id DESC LIMIT 1)' : "CASE WHEN r.status='waiting_customer_approval' THEN (SELECT dgn.proposed_amount FROM proma_repair_diagnoses dgn WHERE dgn.repair_id=r.id ORDER BY dgn.id DESC LIMIT 1) ELSE NULL END";
        $repair = \Model::fetch(
            'SELECT r.id, r.tracking_code, r.status, r.customer_report, ' . $delivery . ' AS estimated_delivery_at, r.delivered_at, r.created_at,
                    d.device_type, d.brand, d.model, d.color, d.imei,
                    ' . $warrantyFields . ',
                    ' . $revenue . ' AS total_revenue,
                    ' . $received . ' AS total_received,
                    ' . $proposed . ' AS proposed_amount
             FROM proma_repairs r JOIN proma_repair_devices d ON d.repair_id=r.id
             LEFT JOIN proma_repair_warranties w ON w.repair_id=r.id
             WHERE r.id = ? AND r.customer_id = ? AND r.status <> \'draft\' AND r.deleted_at IS NULL LIMIT 1',
            [$id, $customerId]
        );
        if (!$repair) {
            return null;
        }
        $repair['timeline'] = $includeTimeline ? $this->timeline($id, true) : [];
        $repair['notes'] = $this->notes($id, true);
        $repair['payments'] = $includeFinancials ? \Model::fetchAll('SELECT payment_type, amount, payment_method, paid_at FROM proma_repair_payments WHERE repair_id = ? AND voided_at IS NULL ORDER BY paid_at DESC, id DESC', [$id]) : [];
        $repair['front_attachment_id'] = (int) ((\Model::fetch('SELECT id FROM proma_repair_attachments WHERE repair_id=? AND attachment_type=\'front\' ORDER BY id DESC LIMIT 1', [$id])['id'] ?? 0));
        $digits = preg_replace('/\D/', '', (string) ($repair['imei'] ?? ''));
        if ($digits !== '') {
            $repair['imei'] = strlen($digits) > 4 ? substr($digits, 0, 2) . str_repeat('*', strlen($digits) - 4) . substr($digits, -2) : str_repeat('*', strlen($digits));
        }
        return $repair;
    }

    public function attachments(int $id): array
    {
        return \Model::fetchAll('SELECT id, attachment_type, original_name, mime_type, file_size, uploader_id, created_at FROM proma_repair_attachments WHERE repair_id = ? ORDER BY id DESC', [$id]);
    }

    public function auditRows(?int $repairId = null): array
    {
        if ($repairId) {
            return \Model::fetchAll('SELECT a.*, u.full_name FROM proma_repair_audit a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.repair_id=? ORDER BY a.id DESC LIMIT 300', [$repairId]);
        }
        return \Model::fetchAll('SELECT a.*, u.full_name, r.tracking_code FROM proma_repair_audit a LEFT JOIN users u ON u.id=a.actor_user_id LEFT JOIN proma_repairs r ON r.id=a.repair_id ORDER BY a.id DESC LIMIT 500');
    }

    public function audit(?int $repairId, int $actorId, string $action, array $details = []): void
    {
        \Model::execute(
            'INSERT INTO proma_repair_audit (repair_id, actor_user_id, action, ip_address, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$repairId, $actorId ?: null, $action, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
        try {
            \AuditLog::record('repair', $action, 'repair', (int) ($repairId ?? 0), [
                'actor_user_id' => $actorId ?: null,
                'customer_id' => $repairId ? (int) (($this->find($repairId)['customer_id'] ?? 0)) : null,
                'description' => 'فعالیت در مدیریت تعمیرات',
                'new_values' => $details,
            ]);
        } catch (\Throwable $ignored) {
        }
    }

    private function notifyCustomer(int $id, string $status): void
    {
        $repair = $this->find($id);
        if (!$repair || !class_exists('Notification')) {
            return;
        }
        $label = RepairWorkflow::customerLabel($status);
        \Notification::create((int) $repair['customer_id'], 'به‌روزرسانی تعمیر دستگاه', 'وضعیت تعمیر ' . $repair['tracking_code'] . ': ' . $label, 'repair', \url('portal/repairs/' . $id), 'repair-status:' . $id . ':' . $status);
    }

    private function jalaliYear(): string
    {
        if (function_exists('gregorian_to_jalali')) {
            $jalali = \gregorian_to_jalali((int) date('Y'), (int) date('m'), (int) date('d'));
            if (is_array($jalali) && !empty($jalali[0])) {
                return (string) (int) $jalali[0];
            }
        }
        return date('Y');
    }
}
