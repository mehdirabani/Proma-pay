<?php

/** Internal legal records only.  It never represents a draft as a judicial filing or official notice. */
final class LegalDocumentService
{
    public static function createInternal(array $case, $type, $actorId, array $input = [])
    {
        self::assertType($type);
        $uuid = self::uuid($input['request_uuid'] ?? '');
        $existing = Model::fetch('SELECT * FROM legal_documents WHERE request_uuid = ? LIMIT 1', [$uuid]);
        if ($existing) {
            if ((int) $existing['legal_case_id'] !== (int) $case['id'] || $existing['document_type'] !== $type) {
                throw new InvalidArgumentException('شناسه درخواست برای سند دیگری استفاده شده است.');
            }
            return $existing;
        }
        $deadline = parse_jalali_date($input['deadline_date'] ?? '') ?: null;
        $title = trim((string) ($input['title'] ?? '')) ?: self::defaultTitle($type);
        $content = trim((string) ($input['content'] ?? '')) ?: self::defaultContent($case, $type, $deadline);
        Model::execute(
            'INSERT INTO legal_documents (document_uuid, request_uuid, legal_case_id, contract_id, document_type, title, body, document_status, deadline_date, policy_snapshot_json, debt_snapshot_json, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                bin2hex(random_bytes(16)), $uuid, (int) $case['id'], (int) $case['contract_id'], $type, $title, $content,
                'internal_draft', $deadline,
                json_encode(LegalEligibilityService::policyForContract((int) $case['contract_id'])['policy'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode(LegalEligibilityService::forContract((int) $case['contract_id']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int) $actorId,
            ]
        );
        $document = self::find((int) Model::lastInsertId());
        self::audit($case, 'legal_document.internal_created', $actorId, ['document_id' => $document['id'], 'document_type' => $type]);
        return $document;
    }

    /** Requires a human legal confirmation and reference; this is not automatic external filing. */
    public static function confirmExternal($documentId, $actorId, array $input)
    {
        $document = self::find((int) $documentId);
        if (!$document) throw new InvalidArgumentException('سند حقوقی پیدا نشد.');
        if (empty($input['confirm_external_submission'])) {
            throw new InvalidArgumentException('تایید صریح ثبت واقعی در مرجع بیرونی الزامی است.');
        }
        $reference = trim((string) ($input['external_reference'] ?? ''));
        $authority = trim((string) ($input['external_authority'] ?? ''));
        $submittedAt = parse_jalali_date($input['external_submitted_at'] ?? '') ?: null;
        if ($reference === '' || $authority === '' || !$submittedAt) {
            throw new InvalidArgumentException('مرجع، شماره پیگیری واقعی و تاریخ ثبت بیرونی الزامی است.');
        }
        Model::execute(
            "UPDATE legal_documents SET document_status = 'external_submission_confirmed', external_reference = ?, external_authority = ?, external_submitted_at = ?, externally_confirmed_by = ?, externally_confirmed_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$reference, $authority, $submittedAt, (int) $actorId, (int) $documentId]
        );
        $document = self::find((int) $documentId);
        $case = LegalCase::find((int) $document['legal_case_id']);
        if ($case) {
            // A human-confirmed, externally filed document is an auditable
            // canonical referral.  Preserve an earlier referral timestamp;
            // never rewrite financial history on later document updates.
            Model::execute(
                "UPDATE legal_cases SET status = 'external_submission_confirmed', legal_referred_at = COALESCE(legal_referred_at, CONCAT(?, ' 00:00:00')), updated_at = NOW() WHERE id = ?",
                [$submittedAt, (int) $case['id']]
            );
            self::audit($case, 'legal_document.external_submission_confirmed', $actorId, [
                'document_id' => $documentId,
                'reference' => $reference,
                'authority' => $authority,
                'legal_referred_at' => $submittedAt,
            ]);
        }
        return $document;
    }

    public static function forCase($caseId)
    {
        return Model::fetchAll('SELECT ld.*, u.full_name AS created_by_name FROM legal_documents ld LEFT JOIN users u ON u.id = ld.created_by WHERE ld.legal_case_id = ? ORDER BY ld.id DESC', [(int) $caseId]);
    }

    public static function find($id)
    {
        return Model::fetch('SELECT * FROM legal_documents WHERE id = ? LIMIT 1', [(int) $id]);
    }

    private static function assertType($type)
    {
        if (!in_array($type, ['contractual_warning', 'petition_draft', 'complaint_draft'], true)) {
            throw new InvalidArgumentException('نوع سند حقوقی معتبر نیست.');
        }
    }

    private static function defaultTitle($type)
    {
        return [
            'contractual_warning' => 'اخطار قراردادی پیش از اقدام حقوقی',
            'petition_draft' => 'پیش‌نویس دادخواست مطالبه وجه',
            'complaint_draft' => 'پیش‌نویس شکواییه / درخواست حقوقی',
        ][$type];
    }

    private static function defaultContent(array $case, $type, $deadline)
    {
        $settings = Settings::allKeyed();
        $templateKey = [
            'contractual_warning' => 'legal_contractual_warning_template',
            'petition_draft' => 'legal_petition_draft_template',
            'complaint_draft' => 'legal_complaint_draft_template',
        ][$type];
        $template = trim((string) ($settings[$templateKey] ?? ''));
        if ($template === '') {
            $template = (string) (Settings::defaults()[$templateKey] ?? '');
        }
        $eligibility = LegalEligibilityService::forContract((int) ($case['contract_id'] ?? 0));
        $tokens = [
            '{{company_name}}' => (string) ($settings['company_name'] ?? $settings['system_name'] ?? 'پروما'),
            '{{customer_name}}' => (string) ($case['customer_name'] ?? ''),
            '{{customer_mobile}}' => (string) ($case['mobile'] ?? ''),
            '{{contract_number}}' => (string) ($case['contract_number'] ?? ''),
            '{{overdue_amount}}' => money_toman($eligibility['overdue_amount'] ?? 0),
            '{{deadline_date}}' => $deadline ? jdate($deadline) : 'تعیین نشده',
            '{{legal_notes}}' => (string) ($case['notes'] ?? ''),
        ];
        return strtr($template, $tokens);
    }

    private static function uuid($value)
    {
        $value = strtolower(trim((string) $value));
        if (!preg_match('/^[a-f0-9]{24,64}$/', $value)) $value = bin2hex(random_bytes(16));
        return $value;
    }

    private static function audit(array $case, $action, $actorId, array $newValues)
    {
        try {
            AuditLog::record('legal_document', $action, 'legal_case', (int) $case['id'], [
                'actor_user_id' => (int) $actorId, 'contract_id' => (int) $case['contract_id'], 'customer_id' => (int) $case['customer_id'],
                'new_values' => $newValues, 'description' => 'ثبت سند حقوقی داخلی با تفکیک از ثبت رسمی بیرونی.',
            ]);
        } catch (Throwable $ignored) {
        }
    }
}
