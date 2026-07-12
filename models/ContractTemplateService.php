<?php

class ContractTemplateService extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        self::execute("CREATE TABLE IF NOT EXISTS contract_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            current_version_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_contract_templates_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::execute("CREATE TABLE IF NOT EXISTS contract_template_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            body_source LONGTEXT NOT NULL,
            body_format VARCHAR(30) NOT NULL DEFAULT 'plain_text_v1',
            content_hash CHAR(64) NOT NULL,
            change_reason TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            published_by BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            superseded_at DATETIME NULL,
            UNIQUE KEY uq_contract_template_version (template_id, version_number),
            KEY idx_contract_template_versions_status (template_id, status, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::execute("CREATE TABLE IF NOT EXISTS contract_template_audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(80) NOT NULL,
            actor_id BIGINT UNSIGNED NULL,
            template_id BIGINT UNSIGNED NULL,
            version_id BIGINT UNSIGNED NULL,
            old_values_json LONGTEXT NULL,
            new_values_json LONGTEXT NULL,
            reason TEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_contract_template_audit_created (created_at),
            KEY idx_contract_template_audit_template (template_id, version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::execute("CREATE TABLE IF NOT EXISTS contract_document_rebuild_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            target_template_version_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            total_documents INT UNSIGNED NOT NULL DEFAULT 0,
            processed_documents INT UNSIGNED NOT NULL DEFAULT 0,
            failed_documents INT UNSIGNED NOT NULL DEFAULT 0,
            last_contract_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            failure_report_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            updated_at DATETIME NULL,
            KEY idx_contract_rebuild_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::execute("INSERT IGNORE INTO contract_templates (id, name, description, status, created_at) VALUES (1, 'قالب اصلی قرارداد', 'قالب پیش‌فرض اسناد قرارداد', 'active', NOW())");
        self::migrateLegacyTemplate();
        self::$schemaReady = true;
    }

    public static function getDefaultTemplate()
    {
        return [
            'id' => null,
            'template_id' => 1,
            'version_number' => 0,
            'body_source' => ContractDocument::defaultTemplate(),
            'body_format' => ContractTemplateRenderer::FORMAT_PLAIN,
            'status' => 'system_default',
            'change_reason' => 'قالب پیش‌فرض سامانه',
        ];
    }

    public static function getPublishedTemplate()
    {
        self::ensureSchema();
        return self::fetch("SELECT v.*, u.full_name AS created_by_name, p.full_name AS published_by_name
            FROM contract_template_versions v
            LEFT JOIN users u ON u.id = v.created_by
            LEFT JOIN users p ON p.id = v.published_by
            WHERE v.template_id = 1 AND v.status = 'published'
            ORDER BY v.version_number DESC, v.id DESC LIMIT 1");
    }

    public static function getDraftTemplate()
    {
        self::ensureSchema();
        return self::fetch("SELECT v.*, u.full_name AS created_by_name
            FROM contract_template_versions v
            LEFT JOIN users u ON u.id = v.created_by
            WHERE v.template_id = 1 AND v.status = 'draft'
            ORDER BY v.version_number DESC, v.id DESC LIMIT 1");
    }

    public static function getEffectiveTemplate()
    {
        return self::getPublishedTemplate() ?: self::getDefaultTemplate();
    }

    public static function versions()
    {
        self::ensureSchema();
        return self::fetchAll("SELECT v.*, u.full_name AS created_by_name, p.full_name AS published_by_name
            FROM contract_template_versions v
            LEFT JOIN users u ON u.id = v.created_by
            LEFT JOIN users p ON p.id = v.published_by
            WHERE v.template_id = 1
            ORDER BY v.version_number DESC, v.id DESC");
    }

    public static function findVersion($id)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM contract_template_versions WHERE id = ? AND template_id = 1 LIMIT 1', [(int) $id]);
    }

    public static function createVersion($source, $format, $reason, $userId = null)
    {
        self::ensureSchema();
        $format = in_array($format, [ContractTemplateRenderer::FORMAT_PLAIN, ContractTemplateRenderer::FORMAT_HTML], true)
            ? $format
            : ContractTemplateRenderer::FORMAT_PLAIN;
        $source = ContractTemplateRenderer::normalizeSource($source);
        $validation = self::validateTemplate($source, $format);
        if (!$validation['valid']) {
            throw new InvalidArgumentException(implode(' ', $validation['errors']));
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('دلیل تغییر قالب را وارد کنید.');
        }
        $hash = hash('sha256', $format . "\0" . $source);
        $latest = self::fetch('SELECT * FROM contract_template_versions WHERE template_id = 1 ORDER BY version_number DESC, id DESC LIMIT 1');
        if ($latest && hash_equals((string) $latest['content_hash'], $hash) && ($latest['status'] ?? '') === 'draft') {
            return (int) $latest['id'];
        }
        $version = ((int) ($latest['version_number'] ?? 0)) + 1;
        self::execute('INSERT INTO contract_template_versions (template_id, version_number, body_source, body_format, content_hash, change_reason, status, created_by, created_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, NOW())', [
            $version, $source, $format, $hash, $reason, 'draft', $userId ? (int) $userId : null,
        ]);
        $id = (int) self::lastInsertId();
        self::audit('template_draft_created', 1, $id, null, ['version_number' => $version, 'format' => $format, 'content_hash' => $hash], $reason, $userId);
        return $id;
    }

    public static function publishVersion($versionId, $reason, $userId = null)
    {
        self::ensureSchema();
        $version = self::findVersion($versionId);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قالب پیدا نشد.');
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('دلیل انتشار قالب الزامی است.');
        }
        $current = self::getPublishedTemplate();
        self::begin();
        try {
            self::execute("UPDATE contract_template_versions SET status = 'superseded', superseded_at = NOW() WHERE template_id = 1 AND status = 'published' AND id <> ?", [(int) $versionId]);
            self::execute("UPDATE contract_template_versions SET status = 'published', published_by = ?, published_at = NOW(), superseded_at = NULL, change_reason = ? WHERE id = ?", [$userId ? (int) $userId : null, $reason, (int) $versionId]);
            self::execute('UPDATE contract_templates SET current_version_id = ?, updated_at = NOW() WHERE id = 1', [(int) $versionId]);
            try {
                self::execute("UPDATE generated_contract_documents SET template_status = 'outdated_template' WHERE COALESCE(template_version_id, 0) <> ?", [(int) $versionId]);
            } catch (Throwable $e) {
            }
            self::audit('template_published', 1, (int) $versionId, $current ? ['version_id' => (int) $current['id']] : null, ['version_id' => (int) $versionId], $reason, $userId);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function restoreVersion($versionId, $reason, $userId = null)
    {
        $version = self::findVersion($versionId);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قالب پیدا نشد.');
        }
        $id = self::createVersion($version['body_source'], $version['body_format'], $reason, $userId);
        self::audit('template_restored_as_draft', 1, $id, ['source_version_id' => (int) $versionId], ['draft_version_id' => $id], $reason, $userId);
        return $id;
    }

    public static function resetToDefault($reason, $userId = null)
    {
        $id = self::createVersion(ContractDocument::defaultTemplate(), ContractTemplateRenderer::FORMAT_PLAIN, $reason, $userId);
        self::audit('template_reset_as_draft', 1, $id, null, ['draft_version_id' => $id], $reason, $userId);
        return $id;
    }

    public static function copyableSource($type = 'effective')
    {
        $template = $type === 'default' ? self::getDefaultTemplate() : self::getEffectiveTemplate();
        return (string) ($template['body_source'] ?? '');
    }

    public static function compareVersions($leftId, $rightId)
    {
        $left = self::findVersion((int) $leftId);
        $right = self::findVersion((int) $rightId);
        if (!$left || !$right) {
            throw new InvalidArgumentException('نسخه‌های مورد مقایسه پیدا نشدند.');
        }
        $leftLines = explode("\n", ContractTemplateRenderer::normalizeSource($left['body_source']));
        $rightLines = explode("\n", ContractTemplateRenderer::normalizeSource($right['body_source']));
        return [
            'left' => $left,
            'right' => $right,
            'added_lines' => array_values(array_diff($rightLines, $leftLines)),
            'removed_lines' => array_values(array_diff($leftLines, $rightLines)),
            'left_line_count' => count($leftLines),
            'right_line_count' => count($rightLines),
        ];
    }

    public static function validateTemplate($source, $format)
    {
        $source = ContractTemplateRenderer::normalizeSource($source);
        $errors = [];
        $warnings = [];
        if ($source === '') {
            $errors[] = 'متن قالب نمی‌تواند خالی باشد.';
        }
        if (mb_strlen($source, 'UTF-8') > 500000) {
            $errors[] = 'حجم متن قالب بیش از حد مجاز است.';
        }
        if (preg_match('/<(?:script|style|iframe|object|embed|form|input|button|link|meta)\b|\son[a-z]+\s*=|(?:javascript|vbscript)\s*:/i', $source)) {
            $errors[] = 'قالب شامل کد یا ویژگی ناامن است.';
        }
        preg_match_all('/\{\{[a-z0-9_]+\}\}/i', $source, $matches);
        $known = ContractDocument::variables();
        $unknown = array_values(array_diff(array_unique($matches[0] ?? []), $known));
        if ($unknown) {
            $warnings[] = 'متغیرهای ناشناخته: ' . implode('، ', $unknown);
        }
        if (!in_array($format, [ContractTemplateRenderer::FORMAT_PLAIN, ContractTemplateRenderer::FORMAT_HTML], true)) {
            $errors[] = 'فرمت قالب معتبر نیست.';
        }
        return ['valid' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'unknown_variables' => $unknown];
    }

    public static function variableCatalog()
    {
        $groups = [
            'قرارداد' => ['contract_number', 'contract_date', 'document_title', 'document_header'],
            'مشتری' => ['customer_'], 'مجموعه' => ['company_'], 'کالا' => ['items_table'],
            'اقساط' => ['installment_', 'first_due_date', 'last_due_date', 'remaining_amount', 'down_payment_amount', 'total_contract_amount'],
            'ضمانت و ضامن' => ['guarantee_', 'guarantor'], 'امضا' => ['signature_section'],
        ];
        $result = [];
        foreach (ContractDocument::variableDescriptions() as $code => $title) {
            $name = trim($code, '{}');
            $groupName = 'سایر';
            foreach ($groups as $group => $needles) {
                foreach ((array) $needles as $needle) {
                    if (strpos($name, $needle) === 0 || $name === $needle) {
                        $groupName = $group;
                        break 2;
                    }
                }
            }
            $result[$groupName][] = [
                'code' => $code,
                'title' => $title,
                'description' => 'در زمان تولید سند با ' . $title . ' جایگزین می‌شود.',
                'example' => self::sampleReplacements()[$code] ?? 'نمونه مقدار',
            ];
        }
        return $result;
    }

    public static function sampleReplacements()
    {
        $simple = [
            '{{contract_number}}' => 'PR-1405-1001', '{{contract_date}}' => '۱۴۰۵/۰۴/۲۲',
            '{{document_title}}' => 'قرارداد اجاره به شرط تملیک / امانت‌داری', '{{document_header}}' => 'نسخه پیش‌نمایش',
            '{{company_name}}' => 'مجموعه نمونه', '{{company_representative_name}}' => 'نماینده مجموعه',
            '{{company_representative_national_id}}' => '۰۰۱۲۳۴۵۶۷۸', '{{company_address}}' => 'نشانی نمونه مجموعه',
            '{{company_postal_code}}' => '۹۱۸۷۶۵۴۳۲۱', '{{company_phone}}' => '۰۵۱۳۰۰۰۰۰۰۰',
            '{{customer_full_name}}' => 'مشتری نمونه', '{{customer_father_name}}' => 'نام پدر',
            '{{customer_national_id}}' => '۱۲۳۴۵۶۷۸۹۰', '{{customer_issued_from}}' => 'مشهد',
            '{{customer_mobile}}' => '۰۹۱۵۱۲۳۴۵۶۷', '{{customer_secondary_phone}}' => '۰۵۱۳۰۰۰۰۰۰۰',
            '{{customer_address}}' => 'نشانی نمونه مشتری', '{{guarantee_type}}' => 'چک', '{{guarantee_count}}' => '۶',
            '{{guarantee_serial}}' => '۱۲۳۴۵۶', '{{guarantee_description}}' => 'ضمانت نمونه',
            '{{installment_count}}' => '۶', '{{total_contract_amount}}' => '۶۰٬۰۰۰٬۰۰۰ تومان',
            '{{down_payment_amount}}' => '۱۰٬۰۰۰٬۰۰۰ تومان', '{{remaining_amount}}' => '۵۰٬۰۰۰٬۰۰۰ تومان',
            '{{monthly_penalty_rate}}' => '۱۰', '{{legal_monthly_penalty_rate}}' => '۲۰',
            '{{late_penalty_grace_days}}' => '۵', '{{legal_penalty_clause}}' => 'شرایط جریمه حقوقی مطالعه و تایید شد.',
            '{{first_due_date}}' => '۱۴۰۵/۰۵/۲۲', '{{last_due_date}}' => '۱۴۰۵/۱۰/۲۲',
        ];
        $simple['{{items_table}}'] = '<table class="contract-print-table"><thead><tr><th>مدل کالا</th><th>شناسه</th></tr></thead><tbody><tr><td>کالای نمونه</td><td>۱۲۳۴۵۶۷۸۹</td></tr></tbody></table>';
        $simple['{{installments_guarantees_table}}'] = '<table class="contract-print-table"><thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th></tr></thead><tbody><tr><td>۱</td><td>۱۴۰۵/۰۵/۲۲</td><td>۱۰٬۰۰۰٬۰۰۰ تومان</td></tr><tr><td>۲</td><td>۱۴۰۵/۰۶/۲۲</td><td>۱۰٬۰۰۰٬۰۰۰ تومان</td></tr></tbody></table>';
        $simple['{{guarantors_section}}'] = '<section class="contract-note"><strong>ضامن نمونه</strong><p>کد ملی: ۱۲۳۴۵۶۷۸۹۰</p></section>';
        $simple['{{signature_section}}'] = '<section class="contract-signature-grid"><div class="contract-signature-box">امضای مجموعه</div><div class="contract-signature-box">امضای مشتری</div></section>';
        return $simple;
    }

    public static function previewHtml(array $template = null)
    {
        $template = $template ?: (self::getDraftTemplate() ?: self::getEffectiveTemplate());
        $body = ContractTemplateRenderer::render($template['body_source'], $template['body_format'], self::sampleReplacements());
        return '<div class="contract-document-body">' . $body . '</div>';
    }

    public static function stats()
    {
        self::ensureSchema();
        $effective = self::getEffectiveTemplate();
        $stale = 0;
        $documents = 0;
        try {
            $stale = (int) (self::fetch("SELECT COUNT(*) AS total FROM generated_contract_documents WHERE template_status = 'outdated_template'")['total'] ?? 0);
            $documents = (int) (self::fetch('SELECT COUNT(*) AS total FROM generated_contract_documents')['total'] ?? 0);
        } catch (Throwable $e) {
        }
        return ['effective' => $effective, 'stale_documents' => $stale, 'documents' => $documents, 'has_logo' => trim((string) Settings::get('contract_logo_path', Settings::get('logo_path', ''))) !== ''];
    }

    public static function startRebuildJob($userId = null)
    {
        self::ensureSchema();
        $effective = self::getEffectiveTemplate();
        if (empty($effective['id'])) {
            throw new InvalidArgumentException('ابتدا یک نسخه قالب را منتشر کنید.');
        }
        $count = (int) (self::fetch("SELECT COUNT(*) AS total FROM generated_contract_documents d WHERE d.template_status = 'outdated_template' AND NOT EXISTS (SELECT 1 FROM contract_document_versions v WHERE v.contract_id = d.contract_id AND v.is_finalized = 1)")['total'] ?? 0);
        self::execute('INSERT INTO contract_document_rebuild_jobs (target_template_version_id, status, total_documents, created_by, created_at) VALUES (?, ?, ?, ?, NOW())', [(int) $effective['id'], 'pending', $count, $userId ? (int) $userId : null]);
        return (int) self::lastInsertId();
    }

    public static function processRebuildJob($jobId, $batchSize = 25)
    {
        self::ensureSchema();
        $job = self::fetch('SELECT * FROM contract_document_rebuild_jobs WHERE id = ? LIMIT 1', [(int) $jobId]);
        if (!$job || in_array($job['status'], ['completed', 'cancelled'], true)) {
            return $job;
        }
        $batchSize = max(1, min(50, (int) $batchSize));
        self::execute("UPDATE contract_document_rebuild_jobs SET status = 'running', started_at = COALESCE(started_at, NOW()), updated_at = NOW() WHERE id = ?", [(int) $jobId]);
        $rows = self::fetchAll("SELECT d.contract_id FROM generated_contract_documents d WHERE d.template_status = 'outdated_template' AND d.contract_id > ? AND NOT EXISTS (SELECT 1 FROM contract_document_versions v WHERE v.contract_id = d.contract_id AND v.is_finalized = 1) ORDER BY d.contract_id ASC LIMIT " . $batchSize, [(int) $job['last_contract_id']]);
        $failed = [];
        $processed = 0;
        $lastId = (int) $job['last_contract_id'];
        foreach ($rows as $row) {
            $lastId = (int) $row['contract_id'];
            try {
                ContractDocument::generate($lastId, $job['created_by'] ?? null);
                $processed++;
            } catch (Throwable $e) {
                $failed[] = ['contract_id' => $lastId, 'message' => $e->getMessage()];
            }
        }
        $done = count($rows) < $batchSize;
        self::execute('UPDATE contract_document_rebuild_jobs SET processed_documents = processed_documents + ?, failed_documents = failed_documents + ?, last_contract_id = ?, failure_report_json = ?, status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?', [
            $processed, count($failed), $lastId, $failed ? json_encode($failed, JSON_UNESCAPED_UNICODE) : ($job['failure_report_json'] ?? null), $done ? 'completed' : 'running', $done ? date('Y-m-d H:i:s') : null, (int) $jobId,
        ]);
        return self::fetch('SELECT * FROM contract_document_rebuild_jobs WHERE id = ?', [(int) $jobId]);
    }

    public static function latestRebuildJob()
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM contract_document_rebuild_jobs ORDER BY id DESC LIMIT 1');
    }

    public static function audit($action, $templateId, $versionId, $oldValues, $newValues, $reason, $userId = null)
    {
        self::ensureSchema();
        self::execute('INSERT INTO contract_template_audit_logs (action, actor_id, template_id, version_id, old_values_json, new_values_json, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', [
            trim((string) $action), $userId ? (int) $userId : null, $templateId ? (int) $templateId : null, $versionId ? (int) $versionId : null,
            $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE), trim((string) $reason) ?: null,
        ]);
    }

    protected static function migrateLegacyTemplate()
    {
        $exists = self::fetch('SELECT id FROM contract_template_versions WHERE template_id = 1 LIMIT 1');
        if ($exists) {
            return;
        }
        $legacy = trim((string) Settings::get('contract_template_body', ''));
        if ($legacy === '') {
            return;
        }
        $hash = hash('sha256', ContractTemplateRenderer::FORMAT_PLAIN . "\0" . ContractTemplateRenderer::normalizeSource($legacy));
        self::execute("INSERT INTO contract_template_versions (template_id, version_number, body_source, body_format, content_hash, change_reason, status, created_at, published_at) VALUES (1, 1, ?, 'plain_text_v1', ?, 'انتقال خودکار قالب قدیمی', 'published', NOW(), NOW())", [$legacy, $hash]);
        $id = (int) self::lastInsertId();
        self::execute('UPDATE contract_templates SET current_version_id = ?, updated_at = NOW() WHERE id = 1', [$id]);
    }
}
