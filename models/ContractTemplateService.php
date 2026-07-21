<?php

class ContractTemplateService extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        SchemaGuard::requireColumns('contract_templates', ['name', 'description', 'status', 'current_version_id', 'created_by', 'created_at', 'updated_at']);
        SchemaGuard::requireColumns('contract_template_versions', ['template_id', 'version_number', 'body_source', 'body_format', 'content_hash', 'change_reason', 'status', 'created_by', 'created_at', 'published_by', 'published_at', 'superseded_at', 'archived_at', 'archived_by']);
        SchemaGuard::requireColumns('contract_template_audit_logs', ['action', 'actor_id', 'template_id', 'version_id', 'old_values_json', 'new_values_json', 'reason', 'ip_address', 'created_at']);
        SchemaGuard::requireColumns('contract_document_rebuild_jobs', ['target_template_version_id', 'status', 'total_documents', 'processed_documents', 'failed_documents', 'last_contract_id', 'failure_report_json', 'created_by', 'created_at', 'started_at', 'completed_at', 'updated_at']);
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
            WHERE v.template_id = 1 AND v.status = 'published' AND v.archived_at IS NULL
            ORDER BY v.version_number DESC, v.id DESC LIMIT 1");
    }

    public static function getDraftTemplate()
    {
        self::ensureSchema();
        return self::fetch("SELECT v.*, u.full_name AS created_by_name
            FROM contract_template_versions v
            LEFT JOIN users u ON u.id = v.created_by
            WHERE v.template_id = 1 AND v.status = 'draft' AND v.archived_at IS NULL
            ORDER BY v.version_number DESC, v.id DESC LIMIT 1");
    }

    public static function getEffectiveTemplate()
    {
        return self::getPublishedTemplate() ?: self::getDefaultTemplate();
    }

    public static function versions($includeArchived = false)
    {
        self::ensureSchema();
        $where = $includeArchived ? '' : ' AND v.archived_at IS NULL';
        $rows = self::fetchAll("SELECT v.*, u.full_name AS created_by_name, p.full_name AS published_by_name,
                (SELECT COUNT(*) FROM generated_contract_documents g WHERE g.template_version_id = v.id) AS generated_document_reference_count,
                (SELECT COUNT(*) FROM contract_document_versions d WHERE d.template_version_id = v.id) AS document_reference_count,
                (SELECT COUNT(*) FROM contract_document_versions f WHERE f.template_version_id = v.id AND f.is_finalized = 1) AS finalized_document_reference_count,
                CASE WHEN t.current_version_id = v.id THEN 1 ELSE 0 END AS is_current
            FROM contract_template_versions v
            INNER JOIN contract_templates t ON t.id = v.template_id
            LEFT JOIN users u ON u.id = v.created_by
            LEFT JOIN users p ON p.id = v.published_by
            WHERE v.template_id = 1" . $where . "
            ORDER BY v.version_number DESC, v.id DESC");
        foreach ($rows as &$row) {
            $row['usage_count'] = (int) $row['generated_document_reference_count'] + (int) $row['document_reference_count'];
            $row['can_delete'] = empty($row['is_current'])
                && (int) $row['usage_count'] === 0
                && (int) $row['finalized_document_reference_count'] === 0
                && in_array($row['status'], ['draft', 'superseded', 'archived'], true);
            $row['can_archive'] = empty($row['is_current']) && empty($row['archived_at']);
        }
        unset($row);
        return $rows;
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
            self::audit('template_validation_failed', 1, null, null, ['errors' => $validation['errors'], 'warnings' => $validation['warnings']], 'اعتبارسنجی پیش‌نویس', $userId);
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
        if (substr_count($source, '**') >= 2 && ContractTemplateRenderer::hasBalancedImportantMarkers($source)) {
            self::audit('important_clause_parser_used', 1, $id, null, ['marker_pairs' => (int) (substr_count($source, '**') / 2)], $reason, $userId);
        }
        return $id;
    }

    public static function publishVersion($versionId, $reason, $userId = null)
    {
        self::ensureSchema();
        $version = self::findVersion($versionId);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قالب پیدا نشد.');
        }
        if (!empty($version['archived_at']) || ($version['status'] ?? '') === 'archived') {
            throw new InvalidArgumentException('نسخه بایگانی‌شده باید ابتدا به‌عنوان پیش‌نویس تازه بازیابی شود.');
        }
        $validation = self::validateTemplate($version['body_source'], $version['body_format']);
        if (!$validation['valid']) {
            self::audit('template_validation_failed', 1, (int) $versionId, null, ['errors' => $validation['errors'], 'warnings' => $validation['warnings']], 'اعتبارسنجی پیش از انتشار', $userId);
            throw new InvalidArgumentException(implode(' ', $validation['errors']));
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

    public static function referenceCounts($versionId)
    {
        self::ensureSchema();
        $result = ['generated' => 0, 'documents' => 0, 'finalized' => 0, 'verification_failed' => false];
        try {
            $result['generated'] = (int) (self::fetch('SELECT COUNT(*) AS total FROM generated_contract_documents WHERE template_version_id = ?', [(int) $versionId])['total'] ?? 0);
            $result['documents'] = (int) (self::fetch('SELECT COUNT(*) AS total FROM contract_document_versions WHERE template_version_id = ?', [(int) $versionId])['total'] ?? 0);
            $result['finalized'] = (int) (self::fetch('SELECT COUNT(*) AS total FROM contract_document_versions WHERE template_version_id = ? AND is_finalized = 1', [(int) $versionId])['total'] ?? 0);
        } catch (Throwable $e) {
            $result['verification_failed'] = true;
        }
        return $result;
    }

    public static function archiveVersion($versionId, $reason, $userId = null)
    {
        self::ensureSchema();
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت بایگانی نسخه را وارد کنید.');
        }
        $version = self::findVersion($versionId);
        if (!$version) {
            throw new InvalidArgumentException('نسخه قالب پیدا نشد.');
        }
        if (!empty($version['archived_at']) || ($version['status'] ?? '') === 'archived') {
            return false;
        }
        $template = self::fetch('SELECT current_version_id FROM contract_templates WHERE id = 1 LIMIT 1');
        if ((int) ($template['current_version_id'] ?? 0) === (int) $versionId) {
            throw new InvalidArgumentException('نسخه فعال قالب قابل بایگانی نیست. ابتدا نسخه دیگری را منتشر کنید.');
        }
        $counts = self::referenceCounts($versionId);
        self::begin();
        try {
            self::execute("UPDATE contract_template_versions SET status = 'archived', archived_at = NOW(), archived_by = ? WHERE id = ? AND archived_at IS NULL", [$userId ? (int) $userId : null, (int) $versionId]);
            self::audit('template_version_archived', 1, (int) $versionId, ['status' => $version['status']], ['status' => 'archived', 'references' => $counts], $reason, $userId);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        return true;
    }

    public static function deleteVersion($versionId, $reason, $userId = null)
    {
        self::ensureSchema();
        $reason = trim((string) $reason);
        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت حذف نسخه را وارد کنید.');
        }
        $version = self::findVersion($versionId);
        if (!$version) {
            return false;
        }
        $template = self::fetch('SELECT current_version_id FROM contract_templates WHERE id = 1 LIMIT 1');
        if ((int) ($template['current_version_id'] ?? 0) === (int) $versionId || ($version['status'] ?? '') === 'published') {
            throw new InvalidArgumentException('نسخه فعال قالب قابل حذف نیست. ابتدا نسخه دیگری را منتشر کنید.');
        }
        $counts = self::referenceCounts($versionId);
        if ($counts['verification_failed']) {
            throw new RuntimeException('بررسی ارجاع‌های حقوقی نسخه کامل نشد؛ حذف برای حفظ سوابق متوقف شد.');
        }
        if ($counts['generated'] > 0 || $counts['documents'] > 0 || $counts['finalized'] > 0) {
            throw new InvalidArgumentException('این نسخه در اسناد قرارداد استفاده شده است و برای حفظ تاریخچه حقوقی قابل حذف دائمی نیست.');
        }
        if (!in_array($version['status'], ['draft', 'superseded', 'archived'], true)) {
            throw new InvalidArgumentException('وضعیت این نسخه اجازه حذف دائمی را نمی‌دهد.');
        }
        self::begin();
        try {
            self::audit('template_version_deleted', 1, (int) $versionId, ['version_number' => (int) $version['version_number'], 'status' => $version['status']], ['references' => $counts], $reason, $userId);
            self::execute('DELETE FROM contract_template_versions WHERE id = ? AND template_id = 1', [(int) $versionId]);
            self::commit();
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
        return true;
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
        if (preg_match('/\sstyle\s*=/i', $source)) {
            $errors[] = 'استایل درون‌خطی پشتیبانی نمی‌شود؛ ظاهر چاپ باید از پروفایل چاپ کنترل شود.';
        }
        if (!ContractTemplateRenderer::hasBalancedImportantMarkers($source)) {
            $warnings[] = 'یک علامت ** بدون جفت در قالب پیدا شد.';
        }
        if ($format === ContractTemplateRenderer::FORMAT_HTML) {
            preg_match_all('/<\s*\/?\s*([a-z][a-z0-9]*)\b/i', $source, $tagMatches);
            $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'span', 'section', 'div', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];
            $unsupported = array_values(array_diff(array_unique(array_map('strtolower', $tagMatches[1] ?? [])), $allowedTags));
            if ($unsupported) {
                $warnings[] = 'تگ‌های پشتیبانی‌نشده حذف می‌شوند: ' . implode('، ', $unsupported);
            }
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
        return ['valid' => !$errors, 'errors' => $errors, 'warnings' => array_values(array_unique($warnings)), 'unknown_variables' => $unknown];
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
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        self::execute('INSERT INTO contract_template_audit_logs (action, actor_id, template_id, version_id, old_values_json, new_values_json, reason, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
            trim((string) $action), $userId ? (int) $userId : null, $templateId ? (int) $templateId : null, $versionId ? (int) $versionId : null,
            $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE), trim((string) $reason) ?: null,
            $ip !== '' ? mb_substr($ip, 0, 45, 'UTF-8') : null,
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
