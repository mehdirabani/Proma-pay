<?php

/**
 * Append-only financial audit journal for V6 payment movements.
 * Amounts are integer Toman; callers must post inside the business transaction.
 */
final class FinancialLedgerService
{
    private const COMPONENTS = ['principal', 'normal_late_fee', 'legal_late_fee', 'legal_cost', 'down_payment', 'unallocated', 'discount', 'write_off'];

    public static function append(int $contractId, int $customerId, string $eventType, string $sourceType, ?int $sourceId, string $idempotencyKey, array $lines, ?int $actorId = null, string $description = '', ?int $reversesEventId = null, array $metadata = []): int
    {
        if (!Model::db()->inTransaction()) {
            throw new LogicException('ثبت دفترکل باید در تراکنش عملیات مالی انجام شود.');
        }
        if ($contractId <= 0 || $customerId <= 0 || trim($idempotencyKey) === '' || mb_strlen($idempotencyKey, 'UTF-8') > 190) {
            throw new InvalidArgumentException('شناسه‌های دفترکل مالی معتبر نیست.');
        }
        $existing = Model::fetch('SELECT id FROM financial_ledger_events WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
        if ($existing) return (int) $existing['id'];

        $cleanLines = [];
        foreach ($lines as $line) {
            $component = (string) ($line['component'] ?? '');
            if (!in_array($component, self::COMPONENTS, true)) throw new InvalidArgumentException('جزء دفترکل معتبر نیست.');
            $rawDelta = $line['delta_toman'] ?? null;
            if (!is_int($rawDelta) && !(is_string($rawDelta) && preg_match('/\A-?\d+\z/', $rawDelta))) {
                throw new InvalidArgumentException('مبلغ هر ردیف دفترکل باید عدد صحیح تومان باشد.');
            }
            $delta = (int) $rawDelta;
            if ($delta === 0) continue;
            $cleanLines[] = [
                'component' => $component,
                'delta_toman' => $delta,
                'source_line_id' => !empty($line['source_line_id']) ? (int) $line['source_line_id'] : null,
                'description' => mb_substr(trim((string) ($line['description'] ?? '')), 0, 255, 'UTF-8') ?: null,
            ];
        }
        if (!$cleanLines) throw new InvalidArgumentException('رویداد دفترکل باید حداقل یک ردیف غیرصفر داشته باشد.');
        $uuid = self::uuid();
        Model::execute(
            'INSERT INTO financial_ledger_events (event_uuid, contract_id, customer_id, event_type, source_type, source_id, idempotency_key, effective_at, posted_by, description, reverses_event_id, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW())',
            [$uuid, $contractId, $customerId, self::slug($eventType), self::slug($sourceType), $sourceId, mb_substr($idempotencyKey, 0, 190, 'UTF-8'), $actorId ?: null, mb_substr(trim($description), 0, 500, 'UTF-8') ?: null, $reversesEventId, $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]
        );
        $eventId = (int) Model::lastInsertId();
        foreach ($cleanLines as $line) {
            Model::execute(
                'INSERT INTO financial_ledger_lines (event_id, component, delta_toman, source_line_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$eventId, $line['component'], $line['delta_toman'], $line['source_line_id'], $line['description']]
            );
        }
        return $eventId;
    }

    public static function payment(int $paymentId, int $actorId = 0): ?int
    {
        $payment = Model::fetch('SELECT p.*, c.customer_id FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE p.id = ? LIMIT 1', [$paymentId]);
        if (!$payment || ($payment['status'] ?? '') !== 'paid') return null;
        $lines = [];
        $allocations = Model::fetchAll('SELECT * FROM payment_allocations WHERE payment_id = ? AND COALESCE(is_reversal, 0) = 0 ORDER BY id', [$paymentId]);
        foreach ($allocations as $allocation) {
            foreach ([
                'principal_applied' => 'principal',
                'normal_penalty_applied' => 'normal_late_fee',
                'legal_penalty_applied' => 'legal_late_fee',
            ] as $field => $component) {
                $amount = normalize_money($allocation[$field] ?? 0);
                if ($amount > 0) $lines[] = ['component' => $component, 'delta_toman' => -$amount, 'source_line_id' => (int) $allocation['id']];
            }
        }
        if (($payment['payment_type'] ?? '') === 'legal_cost') {
            $rows = Model::fetchAll('SELECT * FROM legal_cost_payment_allocations WHERE payment_id = ? AND is_reversal = 0 ORDER BY id', [$paymentId]);
            foreach ($rows as $row) {
                $amount = normalize_money($row['allocated_amount_toman'] ?? 0);
                if ($amount > 0) $lines[] = ['component' => 'legal_cost', 'delta_toman' => -$amount, 'source_line_id' => (int) $row['id']];
            }
        }
        if (!$lines && ($payment['payment_type'] ?? '') === 'down_payment') {
            $amount = normalize_money($payment['amount'] ?? 0);
            if ($amount > 0) $lines[] = ['component' => 'down_payment', 'delta_toman' => -$amount];
        }
        if (!$lines) return null; // Legacy/direct payments without allocation detail remain visible in the legacy reconciliation report.
        return self::append(
            (int) $payment['contract_id'], (int) $payment['customer_id'], 'payment', 'payment', $paymentId,
            'payment:' . $paymentId, $lines, $actorId ?: (int) ($payment['user_id'] ?? 0),
            'ثبت تخصیص پرداخت ' . (int) $paymentId, null, ['payment_type' => (string) ($payment['payment_type'] ?? 'installment')]
        );
    }

    public static function reversePayment(int $paymentId, int $actorId, string $reason): ?int
    {
        $payment = Model::fetch('SELECT p.*, c.customer_id FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE p.id = ? LIMIT 1 FOR UPDATE', [$paymentId]);
        if (!$payment) return null;
        $original = Model::fetch('SELECT * FROM financial_ledger_events WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', ['payment:' . $paymentId]);
        if (!$original) return null;
        $reversalKey = 'payment-reversal:' . $paymentId;
        $existing = Model::fetch('SELECT id FROM financial_ledger_events WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$reversalKey]);
        if ($existing) return (int) $existing['id'];
        $lines = Model::fetchAll('SELECT component, delta_toman, id AS source_line_id FROM financial_ledger_lines WHERE event_id = ? ORDER BY id', [(int) $original['id']]);
        foreach ($lines as &$line) $line['delta_toman'] = -(int) $line['delta_toman'];
        unset($line);
        return self::append((int) $payment['contract_id'], (int) $payment['customer_id'], 'reversal', 'payment', $paymentId, $reversalKey, $lines, $actorId, 'برگشت پرداخت ' . $paymentId . ': ' . $reason, (int) $original['id']);
    }

    public static function approveLegalCost(array $cost, int $actorId): ?int
    {
        if ((int) ($cost['chargeable_to_customer'] ?? 0) !== 1 || empty($cost['legal_case_id']) || normalize_money($cost['amount_toman'] ?? 0) <= 0) return null;
        if (empty($cost['customer_id'])) {
            $contract = Model::fetch('SELECT customer_id FROM contracts WHERE id = ? LIMIT 1', [(int) $cost['contract_id']]);
            $cost['customer_id'] = (int) ($contract['customer_id'] ?? 0);
        }
        if ((int) ($cost['customer_id'] ?? 0) <= 0) return null;
        $referred = Model::fetch('SELECT id FROM legal_cases WHERE id = ? AND contract_id = ? AND legal_referred_at IS NOT NULL LIMIT 1', [(int) $cost['legal_case_id'], (int) $cost['contract_id']]);
        if (!$referred) return null;
        return self::append((int) $cost['contract_id'], (int) ($cost['customer_id'] ?? 0), 'charge', 'legal_cost', (int) $cost['id'], 'legal-cost:' . (int) $cost['id'], [
            ['component' => 'legal_cost', 'delta_toman' => normalize_money($cost['amount_toman'])],
        ], $actorId, 'تأیید هزینه حقوقی: ' . (string) ($cost['title'] ?? ''));
    }

    public static function reverseLegalCost(array $cost, int $actorId, string $reason): ?int
    {
        if (empty($cost['customer_id'])) {
            $contract = Model::fetch('SELECT customer_id FROM contracts WHERE id = ? LIMIT 1', [(int) $cost['contract_id']]);
            $cost['customer_id'] = (int) ($contract['customer_id'] ?? 0);
        }
        if ((int) ($cost['customer_id'] ?? 0) <= 0) return null;
        $key = 'legal-cost:' . (int) ($cost['id'] ?? 0);
        $original = Model::fetch('SELECT * FROM financial_ledger_events WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$key]);
        if (!$original) return null;
        $lines = Model::fetchAll('SELECT component, delta_toman, id AS source_line_id FROM financial_ledger_lines WHERE event_id = ? ORDER BY id', [(int) $original['id']]);
        foreach ($lines as &$line) $line['delta_toman'] = -(int) $line['delta_toman'];
        unset($line);
        return self::append((int) $cost['contract_id'], (int) ($cost['customer_id'] ?? 0), 'reversal', 'legal_cost', (int) $cost['id'], 'legal-cost-reversal:' . (int) $cost['id'], $lines, $actorId, 'برگشت هزینه حقوقی: ' . $reason, (int) $original['id']);
    }

    public static function recentForContract(int $contractId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Model::fetchAll(
            'SELECT e.id, e.event_uuid, e.event_type, e.source_type, e.source_id, e.description, e.effective_at, e.posted_by, u.full_name AS actor_name, l.component, l.delta_toman, l.source_line_id, l.id AS line_id FROM financial_ledger_events e JOIN financial_ledger_lines l ON l.event_id = e.id LEFT JOIN users u ON u.id = e.posted_by WHERE e.contract_id = ? ORDER BY e.id DESC, l.id DESC LIMIT ' . $limit,
            [$contractId]
        );
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/', $value) ? $value : 'financial_event';
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
