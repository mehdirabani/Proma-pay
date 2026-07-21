<?php

final class ContractDuplicateRepairService
{
    public static function scan($limit = 5000, $customerId = null)
    {
        $limit = max(100, min(10000, (int) $limit));
        $params = [];
        $customerFilter = '';
        if ((int) $customerId > 0) {
            $customerFilter = ' AND c.customer_id = ?';
            $params[] = (int) $customerId;
        }
        $rows = Model::fetchAll(
            "SELECT c.*, u.full_name AS customer_name,
                    (SELECT COUNT(*) FROM payments p WHERE p.contract_id = c.id AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0) AS effective_payment_count,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.contract_id = c.id AND p.status = 'paid' AND COALESCE(p.is_corrected, 0) = 0) AS effective_payment_amount,
                    (SELECT COUNT(*) FROM legal_cases lc WHERE lc.contract_id = c.id) AS legal_case_count
             FROM contracts c
             JOIN users u ON u.id = c.customer_id
             WHERE c.status != 'cancelled'
               AND NOT EXISTS (SELECT 1 FROM contract_duplicate_repairs r WHERE r.duplicate_contract_id = c.id)
               {$customerFilter}
             ORDER BY c.customer_id, c.created_at, c.id
             LIMIT {$limit}",
            $params
        );
        $buckets = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                (int) $row['customer_id'],
                normalize_money($row['principal_amount'] ?? 0),
                normalize_money($row['down_payment_amount'] ?? 0),
                MoneyMath::rateUnits($row['monthly_interest_rate'] ?? 0),
                (string) $row['interest_type'],
                (int) $row['months'],
                (string) $row['start_date'],
                (string) $row['first_due_date'],
                (int) ($row['assigned_operator_id'] ?? 0),
                trim((string) ($row['notes'] ?? '')),
            ]);
            $buckets[$key][] = $row;
        }

        $groups = [];
        foreach ($buckets as $contracts) {
            if (count($contracts) < 2) {
                continue;
            }
            usort($contracts, static function ($left, $right) {
                $paymentOrder = (int) ($right['effective_payment_count'] ?? 0) <=> (int) ($left['effective_payment_count'] ?? 0);
                return $paymentOrder !== 0 ? $paymentOrder : ((int) $left['id'] <=> (int) $right['id']);
            });
            $canonical = array_shift($contracts);
            $createdTimes = array_map(static function ($contract) {
                return strtotime((string) ($contract['created_at'] ?? '')) ?: 0;
            }, array_merge([$canonical], $contracts));
            $spread = max($createdTimes) - min($createdTimes);
            $confidence = self::confidenceForSpread($spread);
            $groups[] = [
                'key' => hash('sha256', implode(',', array_column(array_merge([$canonical], $contracts), 'id'))),
                'confidence' => $confidence,
                'canonical' => $canonical,
                'duplicates' => $contracts,
                'auto_repairable' => $confidence !== 'review' && self::allRepairable($contracts),
            ];
        }
        return $groups;
    }

    public static function archiveDuplicates($canonicalId, array $duplicateIds, $adminId)
    {
        $canonical = Contract::find((int) $canonicalId);
        if (!$canonical || ($canonical['status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('قرارداد مرجع معتبر نیست.');
        }
        $duplicateIds = array_values(array_unique(array_filter(array_map('intval', $duplicateIds), static function ($id) use ($canonicalId) {
            return $id > 0 && $id !== (int) $canonicalId;
        })));
        if (!$duplicateIds) {
            throw new InvalidArgumentException('قرارداد تکراری انتخاب نشده است.');
        }

        $archived = 0;
        foreach ($duplicateIds as $duplicateId) {
            $duplicate = Contract::find($duplicateId);
            if (!$duplicate || !self::sameFingerprint($canonical, $duplicate)) {
                throw new InvalidArgumentException('قرارداد انتخاب‌شده با قرارداد مرجع تطابق مالی ندارد.');
            }
            $summary = Contract::cancellationSummary($duplicateId);
            if ((int) ($summary['confirmed_payment_count'] ?? 0) > 0 || (int) ($summary['legal_case_count'] ?? 0) > 0 || (int) ($summary['accounting_relation_count'] ?? 0) > 0) {
                throw new InvalidArgumentException('قرارداد تکراری دارای پرداخت واقعی، پرونده حقوقی یا وابستگی حسابداری است و فقط باید دستی بررسی شود.');
            }
            if (in_array(($duplicate['status'] ?? ''), ['completed', 'closed'], true)) {
                throw new InvalidArgumentException('قرارداد تکمیل‌شده از ترمیم خودکار مستثنا است.');
            }
            $spread = abs((strtotime((string) ($canonical['created_at'] ?? '')) ?: 0) - (strtotime((string) ($duplicate['created_at'] ?? '')) ?: 0));
            $confidence = self::confidenceForSpread($spread);
            if ($confidence === 'review') {
                throw new InvalidArgumentException('فاصله زمانی ایجاد این قراردادها زیاد است و ترمیم خودکار برای آن‌ها مجاز نیست.');
            }
            $snapshot = [
                'canonical' => $canonical,
                'duplicate' => $duplicate,
                'summary' => $summary,
            ];
            Model::begin();
            try {
                Contract::cancel($duplicateId, 'آرشیو قرارداد تکراری؛ قرارداد مرجع: ' . $canonical['contract_number'], $adminId, false);
                Model::execute('UPDATE contract_requests SET contract_id = ? WHERE contract_id = ?', [(int) $canonicalId, $duplicateId]);
                Model::execute(
                    'INSERT INTO contract_duplicate_repairs (canonical_contract_id, duplicate_contract_id, action, confidence, snapshot_json, repaired_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                    [(int) $canonicalId, $duplicateId, 'archive_duplicate', $confidence, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $adminId]
                );
                AuditLog::record('contract', 'duplicate_archived', 'contract', $duplicateId, [
                    'actor_user_id' => $adminId,
                    'customer_id' => (int) $canonical['customer_id'],
                    'contract_id' => $duplicateId,
                    'description' => 'قرارداد تکراری بدون حذف سوابق آرشیو شد.',
                    'new_values' => ['canonical_contract_id' => (int) $canonicalId],
                ]);
                Model::commit();
                if (class_exists('SystemOutbox')) {
                    try {
                        SystemOutbox::processPending(20);
                    } catch (Throwable $outboxError) {
                        ErrorHandler::log('contract_duplicate_repair_outbox', $outboxError, 500);
                    }
                }
                $archived++;
            } catch (Throwable $e) {
                Model::rollBack();
                throw $e;
            }
        }
        return $archived;
    }

    protected static function allRepairable(array $contracts)
    {
        foreach ($contracts as $contract) {
            if ((int) ($contract['effective_payment_count'] ?? 0) > 0 || (int) ($contract['legal_case_count'] ?? 0) > 0 || in_array(($contract['status'] ?? ''), ['completed', 'closed'], true)) {
                return false;
            }
        }
        return true;
    }

    protected static function sameFingerprint(array $left, array $right)
    {
        foreach (['customer_id', 'months', 'assigned_operator_id'] as $field) {
            if ((int) ($left[$field] ?? 0) !== (int) ($right[$field] ?? 0)) {
                return false;
            }
        }
        foreach (['principal_amount', 'down_payment_amount'] as $field) {
            if (normalize_money($left[$field] ?? 0) !== normalize_money($right[$field] ?? 0)) {
                return false;
            }
        }
        foreach (['interest_type', 'start_date', 'first_due_date', 'notes'] as $field) {
            if ((string) ($left[$field] ?? '') !== (string) ($right[$field] ?? '')) {
                return false;
            }
        }
        return MoneyMath::rateUnits($left['monthly_interest_rate'] ?? 0) === MoneyMath::rateUnits($right['monthly_interest_rate'] ?? 0);
    }

    protected static function confidenceForSpread($spread)
    {
        $spread = max(0, (int) $spread);
        return $spread <= 3600 ? 'high' : ($spread <= 86400 ? 'medium' : 'review');
    }
}
