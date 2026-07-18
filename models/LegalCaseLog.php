<?php

class LegalCaseLog extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }

        self::execute(
            "CREATE TABLE IF NOT EXISTS legal_case_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                legal_case_id BIGINT UNSIGNED NULL,
                action_stage VARCHAR(80) NOT NULL,
                action_title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                action_date DATE NOT NULL,
                action_time TIME NULL,
                registered_by BIGINT UNSIGNED NULL,
                assigned_lawyer_id BIGINT UNSIGNED NULL,
                next_status VARCHAR(80) NULL,
                attachment_path VARCHAR(255) NULL,
                cost_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                cost_type VARCHAR(80) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_legal_case_logs_contract (contract_id),
                INDEX idx_legal_case_logs_case (legal_case_id),
                INDEX idx_legal_case_logs_stage (action_stage),
                INDEX idx_legal_case_logs_date (action_date),
                CONSTRAINT fk_legal_case_logs_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
                CONSTRAINT fk_legal_case_logs_case FOREIGN KEY (legal_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL,
                CONSTRAINT fk_legal_case_logs_registered_by FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_legal_case_logs_lawyer FOREIGN KEY (assigned_lawyer_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ([
            'contract_id' => 'BIGINT UNSIGNED NOT NULL',
            'legal_case_id' => 'BIGINT UNSIGNED NULL',
            'action_stage' => 'VARCHAR(80) NOT NULL',
            'action_title' => 'VARCHAR(190) NOT NULL',
            'description' => 'TEXT NULL',
            'action_date' => 'DATE NOT NULL',
            'action_time' => 'TIME NULL',
            'registered_by' => 'BIGINT UNSIGNED NULL',
            'assigned_lawyer_id' => 'BIGINT UNSIGNED NULL',
            'next_status' => 'VARCHAR(80) NULL',
            'attachment_path' => 'VARCHAR(255) NULL',
            'cost_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'cost_type' => 'VARCHAR(80) NULL',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            try {
                self::execute("ALTER TABLE legal_case_logs ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) {
            }
        }

        foreach ([
            'idx_legal_case_logs_contract' => '(contract_id)',
            'idx_legal_case_logs_case' => '(legal_case_id)',
            'idx_legal_case_logs_stage' => '(action_stage)',
            'idx_legal_case_logs_date' => '(action_date)',
        ] as $index => $columns) {
            try {
                self::execute("ALTER TABLE legal_case_logs ADD INDEX {$index} {$columns}");
            } catch (Throwable $e) {
            }
        }

        self::$schemaReady = true;
    }

    public static function stageOptions()
    {
        return [
            'ارجاع به واحد حقوقی',
            'ارسال اخطار',
            'ثبت اظهارنامه',
            'ثبت دادخواست',
            'تعیین وقت رسیدگی',
            'حضور در جلسه دادگاه',
            'صدور رای',
            'اجرای حکم',
            'مختومه',
            'سایر',
        ];
    }

    public static function costTypeOptions()
    {
        return [
            'هزینه دادرسی',
            'هزینه اظهارنامه',
            'هزینه وکیل',
            'هزینه رفت‌وآمد',
            'هزینه کارشناسی',
            'هزینه اجراییه',
            'هزینه متفرقه',
        ];
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT l.*, c.contract_number, c.customer_id,
                    reg.full_name AS registered_by_name,
                    lawyer.full_name AS assigned_lawyer_name
             FROM legal_case_logs l
             JOIN contracts c ON c.id = l.contract_id
             LEFT JOIN users reg ON reg.id = l.registered_by
             LEFT JOIN users lawyer ON lawyer.id = l.assigned_lawyer_id
             WHERE l.id = ?",
            [(int) $id]
        );
    }

    public static function forContract($contractId)
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT l.*, lc.status AS legal_case_status, lc.stage AS legal_case_stage, lc.complaint_number,
                    reg.full_name AS registered_by_name,
                    lawyer.full_name AS assigned_lawyer_name
             FROM legal_case_logs l
             LEFT JOIN legal_cases lc ON lc.id = l.legal_case_id
             LEFT JOIN users reg ON reg.id = l.registered_by
             LEFT JOIN users lawyer ON lawyer.id = l.assigned_lawyer_id
             WHERE l.contract_id = ?
             ORDER BY l.action_date DESC, COALESCE(l.action_time, '23:59:59') DESC, l.id DESC",
            [(int) $contractId]
        );
    }

    public static function forCase($legalCaseId)
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT l.*, lc.status AS legal_case_status, lc.stage AS legal_case_stage, lc.complaint_number,
                    reg.full_name AS registered_by_name,
                    lawyer.full_name AS assigned_lawyer_name
             FROM legal_case_logs l
             LEFT JOIN legal_cases lc ON lc.id = l.legal_case_id
             LEFT JOIN users reg ON reg.id = l.registered_by
             LEFT JOIN users lawyer ON lawyer.id = l.assigned_lawyer_id
             WHERE l.legal_case_id = ?
             ORDER BY l.action_date DESC, COALESCE(l.action_time, '23:59:59') DESC, l.id DESC",
            [(int) $legalCaseId]
        );
    }

    public static function latestForContract($contractId)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT l.*, reg.full_name AS registered_by_name, lawyer.full_name AS assigned_lawyer_name
             FROM legal_case_logs l
             LEFT JOIN users reg ON reg.id = l.registered_by
             LEFT JOIN users lawyer ON lawyer.id = l.assigned_lawyer_id
             WHERE l.contract_id = ?
             ORDER BY l.action_date DESC, COALESCE(l.action_time, '23:59:59') DESC, l.id DESC
             LIMIT 1",
            [(int) $contractId]
        );
    }

    public static function costTotalForContract($contractId)
    {
        self::ensureSchema();
        $row = self::fetch(
            'SELECT COALESCE(SUM(cost_amount), 0) AS total FROM legal_case_logs WHERE contract_id = ?',
            [(int) $contractId]
        );
        return (float) ($row['total'] ?? 0);
    }

    public static function createLog(array $data, array $upload = [])
    {
        self::ensureSchema();
        $payload = self::normalizePayload($data);
        $attachmentPath = null;
        try {
            $attachmentPath = UploadHelper::storeSecureFile($upload, 'legal_case_logs/' . (int) $payload['contract_id']);
            self::execute(
                'INSERT INTO legal_case_logs
                 (contract_id, legal_case_id, action_stage, action_title, description, action_date, action_time,
                  registered_by, assigned_lawyer_id, next_status, attachment_path, cost_amount, cost_type, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    (int) $payload['contract_id'],
                    $payload['legal_case_id'],
                    $payload['action_stage'],
                    $payload['action_title'],
                    $payload['description'],
                    $payload['action_date'],
                    $payload['action_time'],
                    $payload['registered_by'],
                    $payload['assigned_lawyer_id'],
                    $payload['next_status'],
                    $attachmentPath,
                    $payload['cost_amount'],
                    $payload['cost_type'],
                ]
            );
            $id = (int) self::lastInsertId();
            if ($attachmentPath && class_exists('FileRecord')) {
                try {
                    FileRecord::relatePath($attachmentPath, 'legal_case_log', $id, 'legal_attachment', (int) $payload['registered_by']);
                    FileRecord::relatePath($attachmentPath, 'contract', (int) $payload['contract_id'], 'legal_attachment', (int) $payload['registered_by']);
                    if (!empty($payload['legal_case_id'])) {
                        FileRecord::relatePath($attachmentPath, 'legal_case', (int) $payload['legal_case_id'], 'legal_attachment', (int) $payload['registered_by']);
                    }
                } catch (Throwable $e) {
                    ErrorHandler::log('legal_log_file_relation', $e, 500);
                }
            }
            self::syncLegalCaseFromPayload($payload);
            self::notifyCaseStakeholders($payload, 'ثبت اقدام حقوقی', $payload['action_title']);
            return $id;
        } catch (Throwable $e) {
            if ($attachmentPath) {
                UploadHelper::deleteRelative($attachmentPath);
            }
            throw $e;
        }
    }

    public static function updateLog($id, array $data, array $upload = [])
    {
        self::ensureSchema();
        $existing = self::find((int) $id);
        if (!$existing) {
            throw new InvalidArgumentException('لاگ حقوقی پیدا نشد.');
        }

        $payload = self::normalizePayload($data + ['contract_id' => $existing['contract_id']]);
        $attachmentPath = $existing['attachment_path'] ?: null;
        $newAttachmentPath = $attachmentPath;
        $removeAttachment = (int) ($data['remove_attachment'] ?? 0) === 1;

        try {
            if (!empty($upload['tmp_name']) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newAttachmentPath = UploadHelper::storeSecureFile($upload, 'legal_case_logs/' . (int) $payload['contract_id']);
            } elseif ($removeAttachment) {
                $newAttachmentPath = null;
            }

            self::execute(
                'UPDATE legal_case_logs
                 SET legal_case_id = ?, action_stage = ?, action_title = ?, description = ?, action_date = ?, action_time = ?,
                     assigned_lawyer_id = ?, next_status = ?, attachment_path = ?, cost_amount = ?, cost_type = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $payload['legal_case_id'],
                    $payload['action_stage'],
                    $payload['action_title'],
                    $payload['description'],
                    $payload['action_date'],
                    $payload['action_time'],
                    $payload['assigned_lawyer_id'],
                    $payload['next_status'],
                    $newAttachmentPath,
                    $payload['cost_amount'],
                    $payload['cost_type'],
                    (int) $id,
                ]
            );

            if ($newAttachmentPath && $newAttachmentPath !== $attachmentPath && class_exists('FileRecord')) {
                try {
                    FileRecord::relatePath($newAttachmentPath, 'legal_case_log', (int) $id, 'legal_attachment', (int) $payload['registered_by']);
                    FileRecord::relatePath($newAttachmentPath, 'contract', (int) $payload['contract_id'], 'legal_attachment', (int) $payload['registered_by']);
                    if (!empty($payload['legal_case_id'])) {
                        FileRecord::relatePath($newAttachmentPath, 'legal_case', (int) $payload['legal_case_id'], 'legal_attachment', (int) $payload['registered_by']);
                    }
                } catch (Throwable $e) {
                    ErrorHandler::log('legal_log_file_relation_update', $e, 500);
                }
            }

            if ($newAttachmentPath !== $attachmentPath && $attachmentPath) {
                UploadHelper::deleteRelative($attachmentPath);
            }

            self::syncLegalCaseFromPayload($payload);
            self::notifyCaseStakeholders($payload, 'بروزرسانی اقدام حقوقی', $payload['action_title']);
            if (!empty($existing['legal_case_id']) && (int) $existing['legal_case_id'] !== (int) ($payload['legal_case_id'] ?: 0)) {
                self::refreshCaseFromLatestLog((int) $existing['legal_case_id']);
            }
            return true;
        } catch (Throwable $e) {
            if ($newAttachmentPath && $newAttachmentPath !== $attachmentPath) {
                UploadHelper::deleteRelative($newAttachmentPath);
            }
            throw $e;
        }
    }

    public static function deleteLog($id)
    {
        self::ensureSchema();
        $existing = self::find((int) $id);
        if (!$existing) {
            throw new InvalidArgumentException('لاگ حقوقی پیدا نشد.');
        }
        self::execute('DELETE FROM legal_case_logs WHERE id = ?', [(int) $id]);
        if (!empty($existing['attachment_path'])) {
            UploadHelper::deleteRelative($existing['attachment_path']);
        }
        if (!empty($existing['legal_case_id'])) {
            self::refreshCaseFromLatestLog((int) $existing['legal_case_id']);
        }
        return true;
    }

    protected static function normalizePayload(array $data)
    {
        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId <= 0 || !Contract::find($contractId)) {
            throw new InvalidArgumentException('قرارداد مرتبط معتبر نیست.');
        }

        $legalCaseId = !empty($data['legal_case_id']) ? (int) $data['legal_case_id'] : null;
        if ($legalCaseId) {
            $case = LegalCase::find($legalCaseId);
            if (!$case || (int) $case['contract_id'] !== $contractId) {
                throw new InvalidArgumentException('پرونده حقوقی مرتبط معتبر نیست.');
            }
        }

        $actionStage = trim((string) ($data['action_stage'] ?? ''));
        if ($actionStage === '') {
            throw new InvalidArgumentException('مرحله حقوقی الزامی است.');
        }

        $actionTitle = trim((string) ($data['action_title'] ?? ''));
        if ($actionTitle === '') {
            throw new InvalidArgumentException('عنوان اقدام الزامی است.');
        }

        $actionDate = parse_jalali_date($data['action_date'] ?? '') ?: date('Y-m-d');
        $actionTime = normalize_time($data['action_time'] ?? '') ?: null;
        $costAmount = normalize_money($data['cost_amount'] ?? 0);
        $costType = trim((string) ($data['cost_type'] ?? '')) ?: null;
        if ($costAmount > 0 && $costType === null) {
            throw new InvalidArgumentException('برای ثبت هزینه، نوع هزینه الزامی است.');
        }
        $assignedLawyerId = !empty($data['assigned_lawyer_id']) ? (int) $data['assigned_lawyer_id'] : null;
        if ($assignedLawyerId) {
            $lawyer = User::find($assignedLawyerId);
            if (!$lawyer || ($lawyer['role'] ?? '') !== 'lawyer' || ($lawyer['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('وکیل یا مسئول پرونده معتبر نیست.');
            }
        }

        return [
            'contract_id' => $contractId,
            'legal_case_id' => $legalCaseId,
            'action_stage' => $actionStage,
            'action_title' => $actionTitle,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'action_date' => $actionDate,
            'action_time' => $actionTime,
            'registered_by' => !empty($data['registered_by']) ? (int) $data['registered_by'] : null,
            'assigned_lawyer_id' => $assignedLawyerId,
            'next_status' => trim((string) ($data['next_status'] ?? '')) ?: null,
            'cost_amount' => $costAmount,
            'cost_type' => $costType,
        ];
    }

    protected static function syncLegalCaseFromPayload(array $payload)
    {
        if (empty($payload['legal_case_id'])) {
            return;
        }

        LegalCase::applyLogUpdate((int) $payload['legal_case_id'], [
            'stage' => $payload['action_stage'],
            'status' => self::normalizeCaseStatus($payload['next_status'] ?? ''),
            'lawyer_id' => $payload['assigned_lawyer_id'],
        ]);
    }

    protected static function refreshCaseFromLatestLog($legalCaseId)
    {
        $latest = self::fetch(
            'SELECT action_stage, next_status, assigned_lawyer_id
             FROM legal_case_logs
             WHERE legal_case_id = ?
             ORDER BY action_date DESC, COALESCE(action_time, "23:59:59") DESC, id DESC
             LIMIT 1',
            [(int) $legalCaseId]
        );
        if (!$latest) {
            return;
        }
        LegalCase::applyLogUpdate((int) $legalCaseId, [
            'stage' => $latest['action_stage'] ?? '',
            'status' => self::normalizeCaseStatus($latest['next_status'] ?? ''),
            'lawyer_id' => !empty($latest['assigned_lawyer_id']) ? (int) $latest['assigned_lawyer_id'] : null,
        ]);
    }

    protected static function normalizeCaseStatus($value)
    {
        $value = trim((string) $value);
        $map = [
            'باز' => 'open',
            'open' => 'open',
            'ارجاع شده' => 'referred',
            'ارجاع‌شده' => 'referred',
            'referred' => 'referred',
            'مختومه' => 'closed',
            'بسته' => 'closed',
            'closed' => 'closed',
        ];
        return $map[$value] ?? null;
    }

    protected static function notifyCaseStakeholders(array $payload, $title, $actionTitle)
    {
        if (empty($payload['legal_case_id'])) {
            return;
        }
        $case = LegalCase::find((int) $payload['legal_case_id']);
        if (!$case) {
            return;
        }
        Notification::createForLegalCase(
            $case,
            $title,
            'در پرونده حقوقی قرارداد ' . ($case['contract_number'] ?? '') . ' اقدام «' . $actionTitle . '» ثبت شد.',
            url('legal/show/' . (int) $case['id'])
        );
    }
}
